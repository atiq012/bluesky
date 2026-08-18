<?php

namespace App\Services\SearchV2;

use Exception;
use Throwable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use App\Models\BookingAttempt;
use App\Models\BookingSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SearchV2\BookingActivityLogger;

class TpV2TicketService
{
    // Signalled to the controller so a lost race maps to HTTP 409 instead of a 500
    public const ERROR_IN_PROGRESS = 409;

    private const STATUS_TICKETABLE = ['committed', 'booking_confirmed'];

    // Set explicitly rather than inherited from Laravel's default, because the ticketing lease is
    // derived from it: 5 Travelport calls x 2 (one 401 retry each) x 30s = a 5 minute ceiling for
    // one issue() run. Raising this without raising ticket_lease_minutes lets a live run have its
    // claim stolen by a second request — see claimTicketing().
    private const REQUEST_TIMEOUT_SECONDS = 30;

    // Held for the duration of one issue() call so every step shares the same (refreshable) token
    private ?string $token = null;

    public function __construct(
        private readonly TravelportTokenService $tokenService,
        private readonly BookingSessionLogger $sessionLogger,
        private readonly BookingActivityLogger $activityLogger,
    ) {}

    public function issue(BookingAttempt $attempt, int|string|null $userId = null): array
    {
        // Ticketing moves money and cannot be undone by a retry — an already-ticketed attempt
        // returns what was issued rather than issuing a second set of documents
        if ($attempt->status === 'ticketed') {
            return $this->existingTicketResult($attempt);
        }

        $pnr = trim((string) $attempt->gds_pnr);
        if ($pnr === '') {
            throw new Exception('PNR (locator) is missing on this booking — cannot build post-commit workbench.');
        }

        // A fare with unconfirmed SSRs can be repriced by the airline at issuance, so stop early
        $this->assertNoSsrPending($attempt);

        if (! $this->claimTicketing($attempt)) {
            return $this->resolveLostClaim($attempt);
        }

        $workbenchId = null;
        $issued      = false;

        try {
            $this->token = $this->tokenService->getAccessToken();

            // Step 1: Re-open workbench from GDS PNR locator
            [$workbenchId, $reservationBody] = $this->buildPostCommitWorkbench($attempt, $pnr, $userId);

            // Step 1b: Past the airline deadline the fare is gone — fail before any payment is attached
            $this->assertTicketingTimeLimit($attempt, $reservationBody);

            // Step 2: Declare commission per-passenger (Galileo requires this before commit, even at 0%)
            $this->addDocumentOverrideCommission($attempt, $workbenchId, $userId);

            // Step 3: Use existing FOP if present; otherwise add one
            [$fopIdentifierValue, $fopId] = $this->resolveFormOfPayment($attempt, $workbenchId, $reservationBody, $userId);

            // Step 4: Add Payment (links FOP + offer + amount → triggers ticket issuance on commit)
            $this->addPayment($attempt, $workbenchId, $fopIdentifierValue, $fopId, $reservationBody, $userId);

            // Step 5: Commit workbench → issue eTickets
            $ticketNumbers = $this->commitWorkbench($attempt, $workbenchId, $userId);
            $issued        = true;

            $statusBefore = $attempt->status;
            $now = now();
            $attempt->update([
                'ticket_numbers' => $ticketNumbers,
                'ticketed_at'    => $now,
                'status'         => 'ticketed',
                'updated_by'     => $userId,
            ]);

            $this->activityLogger->log(
                $attempt->fresh(),
                BookingActivityLogger::ACTION_TICKET_ISSUED,
                $userId,
                ['ticket_numbers' => $ticketNumbers],
                $statusBefore,
                'ticketed',
            );

            return [
                'ticket_numbers' => $ticketNumbers,
                'ticketed_at'    => $now->toIso8601String(),
            ];
        } catch (Throwable $e) {
            // An abandoned workbench holds a GDS session slot until it times out — hand it back.
            // Never after a successful commit: that workbench now owns the issued tickets.
            if ($workbenchId !== null && ! $issued) {
                $this->discardWorkbench($attempt, $workbenchId, $userId);
            }

            throw $e;
        } finally {
            $this->releaseTicketing($attempt);
            $this->token = null;
        }
    }

