<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Models\BookingAttempt;
use App\Services\HashIdService;
use App\Services\SearchV2\TpV2TicketService;

class TpV2TicketController extends BaseController
{
    public function __construct(
        private readonly TpV2TicketService $ticketService,
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

        try {
            $result = $this->ticketService->issue($attempt, optional(auth()->user())->id);

            return response()->json([
                'status'         => true,
                'message'        => 'Ticket issued successfully.',
                'ticket_numbers' => $result['ticket_numbers'],
                'ticketed_at'    => $result['ticketed_at'],
            ]);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() ?: 'Ticketing failed. Please try again.',
            ], 500);
        }
    }
}
