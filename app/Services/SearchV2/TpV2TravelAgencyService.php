<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TpV2TravelAgencyService
{
    public function __construct(
        private readonly TravelportTokenService $tokenService,
        private readonly BookingSessionLogger $sessionLogger
    ) {}

    public function save(array $params, int|string|null $userId = null): array
    {
        $workbenchId = $params['workbench_identifier'];
        $sessionId   = (int) $params['session_id'];
        $agency      = $params['agency'] ?? [];

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
        $payload = $this->buildPayload($agency);

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
            $body       = $this->decodeResponseBody($response);

            if (!$response->successful() && !in_array($httpStatus, [200, 201, 204], true)) {
                throw new Exception($this->formatHttpFailure($httpStatus, $body, (string) $response->body()));
            }

            $errors = $this->extractTravelportErrors($body);
            if ($errors !== []) {
                throw new Exception('Travelport travel agency error: ' . $errors[0]);
            }

            $this->sessionLogger->create([
                'user_id'              => $userId,
                'booking_attempt_id'   => $workbenchSession->booking_attempt_id,
                'booking_price_log_id' => $workbenchSession->booking_price_log_id,
                'session_type'         => 'add_travel_agency',
                'request_payload'      => $payload,
                'response_payload'     => $body ?: ['http_status' => $httpStatus],
                'identifier_value'     => $workbenchId,
                'provider'             => 'travelport_v2',
                'status'               => 'success',
                'http_status'          => $httpStatus,
                'created_by'           => $userId,
                'updated_by'           => $userId,
            ], $body ?: ['http_status' => $httpStatus]);

            return [
                'travelport_response' => $body,
            ];
        } catch (Exception $e) {
            Log::error('TpV2TravelAgencyService::save failed', [
                'error'       => $e->getMessage(),
                'http_status' => $httpStatus,
                'workbench'   => $workbenchId,
            ]);

            $this->sessionLogger->create([
                'user_id'              => $userId,
                'booking_attempt_id'   => $workbenchSession->booking_attempt_id,
                'booking_price_log_id' => $workbenchSession->booking_price_log_id,
                'session_type'         => 'add_travel_agency',
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

    private function buildPayload(array $agency): array
    {
        $query = [];

        $name = trim((string) ($agency['name'] ?? ''));
        if ($name !== '') {
            $address = [
                '@type'     => 'Address',
                'Addressee' => $name,
            ];

            $line = trim((string) ($agency['address_line'] ?? ''));
            if ($line !== '') {
                $address['AddressLine'] = [$line];
            }

            $city = trim((string) ($agency['city'] ?? ''));
            if ($city !== '') {
                $address['City'] = $city;
            }

            $country = strtoupper(trim((string) ($agency['country_code'] ?? 'BD')));
            $address['Country'] = ['value' => $country];

            $query['Address'] = $address;
        }

        $phone = trim((string) ($agency['phone'] ?? ''));
        if ($phone !== '') {
            $telephone = ['role' => 'Office', 'phoneNumber' => $phone];
            $parsed    = $this->parsePhone($phone, (string) ($agency['country_code'] ?? 'BD'));
            if ($parsed !== null) {
                $telephone = array_merge($telephone, $parsed);
            }
            $query['Telephone'] = [$telephone];
        }

        $email = trim((string) ($agency['email'] ?? ''));
        if ($email !== '') {
            $query['Email'] = ['value' => $email];
        }

        $iata = trim((string) ($agency['iata_number'] ?? ''));
        if ($iata !== '') {
            $query['CorporateCode'] = $iata;
        }

        if ($query === []) {
            throw new Exception('At least one travel agency field is required.');
        }

        return [
            'TravelAgencyQueryTravelAgencyWrapper' => [
                'TravelAgencyQueryTravelAgency' => $query,
            ],
        ];
    }

    private function parsePhone(string $phone, string $countryCode): ?array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        $countryCode = strtoupper(trim($countryCode));

        if ($countryCode === 'BD') {
            if (str_starts_with($digits, '880')) {
                return [
                    'countryAccessCode' => '880',
                    'phoneNumber'       => substr($digits, 3) ?: $digits,
                ];
            }

            if (str_starts_with($digits, '0')) {
                return [
                    'countryAccessCode' => '880',
                    'phoneNumber'       => ltrim($digits, '0'),
                ];
            }
        }

        return null;
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
        $tpError = $this->extractTravelportErrors($body);
        if ($tpError !== []) {
            return 'Travelport travel agency error: ' . $tpError[0];
        }

        if (isset($body['_raw_body']) && str_contains($body['_raw_body'], '<html')) {
            return 'Travelport travel agency save failed. HTTP ' . $httpStatus
                . ' (invalid request body — ensure TravelAgencyQueryTravelAgencyWrapper is sent).';
        }

        $snippet = $rawBody !== '' ? ': ' . substr(preg_replace('/\s+/', ' ', $rawBody), 0, 200) : '';

        return 'Travelport travel agency save failed. HTTP ' . $httpStatus . $snippet;
    }

    private function extractTravelportErrors(array $body): array
    {
        $messages   = [];
        $candidates = [
            $body['TravelAgencyResponse']['Result']['Error'] ?? null,
            $body['ErrorResponse']['Result']['Error'] ?? null,
            $body['Result']['Error'] ?? null,
        ];

        foreach ($candidates as $errors) {
            if (!is_array($errors) || $errors === []) {
                continue;
            }
            if (isset($errors['Message'])) {
                $errors = [$errors];
            }
            foreach ($errors as $error) {
                $msg = trim((string) ($error['Message'] ?? ''));
                if ($msg !== '') {
                    $messages[] = $msg;
                }
            }
        }

        return array_values(array_unique($messages));
    }

    private function buildUrl(string $workbenchId): string
    {
        $base    = rtrim((string) config('services.travelport_v2.base_url'), '/');
        $version = trim((string) config('services.travelport_v2.version'));

        return "{$base}/{$version}/air/ticket/travelagency/reservationworkbench/{$workbenchId}/travelagency";
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