    // One atomic UPDATE decides the winner, so concurrent requests (double-click, two tabs,
    // an HTTP retry) can never both walk into the issuance sequence
    private function claimTicketing(BookingAttempt $attempt): bool
    {
        $staleAfter = now()->subMinutes(
            max(1, (int) config('services.travelport_v2.ticket_lease_minutes', 5))
        );

        return BookingAttempt::query()
            ->whereKey($attempt->getKey())
            ->whereIn('status', self::STATUS_TICKETABLE)
            ->where(function ($query) use ($staleAfter) {
                // A lease older than the window belonged to a crashed run and is safe to take over
                $query->whereNull('ticketing_locked_at')
                    ->orWhere('ticketing_locked_at', '<', $staleAfter);
            })
            ->update(['ticketing_locked_at' => now()]) === 1;
    }

    private function releaseTicketing(BookingAttempt $attempt): void
    {
        BookingAttempt::query()
            ->whereKey($attempt->getKey())
            ->update(['ticketing_locked_at' => null]);
    }

    // Claim failed for one of two reasons: someone already finished, or someone is mid-flight
    private function resolveLostClaim(BookingAttempt $attempt): array
    {
        $attempt->refresh();

        if ($attempt->status === 'ticketed') {
            return $this->existingTicketResult($attempt);
        }

        if ($attempt->ticketing_locked_at !== null) {
            throw new Exception(
                'Ticketing is already in progress for this booking. Wait for it to finish before retrying.',
                self::ERROR_IN_PROGRESS
            );
        }

        throw new Exception(
            'Ticket can only be issued for a committed booking. Current status: ' . $attempt->status,
            self::ERROR_IN_PROGRESS
        );
    }

    private function existingTicketResult(BookingAttempt $attempt): array
    {
        return [
            'ticket_numbers' => (array) ($attempt->ticket_numbers ?? []),
            'ticketed_at'    => optional($attempt->ticketed_at)->toIso8601String(),
            'already_issued' => true,
        ];
    }

