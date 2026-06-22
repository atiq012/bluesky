<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingPriceLog;
use App\Models\BookingSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TpV2AddOfferService
{
    public function __construct(
        private readonly TravelportTokenService $tokenService,
        private readonly BookingSessionLogger $sessionLogger
    ) {}

    public function addOffer(array $params, int|string|null $userId = null): array
    {
        $workbenchId = $params['workbench_identifier'];
        $sessionId   = (int) $params['session_id'];

        $workbenchSession = BookingSession::query()
            ->where('id', $sessionId)
            ->where('session_type', 'reservation_workbench')
            ->where('identifier_value', $workbenchId)
            ->where('status', 'success')
            ->first();

        if (!$workbenchSession) {
            throw new Exception('Invalid or expired workbench session.');
        }

        $priceLog = BookingPriceLog::find($workbenchSession->booking_price_log_id);

        if (!$priceLog) {
            throw new Exception('Price log not found for this workbench session.');
        }

        $url     = $this->buildUrl($workbenchId);
        $payload = $this->buildPayload($priceLog);

        $response      = null;
        $httpStatus    = null;
        $maxAttempts   = 3;
        $lastException = null;

        try {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $forceRefresh = $attempt > 1;
                $token        = $this->tokenService->getAccessToken(forceRefresh: $forceRefresh);
                $response     = $this->executeRequest($url, $payload, $token);

                if ($response->status() === 401) {
                    $token    = $this->tokenService->getAccessToken(forceRefresh: true);
                    $response = $this->executeRequest($url, $payload, $token);
                }

                $httpStatus = $response->status();

                try {
                    if (!$response->successful()) {
                        throw new Exception('Travelport add offer failed. HTTP ' . $httpStatus);
                    }

                    $body   = $response->json();
                    $errors = $body['OfferListResponse']['Result']['Error'] ?? [];

                    if (!empty($errors)) {
                        $firstMessage = $errors[0]['Message'] ?? 'Unknown error';
                        throw new Exception('Travelport add offer error: ' . $firstMessage);
                    }

                    $this->sessionLogger->create([
                        'user_id'              => $userId,
                        'booking_attempt_id'   => $workbenchSession->booking_attempt_id,
                        'booking_price_log_id' => $workbenchSession->booking_price_log_id,
                        'session_type'         => 'add_offer',
                        'request_payload'      => $payload,
                        'response_payload'     => $body,
                        'identifier_value'     => $workbenchId,
                        'provider'             => 'travelport_v2',
                        'status'               => 'success',
                        'http_status'          => $httpStatus,
                        'created_by'           => $userId,
                        'updated_by'           => $userId,
                    ], $body);

                    $contentSource = $body['OfferListResponse']['OfferID'][0]['ContentSource']
                        ?? $body['OfferListResponse']['Offer'][0]['ContentSource']
                        ?? 'GDS';

                    return [
                        'travelport_response' => $body,
                        'content_source'      => $contentSource,
                    ];
                } catch (Exception $attemptException) {
                    $lastException = $attemptException;

                    Log::warning('TpV2AddOfferService::addOffer attempt failed', [
                        'attempt'     => $attempt,
                        'max_attempt' => $maxAttempts,
                        'error'       => $attemptException->getMessage(),
                        'http_status' => $httpStatus,
                        'workbench'   => $workbenchId,
                    ]);

                    if (!$this->isRetryableAddOfferError($attemptException, $httpStatus) || $attempt === $maxAttempts) {
                        throw $attemptException;
                    }

                    // Minimal backoff for transient TP provider communication errors.
                    usleep(300000 * $attempt);
                }
            }

            throw $lastException ?? new Exception('Travelport add offer failed unexpectedly.');
        } catch (Exception $e) {
            Log::error('TpV2AddOfferService::addOffer failed', [
                'error'       => $e->getMessage(),
                'http_status' => $httpStatus,
                'workbench'   => $workbenchId,
            ]);

            $this->sessionLogger->create([
                'user_id'              => $userId,
                'booking_attempt_id'   => $workbenchSession->booking_attempt_id,
                'booking_price_log_id' => $workbenchSession->booking_price_log_id,
                'session_type'         => 'add_offer',
                'request_payload'      => $payload ?? null,
                'response_payload'     => $response?->json(),
                'identifier_value'     => $workbenchId,
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

    private function isRetryableAddOfferError(Exception $exception, ?int $httpStatus): bool
    {
        if (in_array($httpStatus, [429, 500, 502, 503, 504], true)) {
            return true;
        }

        $message = strtoupper($exception->getMessage());

        foreach (
            [
                'COMMUNICATION ERROR',
                'RETRY',
                'TIMEOUT',
                'TIMED OUT',
                'TEMPORARY',
                'SERVICE UNAVAILABLE',
                'UNABLE TO COMMUNICATE',
            ] as $needle
        ) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function buildPayload(BookingPriceLog $priceLog): array
    {
        // TP requires _PC suffix stripped when referencing a prior price response
        $catalogIdentifier = preg_replace('/_PC$/', '', (string) ($priceLog->offer_identifier ?? ''));

        $selections = [];

        if (!empty($priceLog->outbound_offering_id)) {
            $selection = [
                'CatalogProductOfferingIdentifier' => [
                    'Identifier' => ['value' => $priceLog->outbound_offering_id],
                ],
            ];
            if (!empty($priceLog->outbound_product_ref)) {
                $selection['ProductIdentifier'] = [
                    ['Identifier' => ['value' => $priceLog->outbound_product_ref]],
                ];
            }
            $selections[] = $selection;
        }

        if (!empty($priceLog->inbound_offering_id)) {
            $selection = [
                'CatalogProductOfferingIdentifier' => [
                    'Identifier' => ['value' => $priceLog->inbound_offering_id],
                ],
            ];
            if (!empty($priceLog->inbound_product_ref)) {
                $selection['ProductIdentifier'] = [
                    ['Identifier' => ['value' => $priceLog->inbound_product_ref]],
                ];
            }
            $selections[] = $selection;
        }

        return [
            'OfferQueryBuildFromCatalogProductOfferings' => [
                'BuildFromCatalogProductOfferingsRequest' => [
                    '@type'                              => 'BuildFromCatalogProductOfferingsRequestAir',
                    'CatalogProductOfferingsIdentifier'  => [
                        'Identifier' => ['value' => $catalogIdentifier],
                    ],
                    'CatalogProductOfferingSelection'    => $selections,
                ],
            ],
        ];
    }

    private function buildUrl(string $workbenchIdentifier): string
    {
        $base    = rtrim((string) config('services.travelport_v2.base_url'), '/');
        $version = trim((string) config('services.travelport_v2.version'));

        return "{$base}/{$version}/air/book/airoffer/reservationworkbench/{$workbenchIdentifier}/offers/buildfromcatalogproductofferings";
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
