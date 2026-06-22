<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class TravelportFareRulesService
{
    public function __construct(
        private readonly TravelportTokenService $tokenService
    ) {}

    public function getFareRules(array $params, int|string|null $userId = null, string $direction = 'outbound', int|string|null $bookingAttemptId = null): array
    {
        $fixture = $this->loadFixture($direction);
        if ($fixture !== null) {
            $downloadKey = $this->persistFiles($params, $fixture, $userId);
            $this->saveBookingSession($params, $fixture, $userId, $bookingAttemptId, $direction);
            return array_merge($this->normalize($fixture), ['download_key' => $downloadKey]);
        }

        $baseUrl = rtrim((string) config('services.travelport_v2.base_url'), '/');
        $version = trim((string) config('services.travelport_v2.version'), '/');
        $url     = "{$baseUrl}/{$version}/air/farerule/farerules/fromcatalogproductofferings";

        $token   = $this->tokenService->getAccessToken();
        $headers = $this->buildHeaders();

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->acceptJson()
            ->withToken($token)
            ->get($url, $params);

        if ($response->status() === 401) {
            $token    = $this->tokenService->getAccessToken(true);
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->acceptJson()
                ->withToken($token)
                ->get($url, $params);
        }

        if (!$response->successful()) {
            throw new Exception(
                'Fare rules request failed: ' . $response->status() . ' | ' . $response->body()
            );
        }

        $rawResponse = $response->json() ?? [];
        $downloadKey = $this->persistFiles($params, $rawResponse, $userId);
        $this->saveBookingSession($params, $rawResponse, $userId, $bookingAttemptId, $direction);

        return array_merge(
            $this->normalize($rawResponse),
            ['download_key' => $downloadKey]
        );
    }

    public function getDownloadFiles(string $downloadKey, int|string|null $userId): array
    {
        // Key format: "fare-rules/{dmy}/{timestamp}--{userId}"
        $parts = explode('/', $downloadKey);
        if (count($parts) !== 3 || $parts[0] !== 'fare-rules') {
            throw new Exception('Invalid download key.');
        }

        $filePrefix  = $parts[2];
        $separatorPos = strrpos($filePrefix, '--');
        $keyUserId   = $separatorPos !== false ? substr($filePrefix, $separatorPos + 2) : null;

        if ((string) $keyUserId !== (string) ($userId ?? 'guest')) {
            throw new Exception('Unauthorized.');
        }

        $base         = storage_path('app/' . $downloadKey);
        $payloadPath  = $base . '_payload.json';
        $responsePath = $base . '_response.json';

        if (!File::exists($payloadPath) || !File::exists($responsePath)) {
            throw new Exception('Download files not found.');
        }

        return [
            'payload'  => json_decode(File::get($payloadPath), true),
            'response' => json_decode(File::get($responsePath), true),
        ];
    }

    private function saveBookingSession(array $params, array $rawResponse, int|string|null $userId, int|string|null $bookingAttemptId, string $direction): void
    {
        if (!$bookingAttemptId) {
            return;
        }

        $sessionType = $direction === 'inbound' ? 'fare_rules_inbound' : 'fare_rules_outbound';

        BookingSession::create([
            'user_id'            => $userId,
            'booking_attempt_id' => $bookingAttemptId,
            'session_type'       => $sessionType,
            'request_payload'    => $params,
            'response_payload'   => $rawResponse,
            'provider'           => 'travelport_v2',
            'status'             => 'success',
            'http_status'        => 200,
            'created_by'         => $userId,
            'updated_by'         => $userId,
        ]);
    }

    private function persistFiles(array $payload, array $rawResponse, int|string|null $userId): string
    {
        $dmy       = now()->format('dmy');
        $timestamp = now()->format('Ymd_His_u');
        $uid       = (string) ($userId ?? 'guest');
        $prefix    = "{$timestamp}--{$uid}";

        $dir = storage_path("app/fare-rules/{$dmy}");
        File::makeDirectory($dir, 0755, true, true);

        File::put("{$dir}/{$prefix}_payload.json",  json_encode($payload,     JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put("{$dir}/{$prefix}_response.json", json_encode($rawResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return "fare-rules/{$dmy}/{$prefix}";
    }

    private function buildHeaders(): array
    {
        $version     = (string) config('services.travelport_v2.version', '11');
        $accessGroup = (string) config('services.travelport_v2.access_group', '');

        return [
            'Accept'                       => 'application/json',
            'Content-Type'                 => 'application/json',
            'XAUTH_TRAVELPORT_ACCESSGROUP' => $accessGroup,
            'Accept-Version'               => $version,
            'Content-Version'              => $version,
        ];
    }

    private function normalize(array $raw): array
    {
        $fareRules = $raw['FareRuleListResponse']['FareRule'] ?? [];
        if (!empty($fareRules) && !array_is_list($fareRules)) {
            $fareRules = [$fareRules];
        }

        $segments = [];

        foreach ($fareRules as $rule) {
            $flightRef  = $rule['Flight'][0]['FlightRef'] ?? 'unknown';
            $structured = $rule['StructuredFareRules'] ?? [];

            $seg = [
                'flightRef'       => $flightRef,
                'cancellation'    => [],
                'changes'         => [],
                'advance_booking' => null,
                'min_stay'        => null,
                'max_stay'        => null,
                'stopover'        => null,
            ];

            foreach ($structured as $item) {
                foreach ($item['Penalties'] ?? [] as $penalty) {
                    foreach (['Cancel', 'Change'] as $penaltyType) {
                        if (!isset($penalty[$penaltyType])) continue;
                        $detail    = $penalty[$penaltyType][0] ?? [];
                        $type      = $detail['@type'] ?? '';
                        $permitted = !str_contains($type, 'NotPermitted');
                        $timings   = $detail['penaltyTypes'] ?? ['General'];
                        $taxes     = $detail['taxesNonRefundableInd'] ?? false;

                        $amt = $detail['Penalty'][0]['Amount'] ?? null;
                        $amount = $amt ? ['value' => $amt['value'] ?? 0, 'code' => $amt['code'] ?? ''] : null;

                        foreach ($timings as $timing) {
                            $entry = ['timing' => $timing, 'permitted' => $permitted, 'amount' => $amount];
                            if ($penaltyType === 'Cancel') {
                                $entry['taxes_refundable'] = !$taxes;
                                $seg['cancellation'][] = $entry;
                            } else {
                                $seg['changes'][] = $entry;
                            }
                        }
                    }
                }

                if (!empty($item['MinimumStay'])) {
                    $ms = $item['MinimumStay'][0] ?? [];
                    $seg['min_stay'] = isset($ms['IndeterminateInd'])
                        ? 'No restriction'
                        : $this->parseDuration($ms['Duration'] ?? null);
                }

                if (!empty($item['MaximumStay'])) {
                    $ms = $item['MaximumStay'][0] ?? [];
                    $seg['max_stay'] = isset($ms['IndeterminateInd'])
                        ? 'No restriction'
                        : $this->parseDuration($ms['Duration'] ?? $ms['maximumStayDuration'] ?? null);
                }

                if (!empty($item['AdvanceReservation'])) {
                    $ar       = $item['AdvanceReservation'][0] ?? [];
                    $duration = $this->parseDuration($ar['LastReservation']['Duration'] ?? null);
                    if ($duration) {
                        $seg['advance_booking']['book_by'] = $duration . ' before departure';
                    }
                }

                if (!empty($item['AdvancePayment'])) {
                    $ap        = $item['AdvancePayment'][0] ?? [];
                    $payAfter  = $this->parseDuration($ap['PaymentAfterReservation']['Duration'] ?? null);
                    $payBefore = $this->parseDuration($ap['PaymentBeforeDeparture']['Duration'] ?? null);
                    if ($payAfter)  $seg['advance_booking']['pay_after_booking']    = $payAfter . ' after booking';
                    if ($payBefore) $seg['advance_booking']['pay_before_departure'] = $payBefore . ' before departure';
                }

                if (!empty($item['Stopover'])) {
                    $st = $item['Stopover'][0] ?? [];
                    $seg['stopover'] = !isset($st['NotPermittedInd']);
                }
            }

            $seg['cancellation'] = $this->dedup($seg['cancellation']);
            $seg['changes']      = $this->dedup($seg['changes']);

            $segments[] = $seg;
        }

        return ['segments' => $segments];
    }

    private function dedup(array $entries): array
    {
        $seen   = [];
        $result = [];
        foreach ($entries as $entry) {
            $amtVal  = $entry['amount']['value'] ?? 'null';
            $amtCode = $entry['amount']['code']  ?? '';
            $key     = $entry['timing'] . '|' . $amtVal . '|' . $amtCode;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $result[]   = $entry;
        }
        return $result;
    }

    private function parseDuration(?string $iso): ?string
    {
        if (!$iso) return null;
        preg_match('/P(?:(\d+)Y)?(?:(\d+)M(?!in))?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?)?/', $iso, $m);
        $parts = [];
        if (!empty($m[1])) $parts[] = $m[1] . ' year'  . ((int) $m[1] > 1 ? 's' : '');
        if (!empty($m[2])) $parts[] = $m[2] . ' month' . ((int) $m[2] > 1 ? 's' : '');
        if (!empty($m[3])) $parts[] = $m[3] . ' day'   . ((int) $m[3] > 1 ? 's' : '');
        if (!empty($m[4])) $parts[] = $m[4] . ' hr';
        if (!empty($m[5])) $parts[] = $m[5] . ' min';
        return implode(' ', $parts) ?: null;
    }

    private function loadFixture(string $direction = 'outbound'): ?array
    {
        $key     = $direction === 'inbound' ? 'fare_rules_inbound_fixture' : 'fare_rules_outbound_fixture';
        $fixture = trim((string) config("services.travelport_v2.{$key}", ''));
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
