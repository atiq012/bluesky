<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingAttempt;
use App\Models\BookingSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SearchV2\BookingActivityLogger;

class TpV2CommitService
{
    public function __construct(
        private readonly TravelportTokenService $tokenService,
        private readonly BookingSessionLogger $sessionLogger,
        private readonly BookingSnapshotBuilder $snapshotBuilder,
        private readonly BookingSnapshotRecorder $snapshotRecorder,
        private readonly BookingActivityLogger $activityLogger,
    ) {}

    public function commit(BookingAttempt $attempt, int|string|null $userId = null): array
    {
        $workbenchId = trim((string) $attempt->workbench_identifier);
        if ($workbenchId === '') {
            throw new Exception('Workbench identifier is missing on this booking attempt.');
        }

        $workbenchSession = BookingSession::query()
            ->where('booking_attempt_id', $attempt->id)
            ->where('session_type', 'reservation_workbench')
            ->where('identifier_value', $workbenchId)
            ->where('status', 'success')
            ->orderByDesc('id')
            ->first();

        if (!$workbenchSession) {
            throw new Exception('No successful workbench session found for commit.');
        }

        $url     = $this->buildCommitUrl($workbenchId);
        $payload = $this->buildPayload();

        $response   = null;
        $httpStatus = null;

        try {
            $token = $this->tokenService->getAccessToken();
            $this->assertWorkbenchOpen($workbenchId, $token);

            $response = $this->executeRequest($url, $payload, $token);

            if ($response->status() === 401) {
                $token    = $this->tokenService->getAccessToken(forceRefresh: true);
                $response = $this->executeRequest($url, $payload, $token);
            }

            $httpStatus = $response->status();

            $body = $this->decodeResponseBody($response);

            if (!$response->successful() && !in_array($httpStatus, [200, 201], true)) {
                throw new Exception($this->formatHttpFailure($httpStatus, $body, $response->body()));
            }

            $tpError = $this->extractTravelportError($body);
            if ($tpError !== null) {
                throw $this->commitException($tpError);
            }

            $parsed = $this->parseCommitResponse($body);

            if (empty($parsed['gds_pnr'])) {
                throw new Exception('PNR locator missing in Travelport commit response.');
            }

            $airlineCodeRaw = data_get($attempt->priceLog?->price_payload, 'mapped.validating_airline');
            $airlineCode    = $airlineCodeRaw ? strtoupper((string) $airlineCodeRaw) : null;
            $airlineName    = $airlineCode
                ? DB::table('airline_logos')->where('code', $airlineCode)->value('name')
                : null;

            $attempt->update([
                'gds_pnr'                   => $parsed['gds_pnr'],
                'airline_pnr'               => $parsed['airline_pnr'],
                'airline_code'              => $airlineCode,
                'airline_name'              => $airlineName ?? $airlineCode,
                'cabin_class'               => $attempt->searchLog?->cabin_class,
                'reservation_identifier'    => $parsed['reservation_identifier'],
                'commit_error'              => null,
                'status'                    => 'committed',
                'updated_by'                => $userId,
            ]);

            $session = $this->sessionLogger->create([
                'user_id'              => $userId,
                'booking_attempt_id'   => $attempt->id,
                'booking_price_log_id' => $attempt->booking_price_log_id,
                'session_type'         => 'commit',
                'request_payload'      => $payload,
                'response_payload'     => $body,
                'identifier_value'     => $workbenchId,
                'provider'             => 'travelport_v2',
                'status'               => 'success',
                'http_status'          => $httpStatus,
                'created_by'           => $userId,
                'updated_by'           => $userId,
            ], $body);

            $attempt->update(['booking_commit_session_id' => $session->id]);

            $postCommitSnapshot = $this->snapshotBuilder->buildPostCommit($attempt->fresh(), $body, $parsed);
            $this->snapshotRecorder->recordPostCommit($attempt->fresh(), $postCommitSnapshot, $userId);
            $attempt->update(['post_commit_snapshot_json' => $postCommitSnapshot]);

            BookingAttemptOutcome::record(
                $attempt->fresh(),
                BookingAttemptOutcome::STAGE_COMMITTED,
                'commit',
                'success',
                null,
                $userId
            );

            $this->activityLogger->log(
                $attempt->fresh(),
                BookingActivityLogger::ACTION_BOOKING_CONFIRMED,
                $userId,
                ['gds_pnr' => $parsed['gds_pnr'], 'airline_pnr' => $parsed['airline_pnr']],
                'confirmed',
                'committed',
            );

            return [
                'pnr'                     => $parsed['gds_pnr'],
                'gds_pnr'                 => $parsed['gds_pnr'],
                'airline_pnr'             => $parsed['airline_pnr'],
                'reservation_identifier'  => $parsed['reservation_identifier'],
                'reservation_status'      => $parsed['reservation_status'],
                'session_id'              => $session->id,
                'travelport_response'     => $body,
            ];
        } catch (Exception $e) {
            Log::error('TpV2CommitService::commit failed', [
                'attempt_id'  => $attempt->id,
                'workbench'   => $workbenchId,
                'error'       => $e->getMessage(),
                'http_status' => $httpStatus,
            ]);

            $this->sessionLogger->create([
                'user_id'              => $userId,
                'booking_attempt_id'   => $attempt->id,
                'booking_price_log_id' => $attempt->booking_price_log_id,
                'session_type'         => 'commit',
                'request_payload'      => $payload ?? null,
                'response_payload'     => $response?->json(),
                'identifier_value'     => $workbenchId,
                'provider'             => 'travelport_v2',
                'status'               => 'error',
                'http_status'          => $httpStatus,
                'error_message'        => $e->getMessage(),
                'created_by'           => $userId,
                'updated_by'           => $userId,
            ], $response ? $this->decodeResponseBody($response) : []);

            $attempt->update([
                'commit_error' => $e->getMessage(),
                'updated_by'   => $userId,
            ]);

            throw $e;
        }
    }

