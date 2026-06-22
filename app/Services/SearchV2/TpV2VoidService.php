<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingAttempt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TpV2VoidService
{
    public function __construct(
        private readonly TravelportTokenService $tokenService,
        private readonly BookingSessionLogger $sessionLogger,
        private readonly BookingActivityLogger $activityLogger,
    ) {}

    public function void(BookingAttempt $attempt, array $ticketNumbers, int|string|null $userId = null): array
    {
        $pnr = trim((string) $attempt->gds_pnr);
        if ($pnr === '') {
            throw new Exception('PNR (locator) is missing on this booking — cannot void.');
        }

        if (empty($ticketNumbers)) {
            throw new Exception('At least one ticket number must be selected for void.');
        }

        $token      = $this->tokenService->getAccessToken();
        $url        = $this->buildUrl('documents/void');
        $payload    = $this->buildPayload($pnr, $ticketNumbers);
        $response   = null;
        $httpStatus = null;

        try {
            $response   = $this->postJson($url, $token, $payload);
            $httpStatus = $response->status();

            if ($httpStatus === 401) {
                $token      = $this->tokenService->getAccessToken(forceRefresh: true);
                $response   = $this->postJson($url, $token, $payload);
                $httpStatus = $response->status();
            }

            $body = $this->decodeBody($response);
            $this->assertNoTpError($body);

            if (!$response->successful()) {
                throw new Exception("Ticket void failed. HTTP {$httpStatus}.");
            }

            $this->logSession($attempt, $userId, 'ticket_void', $payload, $body, $pnr, 'success', $httpStatus);

            $statusBefore = $attempt->status;
            $now = now();
            $attempt->update([
                'status'     => 'voided',
                'voided_at'  => $now,
                'updated_by' => $userId,
            ]);

            $this->activityLogger->log(
                $attempt->fresh(),
                BookingActivityLogger::ACTION_TICKET_VOIDED,
                $userId,
                ['voided_tickets' => $ticketNumbers, 'pnr' => $pnr],
                $statusBefore,
                'voided',
            );

            $docStatuses = data_get($body, 'DocumentVoidListResponse.documentStatus', []);

            return [
                'pnr'             => $pnr,
                'voided_at'       => $now->toIso8601String(),
                'voided_tickets'  => $ticketNumbers,
                'document_status' => $docStatuses,
                'response'        => $body,
            ];
        } catch (Exception $e) {
            $this->logSession($attempt, $userId, 'ticket_void', $payload, $response ? $this->decodeBody($response) : [], $pnr, 'error', $httpStatus, $e->getMessage());
            Log::error('TpV2VoidService::void failed', ['attempt_id' => $attempt->id, 'pnr' => $pnr, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function buildPayload(string $pnr, array $ticketNumbers): array
    {
        return [
            'locator' => [
                'value' => $pnr,
            ],
            'documentVoid' => [
                [
                    'documentType' => 'Ticket',
                    'number'       => array_values($ticketNumbers),
                ],
            ],
        ];
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

    private function postJson(string $url, string $token, array $payload)
    {
        return Http::withToken($token)
            ->withHeaders($this->buildHeaders())
            ->post($url, $payload);
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
        $errors = $body['DocumentVoidListResponse']['Errors']['Error']
            ?? $body['DocumentVoidListResponse']['Result']['Error']
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
