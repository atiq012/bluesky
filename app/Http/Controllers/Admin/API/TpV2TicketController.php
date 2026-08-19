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

        // Already ticketed at entry — skip the wallet entirely, the service will hand back
        // the existing documents without moving money again
        $agent    = null;
        $reserved = false;

        if ($attempt->status !== 'ticketed') {
            $agent = $this->balanceService->resolveAgentForUser($userId);

            if (!$agent) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Agent account not found.',
                ], 422);
            }

            try {
                // The hold is taken before Travelport is called, not after it returns. Issuance
                // takes minutes, and a check made at the start of that window is stale by the
                // end of it — a concurrent request would still see the full balance.
                $amount = $this->balanceService->resolveBookingAmount($attempt);
                $this->balanceService->reserveForBooking($agent, $amount, $attempt, $userId);
                $reserved = true;
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
            $settleFailed  = false;

            if ($reserved) {
                try {
                    // A lost ticketing claim resolves to the existing-ticket result: no new
                    // documents were bought, so the hold is released rather than charged
                    $alreadyIssued
                        ? $this->balanceService->releaseReservation($agent, $attempt, $userId)
                        : $this->balanceService->settleReservation($agent, $attempt, $userId);
                } catch (Exception $e) {
                    // Tickets exist at the airline and cannot be pulled back, so the response
                    // must not claim a clean success. The hold stays in place — the money is
                    // still ring-fenced — and the booking is flagged for manual settlement.
                    report($e);
                    $settleFailed = true;
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
                'payment_settled' => !$settleFailed,
                'payment_warning' => $settleFailed
                    ? 'Ticket issued, but the balance could not be settled. Funds remain on hold — contact support.'
                    : null,
            ]);
        } catch (Exception $e) {
            report($e);

            // Nothing was bought, so the held funds go back. Guarded on the attempt's real
            // status in case the failure happened after the documents were issued.
            if ($reserved && $attempt->fresh()?->status !== 'ticketed') {
                try {
                    $this->balanceService->releaseReservation($agent, $attempt->fresh() ?? $attempt, $userId);
                } catch (Exception $releaseEx) {
                    report($releaseEx);
                }
            }

            // A lost claim is a conflict, not a server fault — the client may retry later
            $httpStatus = $e->getCode() === TpV2TicketService::ERROR_IN_PROGRESS ? 409 : 500;

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() ?: 'Ticketing failed. Please try again.',
            ], $httpStatus);
        }
    }
}
