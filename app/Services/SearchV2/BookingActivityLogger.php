<?php

namespace App\Services\SearchV2;

use App\Jobs\BroadcastResourceEvent;
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

    // These change booking-list rows / summary cards — wizard steps do not
    private const LIST_BROADCAST_ACTIONS = [
        self::ACTION_BOOKING_CONFIRMED,
        self::ACTION_BOOKING_CANCELLED,
        self::ACTION_TICKET_ISSUED,
        self::ACTION_TICKET_VOIDED,
    ];

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

            $this->broadcastListChange($attempt, $actionType, $userId);
        } catch (Throwable $e) {
            Log::warning('BookingActivityLogger::log failed', [
                'attempt_id'  => $attempt->id,
                'action_type' => $actionType,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    // Agency confirm/ticket/cancel/void → BlueSky (and peer) booking list realtime refresh
    private function broadcastListChange(
        BookingAttempt $attempt,
        string $actionType,
        int|string|null $userId,
    ): void {
        if (!in_array($actionType, self::LIST_BROADCAST_ACTIONS, true)) {
            return;
        }

        // Confirm adds a list row; later status moves are updates
        $event = $actionType === self::ACTION_BOOKING_CONFIRMED ? 'Created' : 'Updated';

        BroadcastResourceEvent::dispatch('booking-attempts', $event, [
            'id'          => $attempt->id,
            'actor_id'    => $userId,
            'action_type' => $actionType,
            'status'      => $attempt->status,
        ]);
    }
}
