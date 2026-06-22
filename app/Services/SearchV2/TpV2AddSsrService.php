<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingAttempt;
use App\Models\BookingPax;
use App\Models\BookingPriceLog;
use App\Models\BookingSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\SearchV2\BookingActivityLogger;

class TpV2AddSsrService
{
    private const MEAL_MAP = [
        'Veg' => 'VegetarianLactoOvo',
    ];

    public function __construct(
        private readonly TravelportTokenService $tokenService,
        private readonly BookingSessionLogger $sessionLogger,
        private readonly BookingActivityLogger $activityLogger,
    ) {}

    public function apply(array $params, int|string|null $userId = null): array
    {
        $workbenchId = $params['workbench_identifier'];
        $sessionId   = (int) $params['session_id'];

        $workbenchSession = $this->resolveWorkbenchSession($workbenchId, $sessionId);

        $paxes = BookingPax::query()
            ->where('booking_attempt_id', $workbenchSession->booking_attempt_id)
            ->where('status', 'success')
            ->orderBy('sequence')
            ->get();

        if ($paxes->isEmpty()) {
            throw new Exception('No travelers found. Submit traveler details first.');
        }

        $addTravelerSession = $this->latestSession($workbenchId, 'add_traveler');
        $addOfferSession    = $this->latestSession($workbenchId, 'add_offer');

        $priceLog = BookingPriceLog::find($workbenchSession->booking_price_log_id);
        if (!$priceLog) {
            throw new Exception('Price log not found for this workbench session.');
        }

        $offerCtx = $this->resolveOfferContext(
            $addOfferSession?->response_payload ?? [],
            $priceLog
        );

        $travelerContexts = $this->resolveTravelerContexts(
            $paxes,
            $addTravelerSession?->response_payload ?? []
        );

        $mealItems       = [];
        $wheelchairItems = [];
        $seq             = 0;

        foreach ($travelerContexts as $ctx) {
            $pax = $ctx['pax'];
            $meal = trim((string) ($pax->meal_preference ?? ''));

            if ($meal !== '' && isset(self::MEAL_MAP[$meal])) {
                $seq++;
                $mealItems[] = $this->buildMealItem($ctx, self::MEAL_MAP[$meal], $offerCtx, $seq);
            }

            if ($pax->wheelchair_needed) {
                $seq++;
                $wheelchairItems[] = $this->buildWheelchairSsrItem($ctx, $offerCtx, $seq);
            }
        }

        if ($mealItems === [] && $wheelchairItems === []) {
            return [
                'skipped'             => true,
                'message'             => 'No special service requests to apply.',
                'travelport_response' => null,
            ];
        }

        $responses = [];

        if ($mealItems !== []) {
            $responses['meals'] = $this->postSpecialServiceList($workbenchId, $mealItems, $workbenchSession, $userId, 'add_ssr_meal');
        }

        if ($wheelchairItems !== []) {
            $responses['wheelchair'] = $this->postSpecialServiceList(
                $workbenchId,
                $wheelchairItems,
                $workbenchSession,
                $userId,
                'add_ssr_wheelchair'
            );
        }

        $attempt = BookingAttempt::find($workbenchSession->booking_attempt_id);
        if ($attempt) {
            $this->activityLogger->log(
                $attempt,
                BookingActivityLogger::ACTION_SSR_ADDED,
                $userId,
                [
                    'meal_count'       => count($mealItems),
                    'wheelchair_count' => count($wheelchairItems),
                ],
            );
        }

        return [
            'skipped'             => false,
            'message'             => 'Special service requests applied.',
            'travelport_response' => $responses,
        ];
    }

