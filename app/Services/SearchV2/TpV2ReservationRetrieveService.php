<?php

namespace App\Services\SearchV2;

use Exception;
use Throwable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use App\Models\BookingAttempt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Reads a live PNR without touching it. The commit response often carries the offer's fare
// validity (weeks out) rather than the airline's real ticketing deadline (usually same day), so
// the only trustworthy source for "Last Ticketing" is the reservation itself.
class TpV2ReservationRetrieveService
{
    public const SESSION_TYPE = 'retrieve_reservation';

    private const REQUEST_TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly TravelportTokenService $tokenService,
        private readonly BookingSessionLogger $sessionLogger,
    ) {}

    // Best-effort refresh used straight after commit. Booking already succeeded by then, so a
    // failure here is logged and swallowed rather than surfaced to the agent.
    public function syncTicketingTimeLimit(BookingAttempt $attempt, int|string|null $userId = null): ?CarbonInterface
    {
        try {
            $body  = $this->retrieve($attempt, $userId);
            $limit = $this->extractTicketingTimeLimit($body);

            if ($limit === null) {
                return null;
            }

            BookingAttempt::query()
                ->whereKey($attempt->getKey())
                ->update(['ticketing_time_limit' => $limit]);
            $attempt->setAttribute('ticketing_time_limit', $limit);

            return $limit;
        } catch (Throwable $e) {
            Log::warning('TpV2ReservationRetrieveService::syncTicketingTimeLimit failed', [
                'attempt_id' => $attempt->id,
                'pnr'        => $attempt->gds_pnr,
                'error'      => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function retrieve(BookingAttempt $attempt, int|string|null $userId = null): array
    {
        $pnr = trim((string) $attempt->gds_pnr);
        if ($pnr === '') {
            throw new Exception('PNR (locator) is missing on this booking — cannot retrieve reservation.');
        }

        $url = $this->buildUrl('air/book/reservation/reservations/' . urlencode($pnr));

        // A GET has no body, so record what was asked for — otherwise the timeline row would have
        // nothing to download on the request side
        $request = ['method' => 'GET', 'url' => $url, 'Locator' => $pnr];

        $response   = null;
        $httpStatus = null;

        try {
            $token    = $this->tokenService->getAccessToken();
            $response = $this->send($url, $token);

            if ($response->status() === 401) {
                $token    = $this->tokenService->getAccessToken(forceRefresh: true);
                $response = $this->send($url, $token);
            }

            $httpStatus = $response->status();
            $body       = $this->decodeBody($response);
            $this->assertNoTpError($body);

            if (!$response->successful()) {
                throw new Exception("Reservation retrieve failed. HTTP {$httpStatus}.");
            }

            $this->logSession($attempt, $userId, $request, $body, $pnr, 'success', $httpStatus);

            return $body;
        } catch (Exception $e) {
            $this->logSession(
                $attempt,
                $userId,
                $request,
                $response ? $this->decodeBody($response) : [],
                $pnr,
                'error',
                $httpStatus,
                $e->getMessage()
            );

            Log::error('TpV2ReservationRetrieveService::retrieve failed', [
                'attempt_id' => $attempt->id,
                'pnr'        => $pnr,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // PaymentTimeLimit is absent once a booking is ticketed, so a null result is normal
    public function extractTicketingTimeLimit(array $body): ?CarbonInterface
    {
        $reservation = $body['ReservationResponse']['Reservation'] ?? [];
        if (!is_array($reservation) || empty($reservation)) {
            return null;
        }

        $earliest = null;

        // Offer/TermsAndConditionsFull nesting varies by carrier and trip shape, so collect every
        // PaymentTimeLimit in the subtree and honour the tightest one
        foreach ($this->collectValuesByKey($reservation, 'PaymentTimeLimit') as $raw) {
            try {
                // Travelport sends a UTC instant ("2026-07-22T17:59:00Z"). Eloquent writes a
                // Carbon's own wall-clock and reads it back as app-timezone, so a UTC-tz Carbon
                // would land in the column 6 hours early.
                $parsed = Carbon::parse($raw)->setTimezone(config('app.timezone'));
            } catch (Throwable) {
                continue;
            }

            if ($earliest === null || $parsed->lt($earliest)) {
                $earliest = $parsed;
            }
        }

        return $earliest;
    }

    private function collectValuesByKey(array $node, string $needle): array
    {
        $found = [];

        foreach ($node as $key => $value) {
            if ($key === $needle) {
                $scalar = is_array($value) ? ($value['value'] ?? null) : $value;
                if (is_string($scalar) && trim($scalar) !== '') {
                    $found[] = $scalar;
                }
                continue;
            }

            if (is_array($value)) {
                $found = array_merge($found, $this->collectValuesByKey($value, $needle));
            }
        }

        return $found;
    }

    private function send(string $url, string $token)
    {
        return Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->withToken($token)
            ->withHeaders($this->buildHeaders())
            ->get($url);
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
        $errors = $body['ReservationResponse']['Result']['Error']
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
            'session_type'         => self::SESSION_TYPE,
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
