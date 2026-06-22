<?php

namespace App\Services\SearchV2;

use App\Models\BookingActivityLog;
use App\Models\BookingAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookingActivityLogger
{
    public const ACTION_PROCEED_TO_BOOKING = 'proceed_to_booking';
    public const ACTION_TRAVELER_ADDED     = 'traveler_added';
    public const ACTION_SSR_ADDED          = 'ssr_added';
    public const ACTION_ANCILLARY_ADDED    = 'ancillary_added';
    public const ACTION_BOOKING_CONFIRMED  = 'booking_confirmed';
    public const ACTION_BOOKING_CANCELLED  = 'booking_cancelled';
    public const ACTION_TICKET_ISSUED      = 'ticket_issued';
    public const ACTION_TICKET_VOIDED      = 'ticket_voided';

    public function log(
        BookingAttempt $attempt,
        string $actionType,
        int|string|null $userId,
        array $metadata = [],
        ?string $statusBefore = null,
        ?string $statusAfter = null,
    ): void {
        try {
            $userName = null;
            if ($userId) {
                $userName = User::find($userId)?->name;
            }

            BookingActivityLog::create([
                'booking_attempt_id' => $attempt->id,
                'action_type'        => $actionType,
                'user_id'            => $userId,
                'user_name'          => $userName,
                'status_before'      => $statusBefore,
                'status_after'       => $statusAfter,
                'metadata'           => $metadata ?: null,
            ]);
        } catch (Throwable $e) {
            Log::warning('BookingActivityLogger::log failed', [
                'attempt_id'  => $attempt->id,
                'action_type' => $actionType,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
