<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Models\BookingAttempt;
use App\Services\AgentBalanceService;
use App\Services\HashIdService;
use App\Services\SearchV2\TpV2VoidService;

class TpV2VoidController extends BaseController
{
    public function __construct(
        private readonly TpV2VoidService $voidService,
        private readonly AgentBalanceService $balanceService,
    ) {}

    public function voidTicket(Request $request, int|string $id)
    {
        $request->validate([
            'ticket_numbers'   => ['required', 'array', 'min:1'],
            'ticket_numbers.*' => ['required', 'string'],
        ]);

        $realId  = hashid_decode(HashIdService::BOOKING_ATTEMPT, $id) ?? $id;
        $attempt = BookingAttempt::findOrFail($realId);

        if ($attempt->status !== 'ticketed') {
            return response()->json([
                'status'  => false,
                'message' => 'Only ticketed bookings can be voided. Current status: ' . $attempt->status,
            ], 422);
        }

        $requestedTickets = $request->input('ticket_numbers');
        $issuedTickets    = is_array($attempt->ticket_numbers) ? $attempt->ticket_numbers : [];

        $invalid = array_diff($requestedTickets, $issuedTickets);
        if (!empty($invalid)) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid ticket number(s): ' . implode(', ', $invalid),
            ], 422);
        }

        $userId = optional(auth()->user())->id;

        try {
            $result = $this->voidService->void($attempt, $requestedTickets, $userId);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() ?: 'Ticket void failed. Please try again.',
            ], 500);
        }

        try {
            $agent = $this->balanceService->resolveAgentForUser($userId);
            if ($agent) {
                $amount = $this->balanceService->resolveBookingAmount($attempt);
                $this->balanceService->creditForVoid($agent, $amount, $attempt, $userId);
            }
        } catch (Exception $e) {
            report($e);
        }

        return response()->json([
            'status'          => true,
            'message'         => 'Ticket(s) voided successfully.',
            'pnr'             => $result['pnr'],
            'voided_at'       => $result['voided_at'],
            'voided_tickets'  => $result['voided_tickets'],
            'document_status' => $result['document_status'],
        ]);
    }
}
