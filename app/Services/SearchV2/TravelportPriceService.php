<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingAttempt;
use App\Models\BookingPriceLog;
use App\Services\SearchV2\Concerns\PersistsTravelportResponseFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TravelportPriceService
{
    use PersistsTravelportResponseFile;

    public function __construct(
        private readonly TravelportTokenService  $tokenService,
        private readonly PriceResponseMapper     $mapper,
        private readonly BookingAttemptService   $bookingAttemptService
    ) {}

    public function price(array $priceRequest, int|string|null $userId = null): array
    {
        $requestId    = (string) Str::uuid();
        $responsePath = null;
        $responseBytes = null;
        $payload      = null;

        try {
            $fixture = $this->loadPriceFixture();
            if ($fixture !== null) {
                $priceData = $this->mapper->map($fixture);

                return [
                    'price_data'       => $priceData,
                    'offer_identifier' => $priceData['offer_identifier'] ?? null,
                    'price_log_id'     => null,
                    'request_id'       => $requestId,
                    'response_file_path' => null,
                ];
            }

            $priceUrl = trim((string) config('services.travelport_v2.price_url'));
            if (empty($priceUrl)) {
                throw new Exception('Missing travelport_v2 price URL config (TRAVELPORT_V2_PRICE_URL).');
            }

            $payload   = $this->buildPricePayload($priceRequest);
            $response  = $this->executePrice($priceUrl, $payload);

            $priceData = $this->mapper->map($response);

            [$responsePath, $responseBytes] = $this->persistTravelportResponseFile('price', $response, $requestId);

            $log = BookingPriceLog::create([
                'user_id'                => $userId,
                'booking_search_log_id'  => $priceRequest['search_log_id'] ?? null,
                'selection_json'         => $priceRequest['selection_json'] ?? null,
                'request_id'             => $requestId,
                'catalog_identifier'     => $priceRequest['catalog_identifier'] ?? null,
                'offer_identifier'       => $priceData['offer_identifier'] ?? null,
                'outbound_offering_id'   => $priceRequest['outbound_offering_id'] ?? null,
                'outbound_product_ref'   => $priceRequest['outbound_product_ref'] ?? null,
                'inbound_offering_id'    => $priceRequest['inbound_offering_id'] ?? null,
                'inbound_product_ref'    => $priceRequest['inbound_product_ref'] ?? null,
                'way'                    => (int) ($priceRequest['form']['Way'] ?? 1),
                'from_airport'           => strtoupper((string) ($priceRequest['form']['from'] ?? '')),
                'to_airport'             => strtoupper((string) ($priceRequest['form']['to'] ?? '')),
                'dep_date'               => $priceRequest['form']['dep_date'] ?? null,
                'arrival_date'           => $priceRequest['form']['arrival_date'] ?? null,
                'adt'                    => (int) ($priceRequest['form']['ADT'] ?? 1),
                'cnn'                    => (int) ($priceRequest['form']['CNN'] ?? 0),
                'kid'                    => (int) ($priceRequest['form']['KID'] ?? 0),
                'inf'                    => (int) ($priceRequest['form']['INF'] ?? 0),
                'cabin_class'            => (string) ($priceRequest['form']['cabin_class'] ?? 'Economy'),
                'price_payload'          => ['request' => $payload, 'mapped' => $priceData],
                'total_price'            => $priceData['total_price'] ?? null,
                'currency'               => $priceData['currency'] ?? 'BDT',
                'base_fare'              => $priceData['base_fare'] ?? null,
                'total_taxes'            => $priceData['total_taxes'] ?? null,
                'provider'               => 'travelport_v2',
                'status'                 => 'success',
                'response_file_path'     => $responsePath,
                'response_size_bytes'    => $responseBytes,
                'created_by'             => $userId,
                'updated_by'             => $userId,
            ]);
            $attempt = $this->bookingAttemptService->attachPriceToAttempt(
                $log,
                $priceRequest['booking_attempt_id'] ?? null,
                $priceRequest['selection_json'] ?? null,
                $userId
            );

            return [
                'price_data'         => $priceData,
                'offer_identifier'   => $priceData['offer_identifier'] ?? null,
                'price_log_id'       => $log->id,
                'booking_attempt_id' => $attempt->id,
                'request_id'         => $requestId,
                'response_file_path' => $responsePath,
            ];
        } catch (Exception $exception) {
            $log = BookingPriceLog::create([
                'user_id'                => $userId,
                'booking_search_log_id'  => $priceRequest['search_log_id'] ?? null,
                'selection_json'         => $priceRequest['selection_json'] ?? null,
                'request_id'           => $requestId,
                'catalog_identifier'   => $priceRequest['catalog_identifier'] ?? null,
                'outbound_offering_id' => $priceRequest['outbound_offering_id'] ?? null,
                'outbound_product_ref' => $priceRequest['outbound_product_ref'] ?? null,
                'inbound_offering_id'  => $priceRequest['inbound_offering_id'] ?? null,
                'inbound_product_ref'  => $priceRequest['inbound_product_ref'] ?? null,
                'way'                  => (int) ($priceRequest['form']['Way'] ?? 1),
                'from_airport'         => strtoupper((string) ($priceRequest['form']['from'] ?? '')),
                'to_airport'           => strtoupper((string) ($priceRequest['form']['to'] ?? '')),
                'dep_date'             => $priceRequest['form']['dep_date'] ?? null,
                'arrival_date'         => $priceRequest['form']['arrival_date'] ?? null,
                'adt'                  => (int) ($priceRequest['form']['ADT'] ?? 1),
                'cnn'                  => (int) ($priceRequest['form']['CNN'] ?? 0),
                'kid'                  => (int) ($priceRequest['form']['KID'] ?? 0),
                'inf'                  => (int) ($priceRequest['form']['INF'] ?? 0),
                'cabin_class'          => (string) ($priceRequest['form']['cabin_class'] ?? 'Economy'),
                'price_payload'        => ['request' => $payload],
                'provider'             => 'travelport_v2',
                'status'               => 'error',
                'error_message'        => $exception->getMessage(),
                'response_file_path'   => $responsePath,
                'response_size_bytes'  => $responseBytes,
                'created_by'           => $userId,
                'updated_by'           => $userId,
            ]);

            $attemptId = $priceRequest['booking_attempt_id'] ?? null;
            if (!$attemptId && !empty($priceRequest['search_log_id'])) {
                $attemptId = BookingAttempt::query()
                    ->where('booking_search_log_id', $priceRequest['search_log_id'])
                    ->when($userId, fn($q) => $q->where('user_id', $userId))
                    ->orderByDesc('id')
                    ->value('id');
            }

            BookingAttemptOutcome::record(
                $attemptId,
                BookingAttemptOutcome::STAGE_PRICE,
                'price',
                'error',
                $exception->getMessage(),
                $userId
            );

            throw $exception;
        }
    }

    private function buildPricePayload(array $priceRequest): array
    {
        $catalogIdentifier  = $priceRequest['catalog_identifier'];
        $outboundOfferingId = $priceRequest['outbound_offering_id'];
        $outboundProductRef = $priceRequest['outbound_product_ref'];
        $inboundOfferingId  = $priceRequest['inbound_offering_id'] ?? null;
        $inboundProductRef  = $priceRequest['inbound_product_ref'] ?? null;

        $selections = [
            [
                'CatalogProductOfferingIdentifier' => [
                    'Identifier' => ['value' => $outboundOfferingId],
                ],
                'ProductIdentifier' => [
                    ['Identifier' => ['value' => $outboundProductRef]],
                ],
            ],
        ];

        if (!empty($inboundOfferingId) && !empty($inboundProductRef)) {
            $selections[] = [
                'CatalogProductOfferingIdentifier' => [
                    'Identifier' => ['value' => $inboundOfferingId],
                ],
                'ProductIdentifier' => [
                    ['Identifier' => ['value' => $inboundProductRef]],
                ],
            ];
        }

        return [
            '@type' => 'OfferQueryBuildFromCatalogProductOfferings',
            'BuildFromCatalogProductOfferingsRequestAir' => [
                '@type'                            => 'BuildFromCatalogProductOfferingsRequestAir',
                'validateInventoryInd'             => true,
                'CatalogProductOfferingsIdentifier' => [
                    'Identifier' => ['value' => $catalogIdentifier],
                ],
                'CatalogProductOfferingSelection'  => $selections,
            ],
        ];
    }

    private function executePrice(string $priceUrl, array $payload): array
    {
        $token   = $this->tokenService->getAccessToken();
        $headers = $this->buildHeaders();

        $response = Http::timeout(60)
            ->withHeaders($headers)
            ->acceptJson()
            ->withToken($token)
            ->post($priceUrl, $payload);

        if ($response->status() === 401) {
            $token    = $this->tokenService->getAccessToken(true);
            $response = Http::timeout(60)
                ->withHeaders($headers)
                ->acceptJson()
                ->withToken($token)
                ->post($priceUrl, $payload);
        }

        if (!$response->successful()) {
            $errorBody = $response->body();
            Log::error('Travelport price failed', [
                'url'              => $priceUrl,
                'status'           => $response->status(),
                'response_preview' => mb_substr($errorBody, 0, 1000),
            ]);
            throw new Exception('Travelport price request failed: ' . $response->status() . ' | ' . $errorBody);
        }

        return $response->json() ?? [];
    }

    private function buildHeaders(): array
    {
        $version     = (string) config('services.travelport_v2.version', '11');
        $accessGroup = (string) config('services.travelport_v2.access_group', '');
        $taxBreakdown = filter_var(config('services.travelport_v2.tax_breakdown', true), FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';

        return [
            'Accept'                        => 'application/json',
            'Content-Type'                  => 'application/json',
            'Accept-Encoding'               => 'gzip, deflate',
            'XAUTH_TRAVELPORT_ACCESSGROUP'  => $accessGroup,
            'Accept-Version'                => $version,
            'Content-Version'               => $version,
            'taxBreakDown'                  => $taxBreakdown,
        ];
    }

    private function loadPriceFixture(): ?array
    {
        $fixture = trim((string) config('services.travelport_v2.price_fixture', ''));
        if (empty($fixture)) {
            return null;
        }

        $path = base_path($fixture);
        if (!File::exists($path)) {
            return null;
        }

        return json_decode(File::get($path), true) ?? null;
    }
}
