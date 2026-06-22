<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingAttempt;
use App\Models\BookingSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SearchV2\BookingActivityLogger;

class TpV2TicketService
{
    public function __construct(
        private readonly TravelportTokenService $tokenService,
        private readonly BookingSessionLogger $sessionLogger,
        private readonly BookingActivityLogger $activityLogger,
    ) {}

    public function issue(BookingAttempt $attempt, int|string|null $userId = null): array
    {
        $pnr = trim((string) $attempt->gds_pnr);
        if ($pnr === '') {
            throw new Exception('PNR (locator) is missing on this booking — cannot build post-commit workbench.');
        }

        $token = $this->tokenService->getAccessToken();

        // Step 1: Re-open workbench from GDS PNR locator
        [$workbenchId, $reservationBody] = $this->buildPostCommitWorkbench($attempt, $pnr, $token, $userId);

        // Step 2: Declare commission per-passenger (Galileo requires this before commit, even at 0%)
        $this->addDocumentOverrideCommission($attempt, $workbenchId, $token, $userId);

        // Step 3: Use existing FOP if present; otherwise add one
        [$fopIdentifierValue, $fopId] = $this->resolveFormOfPayment($attempt, $workbenchId, $reservationBody, $token, $userId);

        // Step 4: Add Payment (links FOP + offer + amount → triggers ticket issuance on commit)
        $this->addPayment($attempt, $workbenchId, $fopIdentifierValue, $fopId, $reservationBody, $token, $userId);

        // Step 5: Commit workbench → issue eTickets
        $ticketNumbers = $this->commitWorkbench($attempt, $workbenchId, $token, $userId);

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
    }

    private function assertNoSsrPending(BookingAttempt $attempt): void
    {
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

    private function buildPostCommitWorkbench(
        BookingAttempt $attempt,
        string $pnr,
        string $token,
        int|string|null $userId
    ): array {
        $url      = $this->buildUrl('air/book/session/reservationworkbench/buildfromlocator') . '?Locator=' . urlencode($pnr);
        $response = null;
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
        string $token,
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
            $response   = $this->request('post', $url, $payload, $token);
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
        string $token,
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

        return $this->addFormOfPayment($attempt, $workbenchId, $token, $userId);
    }

    private function addFormOfPayment(
        BookingAttempt $attempt,
        string $workbenchId,
        string $token,
        int|string|null $userId
    ): array {
        $url        = $this->buildUrl("air/payment/reservationworkbench/{$workbenchId}/formofpayment");
        $payload    = $this->buildFopPayload();
        $response   = null;
        $httpStatus = null;

        try {
            $response   = $this->request('post', $url, $payload, $token);
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
        string $token,
        int|string|null $userId
    ): void {
        $url = $this->buildUrl("air/paymentoffer/reservationworkbench/{$workbenchId}/payments");

        // TP strips Price from buildfromlocator response once payment was previously processed on PNR
        // → get authoritative price from priceLog instead
        $priceLog     = $attempt->priceLog;
        $totalPrice   = (float) ($priceLog?->total_price ?? 0);
        $currencyCode = (string) ($priceLog?->currency ?? 'USD');
        $minorUnit    = $this->currencyMinorUnit($currencyCode);

        $payload    = $this->buildPaymentPayload($fopIdentifierValue, $fopId, $reservationResponse, $totalPrice, $currencyCode, $minorUnit);
        $response   = null;
        $httpStatus = null;

        try {
            $response   = $this->request('post', $url, $payload, $token);
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
        string $token,
        int|string|null $userId
    ): array {
        $url        = $this->buildUrl("air/book/reservation/reservations/{$workbenchId}");
        $payload    = ['ReservationQueryCommitReservation' => ['@type' => 'ReservationQueryCommitReservation']];
        $response   = null;
        $httpStatus = null;

        try {
            $response   = $this->request('post', $url, $payload, $token);
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

    private function request(string $method, string $url, array $payload, string $token)
    {
        return Http::withToken($token)
            ->withHeaders($this->buildHeaders())
            ->{$method}($url, $payload);
    }

    // buildfromlocator takes no body — avoid sending [] which causes TP/Tomcat HTTP 500
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
