<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingAttempt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SearchV2\BookingActivityLogger;

class TpV2CancelService
{
    public function __construct(
        private readonly TravelportTokenService $tokenService,
        private readonly BookingSessionLogger $sessionLogger,
        private readonly BookingActivityLogger $activityLogger,
    ) {}

    public function cancel(BookingAttempt $attempt, int|string|null $userId = null): array
    {
        $pnr = trim((string) $attempt->gds_pnr);
        if ($pnr === '') {
            throw new Exception('PNR (locator) is missing on this booking — cannot cancel.');
        }

        $token      = $this->tokenService->getAccessToken();
        $url        = $this->buildUrl("air/receipt/reservations/{$pnr}/receipts");
        $response   = null;
        $httpStatus = null;

        try {
            $response   = $this->postNoBody($url, $token);
            $httpStatus = $response->status();

            if ($httpStatus === 401) {
                $token      = $this->tokenService->getAccessToken(forceRefresh: true);
                $response   = $this->postNoBody($url, $token);
                $httpStatus = $response->status();
            }

            $body = $this->decodeBody($response);
            $this->assertNoTpError($body);

            if (!$response->successful()) {
                throw new Exception("Booking cancellation failed. HTTP {$httpStatus}.");
            }

            $this->logSession($attempt, $userId, 'cancel', [], $body, $pnr, 'success', $httpStatus);

            $statusBefore = $attempt->status;
            $now = now();
            $attempt->update([
                'status'       => 'cancelled',
                'cancelled_at' => $now,
                'updated_by'   => $userId,
            ]);

            $this->activityLogger->log(
                $attempt->fresh(),
                BookingActivityLogger::ACTION_BOOKING_CANCELLED,
                $userId,
                ['pnr' => $pnr],
                $statusBefore,
                'cancelled',
            );

            return [
                'pnr'          => $pnr,
                'cancelled_at' => $now->toIso8601String(),
                'response'     => $body,
            ];
        } catch (Exception $e) {
            $this->logSession($attempt, $userId, 'cancel', [], $response ? $this->decodeBody($response) : [], $pnr, 'error', $httpStatus, $e->getMessage());
            Log::error('TpV2CancelService::cancel failed', ['attempt_id' => $attempt->id, 'pnr' => $pnr, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function buildUrl(string $path): string
    {
        $base    = rtrim((string) config('services.travelport_v2.base_url'), '/');
        $version = trim((string) config('services.travelport_v2.version'));

        return "{$base}/{$version}/{$path}";
    }

    private function buildHeaders(): array
    {
        $version     = (string) config('services.travelport_v2.version', '11');
        $accessGroup = (string) config('services.travelport_v2.access_group', '');

        return [
            'Accept'                       => 'application/json',
            'Content-Type'                 => 'application/json',
            'Accept-Encoding'              => 'gzip, deflate',
            'XAUTH_TRAVELPORT_ACCESSGROUP' => $accessGroup,
            'Accept-Version'               => $version,
            'Content-Version'              => $version,
        ];
    }

    private function postNoBody(string $url, string $token)
    {
        return Http::withToken($token)
            ->withHeaders($this->buildHeaders())
            ->post($url);
    }

    private function decodeBody($response): array
    {
        $json = $response->json();
        if (is_array($json)) {
            return $json;
        }

        $raw = trim((string) $response->body());

        return $raw !== '' ? ['_raw_body' => $raw] : [];
    }

    private function assertNoTpError(array $body): void
    {
        $errors = $body['ReceiptListResponse']['Result']['Error']
            ?? $body['Result']['Error']
            ?? [];

        if (empty($errors)) {
            return;
        }

        $first   = $errors[0] ?? $errors;
        $message = is_array($first)
            ? ($first['Message'] ?? $first['message'] ?? 'Unknown Travelport error')
            : (string) $first;

        throw new Exception('Travelport error: ' . $message);
    }

    private function logSession(
        BookingAttempt $attempt,
        int|string|null $userId,
        string $sessionType,
        array $request,
        array $response,
        ?string $identifierValue,
        string $status,
        ?int $httpStatus,
        ?string $errorMessage = null
    ): void {
        $this->sessionLogger->create([
            'user_id'              => $userId,
            'booking_attempt_id'   => $attempt->id,
            'booking_price_log_id' => $attempt->booking_price_log_id,
            'session_type'         => $sessionType,
            'request_payload'      => $request,
            'response_payload'     => $response,
            'identifier_value'     => $identifierValue,
            'provider'             => 'travelport_v2',
            'status'               => $status,
            'http_status'          => $httpStatus,
            'error_message'        => $errorMessage,
            'created_by'           => $userId,
            'updated_by'           => $userId,
        ], $response);
    }
}
