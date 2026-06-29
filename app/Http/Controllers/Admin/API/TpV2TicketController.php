<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Models\BookingAttempt;
use App\Services\AgentBalanceService;
use App\Services\HashIdService;
use App\Services\SearchV2\TpV2TicketService;

class TpV2TicketController extends BaseController
{
    public function __construct(
        private readonly TpV2TicketService $ticketService,
        private readonly AgentBalanceService $balanceService,
    ) {}

    public function issueTicket(Request $request, int|string $id)
    {
        $realId  = hashid_decode(HashIdService::BOOKING_ATTEMPT, $id) ?? $id;
        $attempt = BookingAttempt::findOrFail($realId);

        if (!in_array($attempt->status, ['committed', 'booking_confirmed'], true)) {
            return response()->json([
                'status'  => false,
                'message' => 'Ticket can only be issued for a committed booking. Current status: ' . $attempt->status,
            ], 422);
        }

        $userId = optional(auth()->user())->id;
        $agent  = $this->balanceService->resolveAgentForUser($userId);

        if (!$agent) {
            return response()->json([
                'status'  => false,
                'message' => 'Agent account not found.',
            ], 422);
        }

        try {
            $amount = $this->balanceService->resolveBookingAmount($attempt);
            $this->balanceService->assertSufficientBalance($agent, $amount);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        try {
            $result = $this->ticketService->issue($attempt, $userId);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() ?: 'Ticketing failed. Please try again.',
            ], 500);
        }

        try {
            $this->balanceService->debitForBooking($agent, $amount, $attempt, $userId);
        } catch (Exception $e) {
            report($e);
        }

        return response()->json([
            'status'         => true,
            'message'        => 'Ticket issued successfully.',
            'ticket_numbers' => $result['ticket_numbers'],
            'ticketed_at'    => $result['ticketed_at'],
        ]);
    }
}
