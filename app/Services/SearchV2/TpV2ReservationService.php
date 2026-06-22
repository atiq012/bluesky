<?php

namespace App\Services\SearchV2;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TpV2ReservationService
{
    public function __construct(
        private readonly TravelportTokenService $tokenService,
        private readonly BookingAttemptService $bookingAttemptService,
        private readonly BookingSessionLogger $sessionLogger
    ) {}

    public function initiateWorkbench(array $params, int|string|null $userId = null): array
    {
        $priceLogId = (int) ($params['price_log_id'] ?? 0);
        if ($priceLogId <= 0) {
            throw new Exception('price_log_id is required to start a booking.');
        }

        $attempt = $this->bookingAttemptService->createForWorkbench(
            $priceLogId,
            $params['selection_json'] ?? null,
            $userId
        );

        $url     = $this->buildUrl();
        $payload = ['@type' => 'ReservationID'];

        $response   = null;
        $httpStatus = null;

        try {
            $token    = $this->tokenService->getAccessToken();
            $response = $this->executeRequest($url, $payload, $token);

            if ($response->status() === 401) {
                $token    = $this->tokenService->getAccessToken(forceRefresh: true);
                $response = $this->executeRequest($url, $payload, $token);
            }

            $httpStatus = $response->status();

            if (!$response->successful()) {
                throw new Exception('Travelport workbench initiation failed. HTTP ' . $httpStatus);
            }

            $body       = $response->json();
            $identifier = $body['ReservationResponse']['Reservation']['Identifier'] ?? null;

            if (empty($identifier['value'])) {
                throw new Exception('Workbench identifier missing in Travelport response.');
            }

            $session = $this->sessionLogger->create([
                'user_id'              => $userId,
                'booking_attempt_id'   => $attempt->id,
                'booking_price_log_id' => $attempt->booking_price_log_id,
                'session_type'         => 'reservation_workbench',
                'request_payload'      => $payload,
                'response_payload'     => $body,
                'identifier_authority' => $identifier['authority'] ?? null,
                'identifier_value'     => $identifier['value'],
                'provider'             => 'travelport_v2',
                'status'               => 'success',
                'http_status'          => $httpStatus,
                'created_by'           => $userId,
                'updated_by'           => $userId,
            ], $body);

            $this->bookingAttemptService->attachWorkbenchSession(
                $attempt,
                $session->id,
                $identifier['value']
            );

            return [
                'workbench_identifier' => $identifier['value'],
                'session_id'           => $session->id,
                'booking_attempt_id'   => $attempt->id,
                'travelport_response'  => $body,
            ];
        } catch (Exception $e) {
            Log::error('TpV2ReservationService::initiateWorkbench failed', [
                'error'       => $e->getMessage(),
                'http_status' => $httpStatus,
            ]);

            $this->sessionLogger->create([
                'user_id'              => $userId,
                'booking_attempt_id'   => $attempt->id,
                'booking_price_log_id' => $attempt->booking_price_log_id,
                'session_type'         => 'reservation_workbench',
                'request_payload'      => $payload,
                'response_payload'     => $response?->json(),
                'provider'             => 'travelport_v2',
                'status'               => 'error',
                'http_status'          => $httpStatus,
                'error_message'        => $e->getMessage(),
                'created_by'           => $userId,
                'updated_by'           => $userId,
            ], $response?->json() ?? []);

            throw $e;
        }
    }

    private function buildUrl(): string
    {
        $base    = rtrim((string) config('services.travelport_v2.base_url'), '/');
        $version = trim((string) config('services.travelport_v2.version'));

        return "{$base}/{$version}/air/book/session/reservationworkbench";
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

    private function executeRequest(string $url, array $payload, string $token)
    {
        return Http::withToken($token)
            ->withHeaders($this->buildHeaders())
            ->post($url, $payload);
    }
}
