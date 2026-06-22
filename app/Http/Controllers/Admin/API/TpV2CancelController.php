<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Models\BookingAttempt;
use App\Services\HashIdService;
use App\Services\SearchV2\TpV2CancelService;

class TpV2CancelController extends BaseController
{
    public function __construct(
        private readonly TpV2CancelService $cancelService,
    ) {}

    public function cancelBooking(Request $request, int|string $id)
    {
        $realId  = hashid_decode(HashIdService::BOOKING_ATTEMPT, $id) ?? $id;
        $attempt = BookingAttempt::findOrFail($realId);

        if (!in_array($attempt->status, ['committed', 'booking_confirmed'], true)) {
            return response()->json([
                'status'  => false,
                'message' => 'Only committed (held) bookings can be cancelled. Current status: ' . $attempt->status,
            ], 422);
        }

        try {
            $result = $this->cancelService->cancel($attempt, optional(auth()->user())->id);

            return response()->json([
                'status'       => true,
                'message'      => 'Booking cancelled successfully.',
                'pnr'          => $result['pnr'],
                'cancelled_at' => $result['cancelled_at'],
            ]);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() ?: 'Cancellation failed. Please try again.',
            ], 500);
        }
    }
}
