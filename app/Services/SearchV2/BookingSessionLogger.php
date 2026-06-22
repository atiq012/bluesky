<?php

namespace App\Services\SearchV2;

use App\Models\BookingSession;
use App\Services\SearchV2\Concerns\PersistsTravelportResponseFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BookingSessionLogger
{
    use PersistsTravelportResponseFile;

    public function create(array $attributes, ?array $responseBody = null, ?string $requestId = null): BookingSession
    {
        $responsePath  = null;
        $responseBytes = null;
        $extras        = [];

        if ($responseBody !== null && $responseBody !== []) {
            $extras['response_payload'] = $responseBody;

            try {
                $requestId = $requestId ?? (string) Str::uuid();
                [$responsePath, $responseBytes] = $this->persistTravelportResponseFile('booking', $responseBody, $requestId);
            } catch (Throwable $e) {
                Log::warning('BookingSessionLogger: response file not saved', [
                    'error'        => $e->getMessage(),
                    'session_type' => $attributes['session_type'] ?? null,
                ]);
            }
        }

        $session = BookingSession::create(array_merge($attributes, $extras, [
            'response_file_path'  => $responsePath,
            'response_size_bytes' => $responseBytes,
        ]));

        if (!empty($attributes['booking_attempt_id'])) {
            BookingAttemptOutcome::recordFromSession(
                (int) $attributes['booking_attempt_id'],
                (string) ($attributes['session_type'] ?? 'session'),
                (string) ($attributes['status'] ?? 'success'),
                $attributes['error_message'] ?? null,
                $attributes['updated_by'] ?? $attributes['created_by'] ?? null
            );
        }

        return $session;
    }
}
