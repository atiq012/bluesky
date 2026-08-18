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

        // 'ticketed' is allowed through so a retry gets the documents already issued back,
        // rather than a confusing rejection
        if (!in_array($attempt->status, ['committed', 'booking_confirmed', 'ticketed'], true)) {
            return response()->json([
                'status'  => false,
                'message' => 'Ticket can only be issued for a committed booking. Current status: ' . $attempt->status,
            ], 422);
        }

        $userId = optional(auth()->user())->id;

        // Already ticketed at entry — skip the wallet check entirely, the service will hand back
        // the existing documents without moving money again
        $agent  = null;
        $amount = null;

        if ($attempt->status !== 'ticketed') {
            $agent = $this->balanceService->resolveAgentForUser($userId);

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
        }

        try {
            $result = $this->ticketService->issue($attempt, $userId);

            $alreadyIssued = (bool) ($result['already_issued'] ?? false);

            // Also guards the mid-flight race case: a lost ticketing claim resolves to the same
            // existing-ticket result, so the wallet must not be debited a second time either
            if (!$alreadyIssued && $agent) {
                try {
                    $this->balanceService->debitForBooking($agent, $amount, $attempt, $userId);
                } catch (Exception $e) {
                    report($e);
                }
            }

            return response()->json([
                'status'         => true,
                'message'        => $alreadyIssued
                    ? 'Ticket was already issued for this booking.'
                    : 'Ticket issued successfully.',
                'already_issued' => $alreadyIssued,
                'ticket_numbers' => $result['ticket_numbers'],
                'ticketed_at'    => $result['ticketed_at'],
            ]);
        } catch (Exception $e) {
            report($e);

            // A lost claim is a conflict, not a server fault — the client may retry later
            $httpStatus = $e->getCode() === TpV2TicketService::ERROR_IN_PROGRESS ? 409 : 500;

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() ?: 'Ticketing failed. Please try again.',
            ], $httpStatus);
        }
    }
}