    public static function isWorkbenchExpiredMessage(?string $message): bool
    {
        if ($message === null || $message === '') {
            return false;
        }

        $upper = strtoupper($message);

        return str_starts_with($message, 'WORKBENCH_EXPIRED:')
            || str_contains($upper, 'SESSION IDENTIFIER IS INVALID');
    }

    private function commitException(string $tpError): Exception
    {
        if ($this->isWorkbenchSessionInvalid($tpError)) {
            return new Exception(
                'WORKBENCH_EXPIRED: Travelport workbench session is closed or expired. '
                    . 'This booking cannot be committed again — start a new search and book again.'
            );
        }

        return new Exception('Travelport commit error: ' . $tpError);
    }

    private function isWorkbenchSessionInvalid(string $message): bool
    {
        return str_contains(strtoupper($message), 'SESSION IDENTIFIER IS INVALID');
    }

    private function assertWorkbenchOpen(string $workbenchId, string $token): void
    {
        $url      = $this->buildWorkbenchRetrieveUrl($workbenchId);
        $response = Http::withToken($token)
            ->withHeaders($this->buildHeaders())
            ->get($url);

        $body = $this->decodeResponseBody($response);
        $err  = $this->extractTravelportError($body);

        if ($err !== null && $this->isWorkbenchSessionInvalid($err)) {
            throw $this->commitException($err);
        }

        if (!$response->successful()) {
            throw new Exception(
                'WORKBENCH_EXPIRED: Cannot open Travelport workbench (HTTP ' . $response->status() . '). '
                    . 'Start a new booking from search.'
            );
        }
    }

    private function buildCommitUrl(string $workbenchId): string
    {
        $base    = rtrim((string) config('services.travelport_v2.base_url'), '/');
        $version = trim((string) config('services.travelport_v2.version'));

        return "{$base}/{$version}/air/book/reservation/reservations/{$workbenchId}";
    }

