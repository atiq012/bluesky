<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingAttempt;
use App\Models\BookingSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SearchV2\BookingActivityLogger;

class TpV2BookAncillaryService
{
    public function __construct(
        private readonly TravelportTokenService $tokenService,
        private readonly BookingSessionLogger $sessionLogger,
        private readonly BookingActivityLogger $activityLogger,
    ) {}

    public function book(array $params, int|string|null $userId = null): array
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

        $url     = $this->buildUrl($workbenchId);
        $payload = $this->buildPayload($params['ancillaries'] ?? []);

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
                throw new Exception('Travelport book ancillary failed. HTTP ' . $httpStatus);
            }

            $body   = $response->json();
            $errors = $body['OfferListResponse']['Result']['Error'] ?? [];

            if (!empty($errors)) {
                $firstMessage = $errors[0]['Message'] ?? 'Unknown error';
                throw new Exception('Travelport book ancillary error: ' . $firstMessage);
            }

            $this->sessionLogger->create([
                'user_id'              => $userId,
                'booking_attempt_id'   => $workbenchSession->booking_attempt_id,
                'booking_price_log_id' => $workbenchSession->booking_price_log_id,
                'session_type'         => 'add_ancillary',
                'request_payload'      => $payload,
                'response_payload'     => $body,
                'identifier_value'     => $workbenchId,
                'provider'             => 'travelport_v2',
                'status'               => 'success',
                'http_status'          => $httpStatus,
                'created_by'           => $userId,
                'updated_by'           => $userId,
            ], $body);

            $attempt = BookingAttempt::find($workbenchSession->booking_attempt_id);
            if ($attempt) {
                $this->activityLogger->log(
                    $attempt,
                    BookingActivityLogger::ACTION_ANCILLARY_ADDED,
                    $userId,
                    ['ancillary_count' => count($params['ancillaries'] ?? [])],
                );
            }

            return [
                'travelport_response' => $body,
            ];
        } catch (Exception $e) {
            Log::error('TpV2BookAncillaryService::book failed', [
                'error'       => $e->getMessage(),
                'http_status' => $httpStatus,
                'workbench'   => $workbenchId,
            ]);

            $this->sessionLogger->create([
                'user_id'              => $userId,
                'booking_attempt_id'   => $workbenchSession->booking_attempt_id,
                'booking_price_log_id' => $workbenchSession->booking_price_log_id,
                'session_type'         => 'add_ancillary',
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

    private function buildPayload(array $ancillaries): array
    {
        $items = [];

        foreach ($ancillaries as $anc) {
            $items[] = [
                '@type'                       => 'BuildAncillaryOffersFromCatalogOfferings',
                'CatalogOfferingsIdentifier'  => [
                    'id'         => $anc['catalog_offerings_group_id'],
                    'Identifier' => [
                        'authority' => $anc['catalog_offerings_identifier_authority'] ?? 'Travelport',
                        'value'     => $anc['catalog_offerings_identifier_value'],
                    ],
                ],
                'CatalogOfferingIdentifier'   => [
                    'id' => $anc['catalog_offering_id'],
                ],
                'ProductIdentifier'           => [
                    'id' => $anc['product_id'],
                ],
                'TravelerIdentifierRef'       => [
                    'id' => $anc['traveler_ref_id'],
                ],
                'Quantity'                    => (int) ($anc['quantity'] ?? 1),
            ];
        }

        return [
            'OfferQueryBuildAncillaryOffersFromCatalogOfferings' => [
                'BuildAncillaryOffersFromCatalogOfferings' => $items,
            ],
        ];
    }

    private function buildUrl(string $workbenchId): string
    {
        $base    = rtrim((string) config('services.travelport_v2.base_url'), '/');
        $version = trim((string) config('services.travelport_v2.version'));

        return "{$base}/{$version}/air/book/airoffer/reservationworkbench/{$workbenchId}/offers/buildancillaryoffersfromcatalogofferings";
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
