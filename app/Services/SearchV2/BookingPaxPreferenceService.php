<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingPax;
use App\Models\BookingSession;

class BookingPaxPreferenceService
{
    public function syncForWorkbench(
        string $workbenchId,
        int $sessionId,
        array $travelers,
        int|string|null $userId = null
    ): int {
        $workbenchSession = BookingSession::query()
            ->where('id', $sessionId)
            ->where('session_type', 'reservation_workbench')
            ->where('identifier_value', $workbenchId)
            ->where('status', 'success')
            ->first();

        if (!$workbenchSession) {
            throw new Exception('Invalid or expired workbench session.');
        }

        $attemptId = (int) $workbenchSession->booking_attempt_id;
        $updated   = 0;

        foreach ($travelers as $traveler) {
            $sequence = (int) ($traveler['sequence'] ?? 0);
            if ($sequence <= 0) {
                continue;
            }

            $pax = BookingPax::query()
                ->where('booking_attempt_id', $attemptId)
                ->where('sequence', $sequence)
                ->where('status', 'success')
                ->first();

            if (!$pax) {
                continue;
            }

            $pax->update([
                'meal_preference'   => isset($traveler['meal_preference']) && $traveler['meal_preference'] !== ''
                    ? (string) $traveler['meal_preference']
                    : null,
                'wheelchair_needed' => (bool) ($traveler['wheelchair_needed'] ?? false),
                'updated_by'        => $userId,
            ]);

            $updated++;
        }

        return $updated;
    }
}
