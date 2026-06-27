<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingSearchLog;
use App\Services\SearchV2\Concerns\PersistsTravelportResponseFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Agent\Agent;
use App\Services\SearchV2\SearchResponseMapper;
use App\Services\DynamicRule\DynamicRulePricingApplier;

class TravelportSearchService
{
    use PersistsTravelportResponseFile;

    public function __construct(
        private readonly TravelportTokenService $tokenService,
        private readonly SearchResponseMapper $mapper,
        private readonly BookingAttemptService $bookingAttemptService,
        private readonly DynamicRulePricingApplier $dynamicRulePricingApplier,
    ) {}

    public function search(array $form, int|string|null $userId = null): array
    {
        $requestId      = (string) Str::uuid();
        $snapshotKey    = null;
        $providerResp   = [];
        $providerPayload = null;
        $responsePath   = null;
        $responseBytes  = null;
        $searchInput    = $this->extractSearchInputFields($form);

        $agencyId = $this->resolveAgencyId($userId);

        try {
            $fixture = $this->loadDevFixture();
            if ($fixture !== null) {
                [$providerResp, $fixtureForm] = $fixture;
                $effectiveForm     = is_array($fixtureForm) && !empty($fixtureForm) ? $fixtureForm : $form;
                $mapResult         = $this->mapper->map($providerResp, $effectiveForm, $agencyId);
                $flights           = $this->dynamicRulePricingApplier->applyToFlights($mapResult['flights'], $effectiveForm, $agencyId);
                $catalogIdentifier = $mapResult['catalog_identifier'];

                return [
                    'flights'               => $flights,
                    'provider_response'     => $providerResp,
                    'snapshot_key'          => null,
                    'catalog_identifier'    => $catalogIdentifier,
                    'request_id'            => $requestId,
                    'search_log_id'         => null,
                    'response_file_path'    => null,
                ];
            }

            $searchUrl = $this->resolveSearchUrl();
            if (empty($searchUrl)) {
                throw new Exception('Missing travelport_v2 search URL config.');
            }

            $providerPayload   = $this->buildProviderPayload($form);
            $providerResp      = $this->resolveProviderResponseWithCache($searchUrl, $providerPayload);

            $mapResult         = $this->mapper->map($providerResp, $form, $agencyId);
            $flights           = $this->dynamicRulePricingApplier->applyToFlights($mapResult['flights'], $form, $agencyId);
            $catalogIdentifier = $mapResult['catalog_identifier'];
            $snapshotKey       = $this->persistSnapshot($form, $providerResp, $userId);

            [$responsePath, $responseBytes] = $this->persistTravelportResponseFile('', $providerResp, $requestId);

            $log = BookingSearchLog::create(array_merge($searchInput, [
                'user_id' => $userId,
                'request_id' => $requestId,
                'search_payload' => $providerPayload ?? $form,
                'provider' => 'travelport_v2',
                'endpoint' => 'v2/search',
                'response_file_path' => $responsePath,
                'response_size_bytes' => $responseBytes,
                'flight_count' => count($flights),
                'status' => 'success',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
            $attempt = $this->bookingAttemptService->createForSearch($log, $userId);

            return [
                'flights'               => $flights,
                'provider_response'     => $providerResp,
                'snapshot_key'          => $snapshotKey,
                'catalog_identifier'    => $catalogIdentifier,
                'request_id'            => $requestId,
                'search_log_id'         => $log->id,
                'booking_attempt_id'    => $attempt->id,
                'response_file_path'    => $responsePath,
            ];
        } catch (Exception $exception) {
            $log = BookingSearchLog::create(array_merge($searchInput, [
                'user_id' => $userId,
                'request_id' => $requestId,
                'search_payload' => $providerPayload ?? $form,
                'provider' => 'travelport_v2',
                'endpoint' => 'v2/search',
                'response_file_path' => $responsePath,
                'response_size_bytes' => $responseBytes,
                'flight_count' => 0,
                'status' => 'error',
                'error_message' => $exception->getMessage(),
                'http_status' => null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
            $this->bookingAttemptService->createForSearch($log, $userId);
            throw $exception;
        }
    }

    private function resolveAgencyId(int|string|null $userId): ?int
    {
        if (!$userId) {
            return null;
        }
        $id = (int) Cache::remember(
            "user_agency_id:{$userId}",
            600,
            fn() =>
            Agent::where('user_id', $userId)->value('id') ?? 0
        );
        return $id ?: null;
    }

    private function extractSearchInputFields(array $form): array
    {
        return [
            'way'          => (int) ($form['Way'] ?? 1),
            'from_airport' => strtoupper((string) ($form['from'] ?? '')),
            'to_airport'   => strtoupper((string) ($form['to'] ?? '')),
            'dep_date'     => (string) ($form['dep_date'] ?? now()->toDateString()),
            'arrival_date' => !empty($form['arrival_date']) ? (string) $form['arrival_date'] : null,
            'adt'          => (int) ($form['ADT'] ?? 1),
            'cnn'          => (int) ($form['CNN'] ?? 0),
            'kid'          => (int) ($form['KID'] ?? 0),
            'inf'          => (int) ($form['INF'] ?? 0),
            'cabin_class'  => (string) ($form['cabin_class'] ?? 'Economy'),
        ];
    }

    private function loadDevFixture(): ?array
    {
        $fixture = trim((string) config('services.travelport_v2.dev_fixture', ''));
        if (empty($fixture)) {
            return null;
        }

        $path = base_path($fixture);
        if (!File::exists($path)) {
            return null;
        }

        $saved = json_decode(File::get($path), true);

        // wrapped format: {provider_response: ..., request: ...}
        // raw format: {CatalogProductOfferingsResponse: ...}
        if (isset($saved['provider_response'])) {
            $response = $saved['provider_response'];
            $form     = $saved['request'] ?? [];
        } else {
            $response = $saved;
            $form     = [];
        }

        return [$response, $form];
    }

    public function getLatestSnapshot(int|string|null $userId = null): ?array
    {
        $key = $this->snapshotKey($userId);
        return Cache::get($key);
    }

    private function executeSearch(string $searchUrl, array $payload): array
    {
        $token = $this->tokenService->getAccessToken();
        $headers = $this->buildSearchHeaders();
        Log::info('Executing Travelport search request', [
            'url' => $searchUrl,
            'trip_type' => count($payload['CatalogProductOfferingsRequest']['SearchCriteriaFlight'] ?? []) > 1 ? 'ROUND_TRIP' : 'ONE_WAY',
            'legs' => count($payload['CatalogProductOfferingsRequest']['SearchCriteriaFlight'] ?? []),
            'passengers' => count($payload['CatalogProductOfferingsRequest']['PassengerCriteria'] ?? []),
        ]);

        $response = Http::timeout(60)
            ->withHeaders($headers)
            ->acceptJson()
            ->withToken($token)
            ->post($searchUrl, $payload);

        if ($response->status() === 401) {
            $token = $this->tokenService->getAccessToken(true);
            $response = Http::timeout(60)
                ->withHeaders($headers)
                ->acceptJson()
                ->withToken($token)
                ->post($searchUrl, $payload);
        }

        if (!$response->successful()) {
            $errorBody = $response->body();
            Log::error('Travelport search failed', [
                'url' => $searchUrl,
                'status' => $response->status(),
                'response_preview' => mb_substr($errorBody, 0, 1000),
            ]);

            throw new Exception('Travelport search request failed: ' . $response->status() . ' | body: ' . $errorBody);
        }

        return $response->json() ?? [];
    }

    private function buildProviderPayload(array $form): array
    {
        $tripType = ((int) ($form['Way'] ?? 1)) === 2 ? 'ROUND_TRIP' : 'ONE_WAY';
        $from = strtoupper((string) ($form['from'] ?? ''));
        $to = strtoupper((string) ($form['to'] ?? ''));
        $departureDate = (string) ($form['dep_date'] ?? '');
        $returnDate = (string) ($form['arrival_date'] ?? '');
        $preferredCarriers = is_array($form['preferred_carriers'] ?? null) ? $form['preferred_carriers'] : [];
        $cabinClass = (string) ($form['cabin_class'] ?? 'Economy');

        $searchCriteriaFlight = [
            [
                '@type' => 'SearchCriteriaFlight',
                'departureDate' => $departureDate,
                'From' => ['value' => $from],
                'To' => ['value' => $to],
            ],
        ];

        if ($tripType === 'ROUND_TRIP' && !empty($returnDate)) {
            $searchCriteriaFlight[] = [
                '@type' => 'SearchCriteriaFlight',
                'departureDate' => $returnDate,
                'From' => ['value' => $to],
                'To' => ['value' => $from],
            ];
        }

        $request = [
            '@type' => 'CatalogProductOfferingsRequestAir',
            'maxNumberOfUpsellsToReturn' => 1,
            'offersPerPage' => 999,
            'contentSourceList' => ['GDS'],
            'PassengerCriteria' => $this->buildPassengerCriteria($form),
            'SearchCriteriaFlight' => $searchCriteriaFlight,
            'CustomResponseModifiersAir' => [
                '@type' => 'CustomResponseModifiersAir',
                'SearchRepresentation' => 'Journey',
            ],
        ];

        $searchModifiers = ['@type' => 'SearchModifiersAir'];

        $searchModifiers['CabinPreference'] = [
            [
                '@type'          => 'CabinPreference',
                'preferenceType' => 'Permitted',
                'cabins'         => [$cabinClass],
            ],
        ];

        if (!empty($preferredCarriers)) {
            $searchModifiers['CarrierPreference'] = [
                [
                    '@type' => 'CarrierPreference',
                    'preferenceType' => 'Preferred',
                    'carriers' => array_values($preferredCarriers),
                ],
            ];
        }

        $request['SearchModifiersAir'] = $searchModifiers;

        return [
            '@type' => 'CatalogProductOfferingsQueryRequest',
            'CatalogProductOfferingsRequest' => $request,
        ];
    }

    private function buildPassengerCriteria(array $form): array
    {
        $criteria = [];

        $append = function (int $count, string $ptc, ?int $age = null) use (&$criteria) {
            if ($count <= 0) {
                return;
            }
            $entry = [
                '@type' => 'PassengerCriteria',
                'number' => $count,
                'passengerTypeCode' => $ptc,
            ];
            if ($age !== null) {
                $entry['age'] = $age;
            }
            $criteria[] = $entry;
        };

        $append(max(1, (int) ($form['ADT'] ?? 1)), 'ADT', 25);
        $append((int) ($form['CNN'] ?? 0), 'CNN', 8);
        $append((int) ($form['KID'] ?? 0), 'CHD');
        $append((int) ($form['INF'] ?? 0), 'INF', 1);
        $append((int) ($form['INS'] ?? 0), 'INS', 1);
        $append((int) ($form['UNN'] ?? 0), 'UNN');

        return $criteria;
    }

    private function persistSnapshot(array $form, array $providerResponse, int|string|null $userId = null): string
    {
        $key = $this->snapshotKey($userId);
        Cache::put($key, [
            'saved_at' => now()->toIso8601String(),
            'request' => [
                'from' => $form['from'] ?? null,
                'to' => $form['to'] ?? null,
                'dep_date' => $form['dep_date'] ?? null,
                'arrival_date' => $form['arrival_date'] ?? null,
                'Way' => $form['Way'] ?? 1,
                'ADT' => $form['ADT'] ?? 1,
                'CNN' => $form['CNN'] ?? 0,
                'KID' => $form['KID'] ?? 0,
                'INF' => $form['INF'] ?? 0,
            ],
            'provider_summary' => $this->buildSnapshotSummary($providerResponse),
        ], now()->addHours(6));

        return $key;
    }

    private function snapshotKey(int|string|null $userId = null): string
    {
        if (!empty($userId)) {
            return 'travelport_v2_snapshot_user_' . $userId;
        }

        return 'travelport_v2_snapshot_guest';
    }

    private function resolveSearchUrl(): string
    {
        $searchUrl = trim((string) config('services.travelport_v2.search_url'));
        if (!empty($searchUrl)) {
            return $searchUrl;
        }

        $baseUrl = rtrim((string) config('services.travelport_v2.base_url'), '/');
        $version = trim((string) config('services.travelport_v2.version'), '/');
        if (empty($baseUrl) || empty($version)) {
            return '';
        }

        return "{$baseUrl}/{$version}/air/catalog/search/catalogproductofferings";
    }

    private function buildSearchHeaders(): array
    {
        $version = (string) config('services.travelport_v2.version', '11');
        $accessGroup = (string) config('services.travelport_v2.access_group', '');
        $taxBreakdown = filter_var(config('services.travelport_v2.tax_breakdown', true), FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Accept-Encoding' => 'gzip, deflate',
            'XAUTH_TRAVELPORT_ACCESSGROUP' => $accessGroup,
            'Accept-Version' => $version,
            'Content-Version' => $version,
            'taxBreakDown' => $taxBreakdown,
        ];
    }

    private function resolveProviderResponseWithCache(string $searchUrl, array $payload): array
    {
        $cacheEnabled = filter_var(config('services.travelport_v2.search_response_cache_enabled', false), FILTER_VALIDATE_BOOLEAN);
        if (!$cacheEnabled) {
            return $this->executeSearch($searchUrl, $payload);
        }

        $ttlSeconds = max((int) config('services.travelport_v2.search_response_cache_ttl_seconds', 30), 1);
        $cacheKey   = $this->responseCacheKey($searchUrl, $payload);

        return Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function () use ($searchUrl, $payload) {
            return $this->executeSearch($searchUrl, $payload);
        });
    }

    private function responseCacheKey(string $searchUrl, array $payload): string
    {
        return 'travelport_v2_resp_' . sha1($searchUrl . '|' . json_encode($payload));
    }

    private function buildSnapshotSummary(array $providerResponse): array
    {
        $offerCount = 0;
        $token = null;
        $transactionId = null;

        $offers = data_get($providerResponse, 'CatalogProductOfferingsResponse.CatalogProductOfferings.CatalogProductOffering');
        if (is_array($offers)) {
            $offerCount = array_is_list($offers) ? count($offers) : 1;
        }

        $transactionId = data_get($providerResponse, 'CatalogProductOfferingsResponse.transactionId')
            ?? data_get($providerResponse, 'transactionId');
        $token = data_get($providerResponse, 'CatalogProductOfferingsResponse.Identifier.value')
            ?? data_get($providerResponse, 'Identifier.value');

        return [
            'offer_count' => $offerCount,
            'transaction_id' => $transactionId,
            'identifier' => $token,
        ];
    }
}