    private function postSpecialServiceList(
        string $workbenchId,
        array $items,
        BookingSession $workbenchSession,
        int|string|null $userId,
        string $sessionType
    ): array {
        $url     = $this->buildSsrUrl($workbenchId);
        $payload = [
            'SpecialServiceListRequest' => [
                'SpecialServiceID' => $items,
            ],
        ];

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

            if (!$response->successful() && $response->status() !== 204) {
                throw new Exception('Travelport add SSR failed. HTTP ' . $httpStatus);
            }

            $body = $response->json() ?? [];

            $errors = $body['SpecialServiceListResponse']['Result']['Error']
                ?? $body['Result']['Error']
                ?? [];

            if (!empty($errors)) {
                $firstMessage = is_array($errors[0] ?? null)
                    ? ($errors[0]['Message'] ?? 'Unknown error')
                    : (string) $errors[0];
                throw new Exception('Travelport add SSR error: ' . $firstMessage);
            }

            $this->sessionLogger->create([
                'user_id'              => $userId,
                'booking_attempt_id'   => $workbenchSession->booking_attempt_id,
                'booking_price_log_id' => $workbenchSession->booking_price_log_id,
                'session_type'         => $sessionType,
                'request_payload'      => $payload,
                'response_payload'     => $body ?: ['http_status' => $httpStatus],
                'identifier_value'     => $workbenchId,
                'provider'             => 'travelport_v2',
                'status'               => 'success',
                'http_status'          => $httpStatus,
                'created_by'           => $userId,
                'updated_by'           => $userId,
            ], $body ?: ['http_status' => $httpStatus]);

            return $body;
        } catch (Exception $e) {
            Log::error('TpV2AddSsrService::postSpecialServiceList failed', [
                'error'        => $e->getMessage(),
                'http_status'  => $httpStatus,
                'workbench'    => $workbenchId,
                'session_type' => $sessionType,
            ]);

            $this->sessionLogger->create([
                'user_id'              => $userId,
                'booking_attempt_id'   => $workbenchSession->booking_attempt_id,
                'booking_price_log_id' => $workbenchSession->booking_price_log_id,
                'session_type'         => $sessionType,
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

    private function buildMealItem(array $ctx, string $mealEnum, array $offerCtx, int $seq): array
    {
        return [
            '@type'              => 'SpecialServiceMeal',
            'id'                 => 'specialService_' . $seq,
            'Identifier'         => [
                'authority' => 'Travelport',
                'value'     => (string) Str::uuid(),
            ],
            'AppliesTo'          => $this->buildAppliesTo($offerCtx),
            'TravelerIdentifier' => $this->buildTravelerIdentifier($ctx),
            'SpecialMealTypeEnum' => $mealEnum,
        ];
    }

    private function buildWheelchairSsrItem(array $ctx, array $offerCtx, int $seq): array
    {
        return [
            '@type'              => 'SpecialService',
            'id'                 => 'specialService_' . $seq,
            'Identifier'         => [
                'authority' => 'Travelport',
                'value'     => (string) Str::uuid(),
            ],
            'AppliesTo'          => $this->buildAppliesTo($offerCtx),
            'TravelerIdentifier' => $this->buildTravelerIdentifier($ctx),
            'SSRCode'            => 'WCHR',
        ];
    }

    private function buildAppliesTo(array $offerCtx): array
    {
        return [
            '@type'           => 'AppliesToOffer',
            'OfferIdentifier' => [[
                'id'         => $offerCtx['id'],
                'offerRef'   => $offerCtx['offerRef'],
                'Identifier' => [
                    'authority' => $offerCtx['authority'],
                    'value'     => $offerCtx['value'],
                ],
            ]],
        ];
    }

    private function buildTravelerIdentifier(array $ctx): array
    {
        $refId = $ctx['traveler_ref_id'] ?? ('trav_' . ($ctx['index'] + 1));
        $uuid  = $ctx['traveler_uuid'] ?? null;

        $identifier = ['id' => $refId];
        if ($uuid) {
            $identifier['Identifier'] = ['value' => $uuid];
        }

        return $identifier;
    }

    private function resolveOfferContext(array $addOfferBody, BookingPriceLog $priceLog): array
    {
        $offer = $addOfferBody['OfferListResponse']['OfferID'][0]
            ?? $addOfferBody['OfferListResponse']['Offer'][0]
            ?? null;

        $offerId = is_array($offer) ? ($offer['id'] ?? 'offer_1') : 'offer_1';

        return [
            'id'        => $offerId,
            'offerRef'  => $offerId,
            'authority' => is_array($offer)
                ? ($offer['Identifier']['authority'] ?? 'Travelport')
                : 'Travelport',
            'value'     => is_array($offer)
                ? ($offer['Identifier']['value'] ?? (string) $priceLog->offer_identifier)
                : (string) $priceLog->offer_identifier,
        ];
    }

    private function resolveTravelerContexts($paxes, array $addTravelerBody): array
    {
        $tpTravelers = $this->normalizeTravelersFromResponse($addTravelerBody);
        $contexts    = [];

        foreach ($paxes as $index => $pax) {
            $tpTrav = $tpTravelers[$index] ?? [];
            $uuid   = $pax->travelport_traveler_id
                ?? $tpTrav['Identifier']['value']
                ?? null;

            $contexts[] = [
                'index'            => $index,
                'pax'            => $pax,
                'traveler_ref_id' => $tpTrav['id'] ?? ('trav_' . ($index + 1)),
                'traveler_uuid'  => $uuid,
            ];
        }

        return $contexts;
    }

    private function normalizeTravelersFromResponse(array $body): array
    {
        $travelers = $body['TravelerListResponse']['Traveler']
            ?? $body['TravelerResponse']['Traveler']
            ?? [];

        if (!is_array($travelers)) {
            return [];
        }

        if (isset($travelers['@type']) || isset($travelers['PersonName'])) {
            return [$travelers];
        }

        return array_values($travelers);
    }

    private function resolveWorkbenchSession(string $workbenchId, int $sessionId): BookingSession
    {
        $session = BookingSession::query()
            ->where('id', $sessionId)
            ->where('session_type', 'reservation_workbench')
            ->where('identifier_value', $workbenchId)
            ->where('status', 'success')
            ->first();

        if (!$session) {
            throw new Exception('Invalid or expired workbench session.');
        }

        return $session;
    }

    private function latestSession(string $workbenchId, string $sessionType): ?BookingSession
    {
        return BookingSession::query()
            ->where('identifier_value', $workbenchId)
            ->where('session_type', $sessionType)
            ->where('status', 'success')
            ->latest('id')
            ->first();
    }

    private function buildSsrUrl(string $workbenchId): string
    {
        $base    = rtrim((string) config('services.travelport_v2.base_url'), '/');
        $version = trim((string) config('services.travelport_v2.version'));

        return "{$base}/{$version}/air/book/specialservices/reservationworkbench/{$workbenchId}/specialservices/list";
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
