<?php

namespace App\Services\SearchV2;

use App\Models\BookingAttempt;
use App\Models\BookingSession;

class BookingSnapshotRecorder
{
    public function __construct(
        private readonly BookingSessionLogger $sessionLogger
    ) {}

    public function recordPreCommit(BookingAttempt $attempt, array $snapshot, int|string|null $userId = null): BookingSession
    {
        $snapshot['snapshot_type'] = 'pre_commit';

        return $this->record($attempt, 'pre_commit_snapshot', $snapshot, $userId);
    }

    public function recordPostCommit(BookingAttempt $attempt, array $snapshot, int|string|null $userId = null): BookingSession
    {
        $snapshot['snapshot_type'] = 'post_commit';

        return $this->record($attempt, 'post_commit_snapshot', $snapshot, $userId);
    }

    private function record(BookingAttempt $attempt, string $sessionType, array $snapshot, int|string|null $userId): BookingSession
    {
        BookingSession::query()
            ->where('booking_attempt_id', $attempt->id)
            ->where('session_type', $sessionType)
            ->delete();

        return $this->sessionLogger->create([
            'user_id'              => $userId,
            'booking_attempt_id'   => $attempt->id,
            'booking_price_log_id' => $attempt->booking_price_log_id,
            'session_type'         => $sessionType,
            'request_payload'      => null,
            'identifier_value'     => $attempt->workbench_identifier,
            'provider'             => 'internal',
            'status'               => 'success',
            'http_status'          => 200,
            'created_by'           => $userId,
            'updated_by'           => $userId,
        ], $snapshot);
    }
}
