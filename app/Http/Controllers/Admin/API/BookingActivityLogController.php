<?php

namespace App\Http\Controllers\Admin\API;

use App\Http\Controllers\Controller;
use App\Models\BookingActivityLog;
use App\Models\BookingAttempt;
use App\Services\HashIdService;

class BookingActivityLogController extends Controller
{
    public function index(string $id)
    {
        $realId  = hashid_decode(HashIdService::BOOKING_ATTEMPT, $id) ?? $id;
        $attempt = BookingAttempt::findOrFail($realId);

        $logs = BookingActivityLog::query()
            ->where('booking_attempt_id', $attempt->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($log) => [
                'id'            => $log->id,
                'action_type'   => $log->action_type,
                'user_id'       => $log->user_id,
                'user_name'     => $log->user_name,
                'status_before' => $log->status_before,
                'status_after'  => $log->status_after,
                'metadata'      => $log->metadata,
                'created_at'    => $log->created_at?->toIso8601String(),
                'created_at_fmt' => $log->created_at?->format('d-M-Y h:i A'),
            ]);

        return response()->json([
            'status' => 'success',
            'data'   => $logs,
        ]);
    }
}