    private function assertNoSsrPending(BookingAttempt $attempt): void
    {
        if (! filter_var(config('services.travelport_v2.ticket_ssr_check', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $commitSession = BookingSession::query()
            ->where('booking_attempt_id', $attempt->id)
            ->where('session_type', 'commit')
            ->where('status', 'success')
            ->orderByDesc('id')
            ->first();

        if (!$commitSession) {
            return;
        }

        $body = $commitSession->response_payload;
        if (!is_array($body)) {
            $body = json_decode((string) $body, true) ?? [];
        }

        $services = $body['ReservationResponse']['Reservation']['SpecialService'] ?? [];
        $pending  = [];

        foreach ($services as $svc) {
            $rawStatus = $svc['Status'] ?? '';
            $statusStr = is_array($rawStatus)
                ? (string) ($rawStatus['value'] ?? $rawStatus[0] ?? '')
                : (string) $rawStatus;
            if (strtolower($statusStr) === 'pending') {
                $pending[] = $svc['SSRCode'] ?? $svc['ssrCode'] ?? 'UNKNOWN';
            }
        }

        if (!empty($pending)) {
            throw new Exception(
                'Cannot issue ticket — SSR(s) still Pending after commit: '
                . implode(', ', $pending)
                . '. Resolve with airline before ticketing.'
            );
        }
    }

    // TermsAndConditionsFull/PaymentTimeLimit is the carrier's ticketing deadline; it is absent
    // on already-ticketed bookings, so a missing value is never treated as a failure
    private function assertTicketingTimeLimit(BookingAttempt $attempt, array $reservationBody): void
    {
        $limit = $this->extractTicketingTimeLimit($reservationBody);

        if ($limit === null) {
            return;
        }

        BookingAttempt::query()
            ->whereKey($attempt->getKey())
            ->update(['ticketing_time_limit' => $limit]);
        $attempt->setAttribute('ticketing_time_limit', $limit);

        if (! filter_var(config('services.travelport_v2.ticket_ttl_check', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if ($limit->isPast()) {
            throw new Exception(
                'Ticketing time limit expired on ' . $limit->format('d M Y, H:i')
                . ' — the airline may have dropped the fare. Re-confirm the booking before issuing.'
            );
        }
    }

    private function extractTicketingTimeLimit(array $reservationBody): ?CarbonInterface
    {
        $reservation = $reservationBody['Reservation'] ?? [];
        if (!is_array($reservation) || empty($reservation)) {
            return null;
        }

        $earliest = null;

        // Offer/TermsAndConditionsFull nesting varies by carrier and trip shape, so collect every
        // PaymentTimeLimit in the subtree and honour the tightest one
        foreach ($this->collectValuesByKey($reservation, 'PaymentTimeLimit') as $raw) {
            try {
                // Travelport sends a UTC instant ("2026-07-31T17:59:00Z"). Eloquent writes a
                // Carbon's own wall-clock to the column and reads it back as app-timezone, so a
                // UTC-tz Carbon would land in the DB 6 hours early.
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

    private function buildPostCommitWorkbench(
        BookingAttempt $attempt,
        string $pnr,
        int|string|null $userId
    ): array {
        $url      = $this->buildUrl('air/book/session/reservationworkbench/buildfromlocator') . '?Locator=' . urlencode($pnr);
        $response = null;
        $httpStatus = null;

        try {
            $response   = $this->send('post', $url);
            $httpStatus = $response->status();

            $body = $this->decodeBody($response);
            $this->assertNoTpError($body);

            if (!$response->successful()) {
                throw new Exception("Post-commit workbench build failed. HTTP {$httpStatus}.");
            }

            $workbenchId = $body['ReservationResponse']['Identifier']['value'] ?? null;

            if (empty($workbenchId)) {
                throw new Exception('Workbench identifier missing in Travelport buildfromlocator response.');
            }

            $this->logSession($attempt, $userId, 'ticket_workbench', ['Locator' => $pnr], $body, $workbenchId, 'success', $httpStatus);

            return [(string) $workbenchId, $body['ReservationResponse'] ?? []];
        } catch (Exception $e) {
            $this->logSession($attempt, $userId, 'ticket_workbench', ['Locator' => $pnr], $response ? $this->decodeBody($response) : [], null, 'error', $httpStatus, $e->getMessage());
            Log::error('TpV2TicketService::buildPostCommitWorkbench failed', ['attempt_id' => $attempt->id, 'pnr' => $pnr, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function addDocumentOverrideCommission(
        BookingAttempt $attempt,
        string $workbenchId,
        int|string|null $userId
    ): void {
        $url     = $this->buildUrl("air/book/documentoverride/Reservation/{$workbenchId}/documentoverrides");
        $payload = [
            'DocumentOverrides' => [
                'id'                   => 'documentOverrides_1',
                'DocumentOverridesRef' => 'documentOverrides_1',
                'Commissions'          => [
                    [
                        'Commission' => [
                            '@type'   => 'CommissionPercent',
                            'Percent' => 0,
                        ],
                    ],
                ],
            ],
        ];
        $response   = null;
        $httpStatus = null;

        try {
            $response   = $this->send('post', $url, $payload);
            $httpStatus = $response->status();

            $body = $this->decodeBody($response);
            $this->assertNoTpError($body);

            if (!$response->successful()) {
                throw new Exception("Document override commission failed. HTTP {$httpStatus}.");
            }

            $this->logSession($attempt, $userId, 'ticket_doc_override', $payload, $body, $workbenchId, 'success', $httpStatus);
        } catch (Exception $e) {
            $this->logSession($attempt, $userId, 'ticket_doc_override', $payload, $response ? $this->decodeBody($response) : [], $workbenchId, 'error', $httpStatus, $e->getMessage());
            Log::error('TpV2TicketService::addDocumentOverrideCommission failed', ['attempt_id' => $attempt->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    // Returns [fopIdentifierValue, fopId] — reuses existing FOP to avoid duplicate error
    private function resolveFormOfPayment(
        BookingAttempt $attempt,
        string $workbenchId,
        array $reservationBody,
        int|string|null $userId
    ): array {
        $existingFops = $reservationBody['Reservation']['FormOfPayment'] ?? [];
        $existing     = $existingFops[0] ?? null;

        if ($existing && !empty($existing['Identifier']['value'])) {
            return [
                (string) $existing['Identifier']['value'],
                (string) ($existing['id'] ?? 'formOfPayment_1'),
            ];
        }

        return $this->addFormOfPayment($attempt, $workbenchId, $userId);
    }

    private function addFormOfPayment(
        BookingAttempt $attempt,
        string $workbenchId,
        int|string|null $userId
    ): array {
        $url        = $this->buildUrl("air/payment/reservationworkbench/{$workbenchId}/formofpayment");
        $payload    = $this->buildFopPayload();
        $response   = null;
        $httpStatus = null;

        try {
            $response   = $this->send('post', $url, $payload);
            $httpStatus = $response->status();

            $body = $this->decodeBody($response);
            $this->assertNoTpError($body);

            if (!$response->successful()) {
                throw new Exception("Add Form of Payment failed. HTTP {$httpStatus}.");
            }

            $fopIdentifierValue = (string) ($body['FormOfPaymentResponse']['FormOfPayment']['Identifier']['value'] ?? '');

            $this->logSession($attempt, $userId, 'ticket_fop', $payload, $body, $workbenchId, 'success', $httpStatus);

            return [$fopIdentifierValue, 'FOP_1'];
        } catch (Exception $e) {
            $this->logSession($attempt, $userId, 'ticket_fop', $payload, $response ? $this->decodeBody($response) : [], $workbenchId, 'error', $httpStatus, $e->getMessage());
            Log::error('TpV2TicketService::addFormOfPayment failed', ['attempt_id' => $attempt->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function addPayment(
        BookingAttempt $attempt,
        string $workbenchId,
        string $fopIdentifierValue,
        string $fopId,
        array $reservationResponse,
        int|string|null $userId
    ): void {
        $url = $this->buildUrl("air/paymentoffer/reservationworkbench/{$workbenchId}/payments");

        // TP strips Price from buildfromlocator response once payment was previously processed on PNR
        // → get authoritative price from priceLog instead
        $priceLog = $attempt->priceLog;

        // priceLog->total_price is agency_pays (net fare + our service_fee/AIT) — what the agent
        // owes BlueSky, not what Travelport quoted. Paying the GDS that figure fails with
        // "PAYMENT AMOUNT IS INVALID" as soon as a rule adds a nonzero markup. Travelport must be
        // paid its own net fare (fare_pricing.gross_fare); the markup settles in BlueSky's ledger,
        // never on the PNR. Fall back to total_price for attempts priced before this fix shipped
        // (no fare_pricing in their stored payload).
        $grossFare = data_get($priceLog->price_payload, 'mapped.fare_pricing.gross_fare');
        $totalPrice = $grossFare !== null ? (float) $grossFare : (float) ($priceLog->total_price ?? 0);

        // A zero Amount would be accepted here and fail deep inside commit with an opaque GDS
        // error, so refuse to send it
        if ($totalPrice <= 0) {
            throw new Exception(
                'Cannot issue ticket — no priced fare is attached to this booking '
                . '(price log missing or total is zero). Ticketing stopped before payment.'
            );
        }

        // Default matches TravelportPriceService, which stores BDT when the mapper returns nothing
        $currencyCode = strtoupper(trim((string) ($priceLog->currency ?? ''))) ?: 'BDT';
        $minorUnit    = $this->currencyMinorUnit($currencyCode);

        $payload    = $this->buildPaymentPayload($fopIdentifierValue, $fopId, $reservationResponse, $totalPrice, $currencyCode, $minorUnit);
        $response   = null;
        $httpStatus = null;

        try {
            $response   = $this->send('post', $url, $payload);
            $httpStatus = $response->status();

            $body = $this->decodeBody($response);
            $this->assertNoTpError($body);

            if (!$response->successful()) {
                throw new Exception("Add Payment failed. HTTP {$httpStatus}.");
            }

            $this->logSession($attempt, $userId, 'ticket_payment', $payload, $body, $workbenchId, 'success', $httpStatus);
        } catch (Exception $e) {
            $this->logSession($attempt, $userId, 'ticket_payment', $payload, $response ? $this->decodeBody($response) : [], $workbenchId, 'error', $httpStatus, $e->getMessage());
            Log::error('TpV2TicketService::addPayment failed', ['attempt_id' => $attempt->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function commitWorkbench(
        BookingAttempt $attempt,
        string $workbenchId,
        int|string|null $userId
    ): array {
        $url        = $this->buildUrl("air/book/reservation/reservations/{$workbenchId}");
        $payload    = ['ReservationQueryCommitReservation' => ['@type' => 'ReservationQueryCommitReservation']];
        $response   = null;
        $httpStatus = null;

        try {
            $response   = $this->send('post', $url, $payload);
            $httpStatus = $response->status();

            $body = $this->decodeBody($response);
            $this->assertNoTpError($body);

            if (!$response->successful()) {
                throw new Exception("Ticket commit failed. HTTP {$httpStatus}.");
            }

            $ticketNumbers = $this->extractTicketNumbers($body);

            $this->logSession($attempt, $userId, 'ticket_commit', $payload, $body, $workbenchId, 'success', $httpStatus);

            return $ticketNumbers;
        } catch (Exception $e) {
            $this->logSession($attempt, $userId, 'ticket_commit', $payload, $response ? $this->decodeBody($response) : [], $workbenchId, 'error', $httpStatus, $e->getMessage());
            Log::error('TpV2TicketService::commitWorkbench failed', ['attempt_id' => $attempt->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    // Best-effort cleanup of a workbench we will not commit — failures here are logged, never rethrown,
    // because the caller is already unwinding a real ticketing error
    private function discardWorkbench(
        BookingAttempt $attempt,
        string $workbenchId,
        int|string|null $userId
    ): void {
        $url = $this->buildUrl("air/book/session/reservationworkbench/{$workbenchId}");

        try {
            $response = $this->send('delete', $url);

            $this->logSession(
                $attempt,
                $userId,
                'ticket_wb_discard',
                ['workbenchId' => $workbenchId],
                $this->decodeBody($response),
                $workbenchId,
                $response->successful() ? 'success' : 'error',
                $response->status()
            );
        } catch (Throwable $e) {
            Log::warning('TpV2TicketService::discardWorkbench failed', [
                'attempt_id'   => $attempt->id,
                'workbench_id' => $workbenchId,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    private function buildFopPayload(): array
    {
        $fopMode = config('services.travelport_v2.fop_mode', 'cash');

        if ($fopMode === 'bsp') {
            $iataNumber = (string) config('services.travelport_v2.iata_number', '');
            return [
                'FormOfPaymentBSP' => [
                    '@type'    => 'FormOfPaymentBSP',
                    'id'       => 'FOP_1',
                    'Number'   => $iataNumber,
                    'TypeCode' => 'BSP',
                ],
            ];
        }

        return ['FormOfPaymentCash' => ['@type' => 'FormOfPaymentCash', 'id' => 'FOP_1']];
    }

    private function currencyMinorUnit(string $currency): int
    {
        // GDS/IATA zero-decimal currencies (no paise/cents subdivision used)
        $zeroDecimal = ['BDT', 'JPY', 'KRW', 'IDR', 'VND', 'MNT', 'PYG', 'UGX', 'RWF', 'GNF', 'BIF', 'CLP', 'DJF', 'ISK', 'KMF', 'XAF', 'XOF', 'XPF'];
        return in_array(strtoupper($currency), $zeroDecimal, true) ? 0 : 2;
    }

    private function buildPaymentPayload(
        string $fopIdentifierValue,
        string $fopId,
        array $reservationResponse,
        float $totalPrice,
        string $currencyCode,
        int $minorUnit
    ): array {
        $reservation = $reservationResponse['Reservation'] ?? [];
        $offers      = $reservation['Offer'] ?? [];
        $travelers   = $reservation['Traveler'] ?? [];

        $offerIdentifiers = [];
        foreach ($offers as $i => $offer) {
            $offerIdentifiers[] = [
                'id'         => $offer['id'] ?? "offer_{$i}",
                'offerRef'   => $offer['id'] ?? "offer_{$i}",
                'Identifier' => [
                    'authority' => $offer['Identifier']['authority'] ?? 'Travelport',
                    'value'     => $offer['Identifier']['value'] ?? '',
                ],
            ];
        }

        $travelerRefs = [];
        foreach ($travelers as $traveler) {
            $travelerRefs[] = [
                'passengerTypeCode' => $traveler['passengerTypeCode'] ?? 'ADT',
                'id'                => $traveler['id'] ?? '',
            ];
        }

        $payment = [
            'id'     => 'PAY_1',
            'Amount' => [
                'code'      => $currencyCode,
                'minorUnit' => $minorUnit,
                'value'     => $totalPrice,
            ],
            'FormOfPaymentIdentifier' => [
                'id'                => $fopId,
                'FormOfPaymentRef'  => $fopId,
                'Identifier'        => [
                    'authority' => 'Travelport',
                    'value'     => $fopIdentifierValue,
                ],
            ],
        ];

        if (!empty($offerIdentifiers)) {
            $payment['OfferIdentifier'] = $offerIdentifiers;
        }

        if (!empty($travelerRefs)) {
            $payment['TravelerIdentifierRef'] = $travelerRefs;
        }

        return ['Payment' => $payment];
    }

    private function extractTicketNumbers(array $body): array
    {
        $receipts = $body['ReservationResponse']['Reservation']['Receipt'] ?? [];
        if (!is_array($receipts)) {
            return [];
        }

        $numbers = [];
        foreach ($receipts as $receipt) {
            if (($receipt['@type'] ?? '') !== 'ReceiptPayment') {
                continue;
            }
            foreach ($receipt['Document'] ?? [] as $doc) {
                $number = $doc['Number'] ?? $doc['number'] ?? null;
                if (!empty($number)) {
                    $numbers[] = (string) $number;
                }
            }
        }

        return $numbers;
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

    // Every Travelport call goes through here so a token that expires mid-sequence is refreshed and
    // retried once, instead of failing after the workbench and FOP are already in place
    private function send(string $method, string $url, ?array $payload = null)
    {
        $response = $this->dispatch($method, $url, $payload);

        if ($response->status() === 401) {
            $this->token = $this->tokenService->getAccessToken(forceRefresh: true);
            $response    = $this->dispatch($method, $url, $payload);
        }

        return $response;
    }

    private function dispatch(string $method, string $url, ?array $payload)
    {
        $request = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->withToken((string) $this->token)
            ->withHeaders($this->buildHeaders());

        // buildfromlocator and discard take no body — sending [] causes TP/Tomcat HTTP 500
        return $payload === null
            ? $request->{$method}($url)
            : $request->{$method}($url, $payload);
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
            ?? $body['DocumentOverridesResponse']['Result']['Error']
            ?? $body['FormOfPaymentResponse']['Result']['Error']
            ?? $body['PaymentResponse']['Result']['Error']
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
