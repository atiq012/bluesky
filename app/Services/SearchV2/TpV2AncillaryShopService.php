<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingPriceLog;
use App\Models\BookingSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TpV2AncillaryShopService
{
    public const EMPTY_MESSAGE = 'No ancillary options found.';

    public function __construct(
        private readonly TravelportTokenService $tokenService
    ) {}

    public function shop(array $params, int|string|null $userId = null): array
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

        $url      = $this->buildUrl();
        $payloads = array_values(array_filter([
            $this->buildPayloadFromWorkbench($workbenchId),
            $this->buildPayloadFromCatalogProductOfferings($priceLog),
            $this->buildPayloadFromOffer($priceLog),
        ]));

        $lastException = null;

        foreach ($payloads as $index => $payload) {
            Log::info('TpV2AncillaryShopService::shop attempt', [
                'attempt'              => $index + 1,
                'payload_type'         => $payload['CatalogOfferingsQueryAncillaries']['AncillaryOfferings']['@type'] ?? null,
                'offer_identifier'     => $priceLog->offer_identifier,
                'outbound_product_ref' => $priceLog->outbound_product_ref,
                'inbound_product_ref'  => $priceLog->inbound_product_ref,
            ]);

            try {
                $result = $this->executeShop($url, $payload, $workbenchId);

                if (($result['ancillary_items'] ?? []) === []) {
                    $result['no_ancillaries'] = true;
                    $result['message']        = self::EMPTY_MESSAGE;
                }

                return $result;
            } catch (Exception $e) {
                $lastException = $e;

                if ($this->isHardShopFailure($e->getMessage())) {
                    throw $e;
                }

                Log::warning('TpV2AncillaryShopService::shop attempt failed, trying next payload', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($lastException !== null) {
            Log::info('TpV2AncillaryShopService::shop all payloads failed, returning empty', [
                'error' => $lastException->getMessage(),
            ]);
        }

        return $this->emptyShopResult();
    }

    private function emptyShopResult(): array
    {
        return [
            'travelport_response' => null,
            'ancillary_items'     => [],
            'no_ancillaries'      => true,
            'message'             => self::EMPTY_MESSAGE,
        ];
    }

    private function executeShop(string $url, array $payload, string $workbenchId): array
    {
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
                throw new Exception('Travelport ancillary shop failed. HTTP ' . $httpStatus);
            }

            $body = $response->json();

            $errors = $body['CatalogOfferingsAncillaryListResponse']['Result']['Error'] ?? [];
            if (!empty($errors)) {
                $firstMessage = $errors[0]['Message'] ?? 'Unknown error';
                throw new Exception('Travelport ancillary shop error: ' . $firstMessage);
            }

            $items = $this->extractAncillaryItems($body);

            return [
                'travelport_response' => $body,
                'ancillary_items'     => $items,
                'no_ancillaries'      => $items === [],
                'message'             => $items === [] ? self::EMPTY_MESSAGE : null,
            ];
        } catch (Exception $e) {
            Log::error('TpV2AncillaryShopService::shop failed', [
                'error'       => $e->getMessage(),
                'http_status' => $httpStatus,
                'workbench'   => $workbenchId,
                'payload'     => $payload,
            ]);

            throw $e;
        }
    }

    private function buildPayloadFromWorkbench(string $workbenchId): array
    {
        return [
            'CatalogOfferingsQueryAncillaries' => [
                'AncillaryOfferings' => [
                    '@type'                         => 'AncillaryOfferingsBuildFromReservationWorkbench',
                    'BuildFromReservationWorkbench' => [
                        'ReservationIdentifier' => [
                            'Identifier' => ['value' => $workbenchId],
                        ],
                    ],
                ],
            ],
        ];
    }

    // Same catalog context as add-offer (flight already on workbench)
    private function buildPayloadFromCatalogProductOfferings(BookingPriceLog $priceLog): ?array
    {
        $catalogIdentifier = preg_replace('/_PC$/', '', (string) ($priceLog->offer_identifier ?? ''));

        if ($catalogIdentifier === '') {
            return null;
        }

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

        if ($selections === []) {
            return null;
        }

        return [
            'CatalogOfferingsQueryAncillaries' => [
                'AncillaryOfferings' => [
                    '@type'                            => 'AncillaryOfferingsBuildFromCatalogProductOfferings',
                    'BuildFromCatalogProductOfferings' => [
                        '@type'                             => 'BuildFromCatalogProductOfferingsAir',
                        'CatalogProductOfferingsIdentifier' => [
                            'Identifier' => ['value' => $catalogIdentifier],
                        ],
                        'CatalogProductOfferingSelection'   => $selections,
                    ],
                ],
            ],
        ];
    }

    private function buildPayloadFromOffer(BookingPriceLog $priceLog): ?array
    {
        $offerId = trim((string) ($priceLog->offer_identifier ?? ''));

        if ($offerId === '') {
            return null;
        }

        $productRefs = array_values(array_filter([
            $priceLog->outbound_product_ref,
            $priceLog->inbound_product_ref,
        ]));

        if ($productRefs === []) {
            return null;
        }

        return [
            'CatalogOfferingsQueryAncillaries' => [
                'AncillaryOfferings' => [
                    '@type'          => 'AncillaryOfferingsBuildFromOffer',
                    'BuildFromOffer' => [
                        '@type'             => 'BuildFromOfferAir',
                        'OfferIdentifier'   => [
                            'Identifier' => ['value' => $offerId],
                        ],
                        'ProductIdentifier' => array_map(
                            fn(string $ref) => ['Identifier' => ['value' => $ref]],
                            $productRefs
                        ),
                    ],
                ],
            ],
        ];
    }

    public function isHardFailureMessage(string $message): bool
    {
        return $this->isHardShopFailure($message);
    }

    // Only true configuration/session problems should surface as API errors to the UI.
    private function isHardShopFailure(string $message): bool
    {
        $needles = [
            'Invalid or expired workbench session',
            'Price log not found',
            'Offer identifier missing',
            'Product references missing',
        ];

        foreach ($needles as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function extractAncillaryItems(array $body): array
    {
        $items = [];
        $isRoundTrip = $this->isRoundTripResponse($body);

        $response                = $body['CatalogOfferingsAncillaryListResponse'] ?? null;
        $catalogOfferingsIdValue = $response['Identifier']['value'] ?? null;
        $groups                  = $response['CatalogOfferingsID'] ?? [];

        if (!is_array($groups)) {
            return $items;
        }

        if (isset($groups['id'])) {
            $groups = [$groups];
        }

        foreach ($groups as $group) {
            $catalogOfferingsGroupId = $group['id'] ?? null;
            $offerings               = $group['CatalogOffering'] ?? [];

            if (!is_array($offerings) || empty($offerings)) {
                continue;
            }

            if (isset($offerings['id'])) {
                $offerings = [$offerings];
            }

            foreach ($offerings as $offering) {
                $product = $this->normalizeProductFromOffering($offering);
                if ($product === null) {
                    continue;
                }

                $ancillary = $product['Ancillary'] ?? [];
                $price     = $offering['Price'] ?? null;
                $meta      = $this->resolveAncillaryDisplayMeta($ancillary, $price);
                $terms     = $this->normalizeTermsAndConditions($offering['TermsAndConditions'] ?? null);
                $flightRefs = $this->normalizeFlightRefs($ancillary['FlightRef'] ?? null);
                $scope      = $this->resolveApplicationScope(
                    $terms,
                    $flightRefs,
                    $isRoundTrip
                );

                $items[] = [
                    'catalog_offerings_group_id'            => $catalogOfferingsGroupId,
                    'catalog_offerings_identifier_value'    => $catalogOfferingsIdValue,
                    'catalog_offering_id'                   => $offering['id'] ?? null,
                    'catalog_offering_identifier_value'     => $offering['Identifier']['value'] ?? null,
                    'catalog_offering_identifier_authority' => $offering['Identifier']['authority'] ?? 'Travelport',
                    'product_id'                            => $product['id'] ?? null,
                    'ancillary_type'                        => $ancillary['@type'] ?? null,
                    'name'                                  => $meta['name'],
                    'subtitle'                              => $meta['subtitle'],
                    'code'                                  => $meta['code'],
                    'ssr_code'                              => $meta['ssr_code'],
                    'price'                                 => $price['TotalPrice'] ?? null,
                    'currency'                              => $price['CurrencyCode']['value'] ?? 'BDT',
                    'flight_refs'                           => $flightRefs,
                    'application_limit'                     => $scope['application_limit'],
                    'scope_label'                           => $scope['scope_label'],
                ];
            }
        }

        $deduped = $this->deduplicateAncillaryItems($items);
        $merged  = $this->mergeItineraryScopedBaggageItems($deduped);

        return $this->applyCoverageMetadata($merged);
    }

    // Travelport can return duplicate ancillary cards across groups; keep one per business scope.
    private function deduplicateAncillaryItems(array $items): array
    {
        $seen = [];
        $out  = [];

        foreach ($items as $item) {
            $key = $this->ancillaryItemDedupeKey($item);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key]   = true;
            $item['item_key'] = $key;
            $out[]        = $item;
        }

        if (count($out) < count($items)) {
            Log::info('TpV2AncillaryShopService deduped ancillary items', [
                'before' => count($items),
                'after'  => count($out),
            ]);
        }

        return $out;
    }

    private function ancillaryItemDedupeKey(array $item): string
    {
        $flightRefs = $item['flight_refs'] ?? [];
        sort($flightRefs);

        return implode('|', [
            (string) ($item['product_id'] ?? ''),
            (string) ($item['catalog_offering_id'] ?? ''),
            (string) ($item['application_limit'] ?? ''),
            implode(',', $flightRefs),
            (string) ($item['price'] ?? ''),
            strtoupper((string) ($item['ssr_code'] ?? $item['code'] ?? '')),
        ]);
    }

    private function normalizeTermsAndConditions(mixed $terms): array
    {
        if (!is_array($terms)) {
            return [];
        }

        if (isset($terms['@type']) || isset($terms['ApplicationLimit'])) {
            return [$terms];
        }

        return $terms;
    }

    private function normalizeFlightRefs(mixed $flightRefs): array
    {
        if ($flightRefs === null) {
            return [];
        }

        if (is_string($flightRefs)) {
            return [trim($flightRefs)];
        }

        if (!is_array($flightRefs)) {
            return [];
        }

        $refs = [];
        foreach ($flightRefs as $ref) {
            if (!is_scalar($ref)) {
                continue;
            }

            $value = trim((string) $ref);
            if ($value !== '') {
                $refs[] = $value;
            }
        }

        return array_values(array_unique($refs));
    }

    private function resolveApplicationScope(array $termsList, array $flightRefs, bool $hasInbound = false): array
    {
        $applicationLimit = null;

        foreach ($termsList as $term) {
            if (!is_array($term)) {
                continue;
            }

            $value = strtoupper(trim((string) ($term['ApplicationLimit']['value'] ?? '')));
            if ($value !== '') {
                $applicationLimit = $value;
                break;
            }
        }

        $scopeLabel = match ($applicationLimit) {
            'ITINERARY'        => $hasInbound ? 'Applies to outbound + inbound' : 'Applies to full journey',
            'PASSENGER'        => 'Per passenger (full itinerary)',
            'PASSENGEROD'      => 'Per passenger (origin-destination)',
            'SEGMENT'          => 'Per segment',
            'PASSENGERSEGMENT' => 'Per passenger per segment',
            default            => null,
        };

        if ($scopeLabel === null && count($flightRefs) > 1) {
            $scopeLabel = 'Applies to multiple segments';
        }

        return [
            'application_limit' => $applicationLimit,
            'scope_label'       => $scopeLabel,
        ];
    }

    // Business override: show one itinerary-level baggage card instead of per-leg split cards.
    private function mergeItineraryScopedBaggageItems(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            if (!$this->isBaggageLikeAncillary($item)) {
                continue;
            }

            $limit = strtoupper((string) ($item['application_limit'] ?? ''));
            if (!in_array($limit, ['SEGMENT', 'PASSENGERSEGMENT'], true)) {
                continue;
            }

            $key = implode('|', [
                strtoupper((string) ($item['ssr_code'] ?? $item['code'] ?? '')),
                (string) ($item['name'] ?? ''),
                (string) ($item['price'] ?? ''),
                (string) ($item['currency'] ?? ''),
                (string) ($item['ancillary_type'] ?? ''),
                $limit,
            ]);

            $groups[$key][] = $item;
        }

        if ($groups === []) {
            return $items;
        }

        $indexByItemKey = [];
        foreach ($items as $index => $item) {
            $indexByItemKey[(string) ($item['item_key'] ?? '')] = $index;
        }

        foreach ($groups as $bucket) {
            if (count($bucket) < 2) {
                continue;
            }

            $flightUnion = [];
            foreach ($bucket as $entry) {
                $flightUnion = array_merge($flightUnion, $entry['flight_refs'] ?? []);
            }
            $flightUnion = array_values(array_unique($flightUnion));
            $directionMap = $this->buildDirectionMap($flightUnion);

            $components = [];
            foreach ($bucket as $entry) {
                $components[] = [
                    'catalog_offerings_group_id'            => $entry['catalog_offerings_group_id'] ?? null,
                    'catalog_offerings_identifier_value'    => $entry['catalog_offerings_identifier_value'] ?? null,
                    'catalog_offerings_identifier_authority' => $entry['catalog_offering_identifier_authority'] ?? 'Travelport',
                    'catalog_offering_id'                   => $entry['catalog_offering_id'] ?? null,
                    'product_id'                            => $entry['product_id'] ?? null,
                    'price'                                 => $entry['price'] ?? null,
                    'currency'                              => $entry['currency'] ?? null,
                    'flight_refs'                           => $entry['flight_refs'] ?? [],
                    'component_direction'                   => $this->componentDirectionFromRefs(
                        $entry['flight_refs'] ?? [],
                        $directionMap
                    ),
                ];
            }

            $directions = array_values(array_unique(array_filter(
                array_map(
                    fn(array $row) => $row['component_direction'] ?? null,
                    $components
                )
            )));
            $canSelectCoverage = in_array('outbound', $directions, true)
                && in_array('inbound', $directions, true);

            $merged = $bucket[0];
            $merged['flight_refs']       = $flightUnion;
            $merged['application_limit'] = 'ITINERARY';
            $merged['scope_label']       = $this->isRoundTripFromRefs($flightUnion)
                ? 'Applies to outbound + inbound'
                : 'Applies to full journey';
            $merged['merged_components'] = $components;
            $merged['coverage_options']  = $canSelectCoverage
                ? ['both', 'outbound', 'inbound']
                : ['both'];
            $merged['default_coverage']  = 'both';
            $merged['can_select_coverage'] = $canSelectCoverage;
            $merged['item_key']          = 'merged:' . md5(json_encode($components));

            $firstItemKey = (string) ($bucket[0]['item_key'] ?? '');
            if ($firstItemKey !== '' && isset($indexByItemKey[$firstItemKey])) {
                $items[$indexByItemKey[$firstItemKey]] = $merged;
            }

            for ($i = 1; $i < count($bucket); $i++) {
                $extraKey = (string) ($bucket[$i]['item_key'] ?? '');
                if ($extraKey !== '' && isset($indexByItemKey[$extraKey])) {
                    unset($items[$indexByItemKey[$extraKey]]);
                }
            }
        }

        return array_values($items);
    }

    private function applyCoverageMetadata(array $items): array
    {
        foreach ($items as $index => $item) {
            if (isset($item['coverage_options'])) {
                continue;
            }

            $items[$index]['coverage_options'] = ['both'];
            $items[$index]['default_coverage'] = 'both';
            $items[$index]['can_select_coverage'] = false;
        }

        return $items;
    }

    private function isBaggageLikeAncillary(array $item): bool
    {
        $type = strtoupper((string) ($item['ancillary_type'] ?? ''));
        if ($type === 'ANCILLARYAIRBAGGAGE') {
            return true;
        }

        $code = strtoupper((string) ($item['ssr_code'] ?? $item['code'] ?? ''));
        if ($code !== '' && str_ends_with($code, 'BAG')) {
            return true;
        }

        $name = strtoupper((string) ($item['name'] ?? ''));
        $subtitle = strtoupper((string) ($item['subtitle'] ?? ''));

        return str_contains($name, 'BAG') || str_contains($subtitle, 'BAG');
    }

    private function isRoundTripResponse(array $body): bool
    {
        $response = $body['CatalogOfferingsAncillaryListResponse'] ?? null;
        if (!is_array($response)) {
            return false;
        }

        $groups = $response['CatalogOfferingsID'] ?? [];
        if (!is_array($groups)) {
            return false;
        }
        if (isset($groups['id'])) {
            $groups = [$groups];
        }

        $refs = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $offerings = $group['CatalogOffering'] ?? [];
            if (!is_array($offerings)) {
                continue;
            }
            if (isset($offerings['id'])) {
                $offerings = [$offerings];
            }

            foreach ($offerings as $offering) {
                if (!is_array($offering)) {
                    continue;
                }

                $product = $this->normalizeProductFromOffering($offering);
                if (!is_array($product)) {
                    continue;
                }

                $ancillary = $product['Ancillary'] ?? [];
                foreach ($this->normalizeFlightRefs($ancillary['FlightRef'] ?? null) as $ref) {
                    $refs[$ref] = true;
                }
            }
        }

        return count($refs) >= 2;
    }

    private function isRoundTripFromRefs(array $flightRefs): bool
    {
        return count(array_unique(array_filter($flightRefs))) >= 2;
    }

    private function buildDirectionMap(array $flightRefs): array
    {
        $unique = array_values(array_unique(array_filter($flightRefs)));

        $map = [];
        if (isset($unique[0])) {
            $map[$unique[0]] = 'outbound';
        }
        if (isset($unique[1])) {
            $map[$unique[1]] = 'inbound';
        }

        return $map;
    }

    private function componentDirectionFromRefs(array $flightRefs, array $directionMap): ?string
    {
        foreach ($flightRefs as $ref) {
            if (isset($directionMap[$ref])) {
                return $directionMap[$ref];
            }
        }

        return null;
    }

    private function normalizeProductFromOffering(array $offering): ?array
    {
        $productOptions = $offering['ProductOptions'] ?? null;
        if (!is_array($productOptions)) {
            return null;
        }

        // TP returns ProductOptions as array of wrappers or a single object
        if (isset($productOptions[0]) && is_array($productOptions[0])) {
            $productOptions = $productOptions[0];
        }

        $products = $productOptions['Product'] ?? null;
        if (!is_array($products)) {
            return null;
        }

        if (isset($products['@type']) || isset($products['id'])) {
            return $products;
        }

        $first = $products[0] ?? null;

        return is_array($first) ? $first : null;
    }

    private function resolveAncillaryDisplayMeta(array $ancillary, ?array $price): array
    {
        $type         = $ancillary['@type'] ?? null;
        $descriptions = $ancillary['Description'] ?? [];
        $code         = null;
        $ssrCode      = null;
        $subCode      = null;
        $name         = null;

        if (isset($descriptions['value']) || isset($descriptions['code'])) {
            $descriptions = [$descriptions];
        }

        if (is_array($descriptions)) {
            foreach ($descriptions as $desc) {
                if (!is_array($desc)) {
                    continue;
                }

                $code    = $code ?? ($desc['code'] ?? null);
                $ssrCode = $ssrCode ?? ($desc['ssrCode'] ?? null);
                $subCode = $subCode ?? ($desc['subCode'] ?? null);

                $value = trim((string) ($desc['value'] ?? ''));
                if ($name === null && $value !== '' && !$this->isInternalAncillaryDescription($value)) {
                    $name = $value;
                }
            }
        }

        if ($name === null) {
            $name = $this->nameFromPriceBreakdown($price);
        }

        if ($name === null) {
            $name = $this->fallbackAncillaryName($ancillary, $type, $code, $ssrCode, $subCode);
        }

        return [
            'name'     => $name,
            'subtitle' => $this->subtitleForAncillaryType($type, $ancillary),
            'code'     => $code ?? $subCode,
            'ssr_code' => $ssrCode,
        ];
    }

    private function isInternalAncillaryDescription(string $value): bool
    {
        if (preg_match('/^C-/i', $value)) {
            return true;
        }

        return strlen($value) < 4;
    }

    private function nameFromPriceBreakdown(?array $price): ?string
    {
        $breakdowns = $price['PriceBreakdown'] ?? [];
        if (!is_array($breakdowns)) {
            return null;
        }

        if (isset($breakdowns['@type'])) {
            $breakdowns = [$breakdowns];
        }

        foreach ($breakdowns as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = trim((string) ($row['Description']['value'] ?? ''));
            if ($value !== '' && !$this->isInternalAncillaryDescription($value)) {
                return $value;
            }
        }

        return null;
    }

    private function fallbackAncillaryName(
        array $ancillary,
        ?string $type,
        ?string $code,
        ?string $ssrCode,
        ?string $subCode
    ): string {
        if ($type === 'AncillaryAirBaggage') {
            $piece  = !empty($ancillary['soldByPieceInd']);
            $weight = !empty($ancillary['soldByWeightInd']);
            if ($piece) {
                return 'Checked baggage (by piece)';
            }
            if ($weight) {
                return 'Checked baggage (by weight)';
            }

            return 'Extra baggage';
        }

        if ($ssrCode !== null && $ssrCode !== '') {
            return $this->humanizeSsrCode($ssrCode);
        }

        if ($code !== null && $code !== '') {
            return 'Ancillary — ' . $code . ($subCode ? " ({$subCode})" : '');
        }

        return match ($type) {
            'AncillaryAirSeat' => 'Seat selection',
            'AncillaryAir'     => 'Optional air service',
            default            => 'Ancillary service',
        };
    }

    private function subtitleForAncillaryType(?string $type, array $ancillary): string
    {
        if (!empty($ancillary['BaggageType'])) {
            return (string) $ancillary['BaggageType'] . ' baggage';
        }

        return match ($type) {
            'AncillaryAirBaggage' => 'Extra baggage',
            'AncillaryAirSeat'    => 'Seat',
            'AncillaryAir'        => 'Paid optional service',
            default               => 'Optional service',
        };
    }

    private function humanizeSsrCode(string $ssrCode): string
    {
        static $map = [
            'PETC' => 'Pet in cabin',
            'AVIH' => 'Pet in hold',
            'XBAG' => 'Extra baggage',
            'ABAG' => 'Additional baggage',
        ];

        $upper = strtoupper($ssrCode);

        return $map[$upper] ?? $upper;
    }

    private function buildUrl(): string
    {
        $base    = rtrim((string) config('services.travelport_v2.base_url'), '/');
        $version = trim((string) config('services.travelport_v2.version'));

        return "{$base}/{$version}/air/ancillaryshop/catalogofferingsancillaries";
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