    private function buildWorkbenchRetrieveUrl(string $workbenchId): string
    {
        $base    = rtrim((string) config('services.travelport_v2.base_url'), '/');
        $version = trim((string) config('services.travelport_v2.version'));

        return "{$base}/{$version}/air/book/session/reservationworkbench/{$workbenchId}";
    }

    private function buildPayload(): array
    {
        // Travelport rejects JSON `[]`; requires object wrapper (empty body → Tomcat HTTP 500).
        return [
            'ReservationQueryCommitReservation' => [
                '@type' => 'ReservationQueryCommitReservation',
            ],
        ];
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

    private function decodeResponseBody($response): array
    {
        $json = $response->json();
        if (is_array($json)) {
            return $json;
        }

        $raw = trim((string) $response->body());
        if ($raw === '') {
            return [];
        }

        return ['_raw_body' => $raw];
    }

    private function formatHttpFailure(int $httpStatus, array $body, string $rawBody): string
    {
        $tpError = $this->extractTravelportError($body);
        if ($tpError !== null) {
            return 'Travelport commit error: ' . $tpError;
        }

        if (isset($body['_raw_body']) && str_contains($body['_raw_body'], '<html')) {
            return 'Travelport workbench commit failed. HTTP ' . $httpStatus
                . ' (invalid request body — ensure ReservationQueryCommitReservation wrapper is sent).';
        }

        $snippet = $rawBody !== '' ? ': ' . substr(preg_replace('/\s+/', ' ', $rawBody), 0, 200) : '';

        return 'Travelport workbench commit failed. HTTP ' . $httpStatus . $snippet;
    }

    private function extractTravelportError(array $body): ?string
    {
        $errors = $body['ReservationResponse']['Result']['Error']
            ?? $body['Result']['Error']
            ?? [];

        if (empty($errors)) {
            return null;
        }

        $first = $errors[0] ?? null;
        if (is_array($first)) {
            return $first['Message'] ?? $first['message'] ?? 'Unknown error';
        }

        return (string) $first;
    }

    private function parseCommitResponse(array $body): array
    {
        $reservation = $body['ReservationResponse']['Reservation'] ?? [];
        $receipts    = $reservation['Receipt'] ?? [];
        if (!is_array($receipts)) {
            $receipts = [];
        }

        $gdsPnr        = null;
        $airlinePnr    = null;
        $reservationId = $reservation['Identifier']['value'] ?? null;

        foreach ($receipts as $receipt) {
            $locator = $receipt['Confirmation']['Locator'] ?? null;
            if (!is_array($locator) || empty($locator['value'])) {
                continue;
            }

            $source = (string) ($locator['source'] ?? '');
            $val    = strtoupper(trim((string) $locator['value']));

            if ($source === '1G') {
                $gdsPnr = $val;
            } elseif ($airlinePnr === null) {
                $airlinePnr = $val;
            }
        }

        if ($gdsPnr === null) {
            $gdsPnr = $this->findLocatorInTree($reservation);
        }

        if ($reservationId === null) {
            foreach ($receipts as $receipt) {
                $id = $receipt['Identifier']['value'] ?? null;
                if (!empty($id)) {
                    $reservationId = $id;
                    break;
                }
            }
        }

        return [
            'gds_pnr'                => $gdsPnr,
            'airline_pnr'            => $airlinePnr,
            'reservation_identifier' => $reservationId,
            'reservation_status'     => $body['ReservationResponse']['reservationStatus'] ?? null,
        ];
    }

    private function findLocatorInTree(mixed $node): ?string
    {
        if (!is_array($node)) {
            return null;
        }

        if (isset($node['Locator']['value']) && is_string($node['Locator']['value'])) {
            $val = trim($node['Locator']['value']);
            if (strlen($val) >= 5 && strlen($val) <= 8) {
                return strtoupper($val);
            }
        }

        foreach ($node as $child) {
            $found = $this->findLocatorInTree($child);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
