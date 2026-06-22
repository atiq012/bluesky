<?php

namespace App\Services\SearchV2;

use Exception;
use Carbon\Carbon;
use App\Models\BookingAttempt;
use App\Models\BookingPax;
use App\Models\BookingPriceLog;
use App\Models\BookingSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SearchV2\BookingActivityLogger;

class TpV2AddTravelerService
{
    private const MAX_TRAVELERS = 9;

    private const NATIONALITY_CODES = [
        'Bangladeshi' => 'BD',
        'American'    => 'US',
        'Pakistani'   => 'PK',
        'Indian'      => 'IN',
    ];

    public function __construct(
        private readonly TravelportTokenService $tokenService,
        private readonly BookingSessionLogger $sessionLogger,
        private readonly BookingActivityLogger $activityLogger,
    ) {}

    public function addTravelers(array $params, int|string|null $userId = null): array
    {
        $workbenchId = $params['workbench_identifier'];
        $sessionId   = (int) $params['session_id'];
        $travelers   = $params['travelers'];

        if (count($travelers) > self::MAX_TRAVELERS) {
            throw new Exception('Maximum 9 travelers allowed per booking.');
        }

        $workbenchSession = BookingSession::query()
            ->where('id', $sessionId)
            ->where('session_type', 'reservation_workbench')
            ->where('identifier_value', $workbenchId)
            ->where('status', 'success')
            ->first();

        if (!$workbenchSession) {
            throw new Exception('Invalid or expired workbench session.');
        }

        $depDate = null;
        if ($workbenchSession->booking_price_log_id) {
            $priceLog = BookingPriceLog::find($workbenchSession->booking_price_log_id);
            $depDate  = $priceLog?->dep_date?->format('Y-m-d');
        }

        $this->validateBusinessRules($travelers, $depDate);

        $url     = $this->buildUrl($workbenchId);
        $payload = $this->buildTravelerListPayload($travelers, $workbenchId);

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
                throw new Exception('Travelport add traveler failed. HTTP ' . $httpStatus);
            }

            $body = $response->json();

            $tpErrors = $this->extractTravelportErrors($body);
            if ($tpErrors !== []) {
                throw new Exception('Travelport add traveler error: ' . $tpErrors[0]);
            }

            $travelerIds    = $this->extractTravelerIdentifiers($body);
            $travelerRefIds = $this->extractTravelerRefIds($body);

            if (count($travelerIds) < count($travelers)) {
                Log::warning('TpV2AddTravelerService::addTravelers identifier mismatch', [
                    'expected' => count($travelers),
                    'got'      => count($travelerIds),
                    'body'     => $body,
                ]);
                throw new Exception('Travelport returned fewer traveler identifiers than expected.');
            }

            $result = DB::transaction(function () use (
                $workbenchSession,
                $travelers,
                $travelerIds,
                $travelerRefIds,
                $body,
                $payload,
                $httpStatus,
                $userId
            ) {
                $paxIds = [];
                $tpIds  = [];

                $travelerSession = $this->sessionLogger->create([
                    'user_id'              => $userId,
                    'booking_attempt_id'   => $workbenchSession->booking_attempt_id,
                    'booking_price_log_id' => $workbenchSession->booking_price_log_id,
                    'session_type'         => 'add_traveler',
                    'request_payload'      => $payload,
                    'response_payload'     => $body,
                    'identifier_value'     => $workbenchSession->identifier_value,
                    'provider'             => 'travelport_v2',
                    'status'               => 'success',
                    'http_status'          => $httpStatus,
                    'created_by'           => $userId,
                    'updated_by'           => $userId,
                ], $body);

                foreach ($travelers as $index => $traveler) {
                    $tpId = $travelerIds[$index] ?? null;

                    $pax = BookingPax::create([
                        'booking_attempt_id' => $workbenchSession->booking_attempt_id,
                        'booking_session_id' => $travelerSession->id,
                        'traveller_id'           => $traveler['traveller_id'] ?? null,
                        'travelport_traveler_id' => $tpId,
                        'pax_type'               => $traveler['pax_type'],
                        'sequence'               => $traveler['sequence'],
                        'is_primary_contact'     => (bool) ($traveler['is_primary_contact'] ?? false),
                        'title'                  => $traveler['title'] ?? null,
                        'first_name'             => $traveler['first_name'],
                        'middle_name'            => $traveler['middle_name'] ?? null,
                        'last_name'              => $traveler['last_name'],
                        'dob'                    => $this->toSqlDate($traveler['dob']),
                        'gender'                 => $traveler['gender'] ?? null,
                        'nationality'            => $traveler['nationality'] ?? null,
                        'passport_number'        => $traveler['passport_number'] ?? null,
                        'passport_expiry_date'   => !empty($traveler['passport_expiry_date'])
                            ? $this->toSqlDate($traveler['passport_expiry_date'])
                            : null,
                        'frequent_flyer_number'  => $traveler['frequent_flyer_number'] ?? null,
                        'email'                  => $traveler['email'] ?? null,
                        'phone'                  => $traveler['phone'] ?? null,
                        'meal_preference'        => $traveler['meal_preference'] ?? null,
                        'wheelchair_needed'      => isset($traveler['wheelchair_needed'])
                            ? (bool) $traveler['wheelchair_needed']
                            : null,
                        'travelport_response'    => $this->travelerResponseSlice($body, $index),
                        'status'                 => 'success',
                        'created_by'             => $userId,
                        'updated_by'             => $userId,
                    ]);

                    $paxIds[] = $pax->id;
                    $tpIds[]  = $tpId;
                }

                return [
                    'pax_ids'                     => $paxIds,
                    'travelport_traveler_ids'     => $tpIds,
                    'travelport_traveler_ref_ids' => $travelerRefIds,
                    'travelport_response'         => $body,
                ];
            });

            $attempt = BookingAttempt::find($workbenchSession->booking_attempt_id);
            if ($attempt) {
                $this->activityLogger->log(
                    $attempt,
                    BookingActivityLogger::ACTION_TRAVELER_ADDED,
                    $userId,
                    ['traveler_count' => count($travelers)],
                );
            }

            return $result;
        } catch (Exception $e) {
            Log::error('TpV2AddTravelerService::addTravelers failed', [
                'error'       => $e->getMessage(),
                'http_status' => $httpStatus,
                'workbench'   => $workbenchId,
            ]);

            $this->sessionLogger->create([
                'user_id'              => $userId,
                'booking_attempt_id'   => $workbenchSession->booking_attempt_id,
                'booking_price_log_id' => $workbenchSession->booking_price_log_id,
                'session_type'         => 'add_traveler',
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

    private function validateBusinessRules(array $travelers, ?string $depDate = null): void
    {
        $hasInf       = false;
        $hasAdt       = false;
        $primaryCount = 0;
        $today        = Carbon::today();
        $departure    = $depDate ? Carbon::parse($depDate) : null;

        foreach ($travelers as $traveler) {
            $ptc = $traveler['pax_type'] ?? '';

            if ($ptc === 'INF') {
                $hasInf = true;
            }
            if ($ptc === 'ADT') {
                $hasAdt = true;
                if (!empty($traveler['is_primary_contact'])) {
                    $primaryCount++;
                }
            }

            if (in_array($ptc, ['CNN', 'INF'], true) && empty($traveler['dob'])) {
                throw new Exception("Date of birth is required for {$ptc} passengers.");
            }

            if (!empty($traveler['dob'])) {
                try {
                    $dob = Carbon::parse($traveler['dob']);
                } catch (\Exception) {
                    throw new Exception("Invalid date of birth format for {$ptc} passenger.");
                }

                if ($dob->gt($today)) {
                    throw new Exception("Birth date cannot be in the future for {$ptc} passenger.");
                }

                if ($departure !== null) {
                    if ($ptc === 'CNN') {
                        $ageAtTravel = $dob->diffInYears($departure);
                        if ($ageAtTravel < 2 || $ageAtTravel >= 12) {
                            throw new Exception(
                                "CNN passenger age must be 2–11 at travel date. "
                                . "Provided birth date gives age {$ageAtTravel} on departure."
                            );
                        }
                    }

                    if ($ptc === 'INF') {
                        $monthsAtTravel = $dob->diffInMonths($departure);
                        if ($monthsAtTravel >= 24) {
                            throw new Exception(
                                "INF passenger must be under 24 months at travel date. "
                                . "Provided birth date gives {$monthsAtTravel} months on departure."
                            );
                        }
                    }
                }
            }
        }

        if ($hasInf && !$hasAdt) {
            throw new Exception('At least one adult (ADT) is required when booking an infant (INF).');
        }

        if ($primaryCount > 1) {
            throw new Exception('Only one primary contact is allowed.');
        }
    }

    private function buildUrl(string $workbenchIdentifier): string
    {
        $base    = rtrim((string) config('services.travelport_v2.base_url'), '/');
        $version = trim((string) config('services.travelport_v2.version'));

        return "{$base}/{$version}/air/book/traveler/reservationworkbench/{$workbenchIdentifier}/travelers/list";
    }

    private function buildTravelerListPayload(array $travelers, string $workbenchId): array
    {
        $items = [];

        foreach ($travelers as $traveler) {
            $items[] = $this->buildTravelerItem($traveler);
        }

        return [
            'TravelerListRequest' => [
                '@type'    => 'TravelerListRequest',
                'Traveler' => $items,
            ],
        ];
    }

    private function buildTravelerItem(array $traveler): array
    {
        $ptc    = $traveler['pax_type'] ?? 'ADT';
        $gender = $this->toTravelportGender($traveler['gender'] ?? '');
        $dob    = !empty($traveler['dob']) ? $this->toTravelportDate($traveler['dob']) : null;

        $item = [
            '@type' => 'Traveler',
            'gender' => $gender,
            'PersonName' => $this->buildPersonName($traveler),
        ];

        $ptcCode = $this->toPassengerTypeCode($ptc);
        if ($ptcCode !== null) {
            $item['passengerTypeCode'] = $ptcCode;
        }
        if ($dob !== null) {
            $item['birthDate'] = $dob;
        }

        if (!empty($traveler['email'])) {
            $item['Email'] = [['value' => $traveler['email']]];
        }

        if (!empty($traveler['phone'])) {
            $item['Telephone'] = [[
                '@type'       => 'Telephone',
                'phoneNumber' => $traveler['phone'],
                'role'        => 'Home',
            ]];
        }

        if (!empty($traveler['passport_number'])) {
            $item['TravelDocument'] = [$this->buildTravelDocument($traveler, $gender, $dob)];
        }

        if (!empty($traveler['frequent_flyer_number'])) {
            $item['CustomerLoyalty'] = [[
                '@type' => 'CustomerLoyalty',
                'value' => $traveler['frequent_flyer_number'],
            ]];
        }

        return $item;
    }

    private function buildPersonName(array $traveler): array
    {
        $given   = $this->sanitizeTravelportName((string) ($traveler['first_name'] ?? ''));
        $surname = $this->sanitizeTravelportName((string) ($traveler['last_name'] ?? ''));

        if ($given === '' || $surname === '') {
            throw new Exception(
                'Passenger name must contain only letters (A–Z). Remove numbers and special characters from first and last name.'
            );
        }

        $name = [
            '@type'   => 'PersonNameDetail',
            'Given'   => $given,
            'Surname' => $surname,
        ];

        $prefix = $this->sanitizeTravelportName((string) ($traveler['title'] ?? ''));
        if ($prefix !== '') {
            $name['Prefix'] = $prefix;
        }

        $middle = $this->sanitizeTravelportName((string) ($traveler['middle_name'] ?? ''));
        if ($middle !== '') {
            $name['Middle'] = $middle;
        }

        return $name;
    }

    // GDS allows letters, spaces, hyphen, apostrophe only in passenger names.
    private function sanitizeTravelportName(string $name): string
    {
        $name = trim($name);
        $cleaned = preg_replace("/[^a-zA-Z\s\-']/u", '', $name) ?? '';

        return trim(preg_replace('/\s+/', ' ', $cleaned) ?? '');
    }

    private function buildTravelDocument(array $traveler, string $gender, ?string $dob): array
    {
        $doc = [
            '@type'    => 'TravelDocumentDetail',
            'docNumber' => $traveler['passport_number'],
            'docType'  => 'Passport',
            'issueCountry' => $this->toIssueCountry($traveler['nationality'] ?? ''),
            'PersonName' => $this->buildPersonName($traveler),
        ];

        if (!empty($traveler['passport_expiry_date'])) {
            $doc['expireDate'] = $this->toTravelportDate($traveler['passport_expiry_date']);
        }
        if ($dob !== null) {
            $doc['birthDate'] = $dob;
        }
        if ($gender !== '') {
            $doc['Gender'] = $gender;
        }

        return $doc;
    }

    private function toTravelportDate(string $display): string
    {
        $formats = ['d-M-Y', 'd-M-y', 'Y-m-d', 'd/m/Y'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, trim($display))->format('Y-m-d');
            } catch (Exception) {
                continue;
            }
        }

        return Carbon::parse($display)->format('Y-m-d');
    }

    private function toSqlDate(string $display): string
    {
        return $this->toTravelportDate($display);
    }

    private function toPassengerTypeCode(string $paxType): ?string
    {
        return match (strtoupper($paxType)) {
            'CNN', 'INF' => strtoupper($paxType),
            'ADT'        => null,
            default      => null,
        };
    }

    private function toIssueCountry(string $nationality): string
    {
        return self::NATIONALITY_CODES[$nationality] ?? strtoupper(substr($nationality, 0, 2));
    }

    private function toTravelportGender(string $gender): string
    {
        return match ($gender) {
            'Male'   => 'Male',
            'Female' => 'Female',
            default  => 'Unknown',
        };
    }

    private function extractTravelportErrors(array $body): array
    {
        $messages = [];

        foreach (['TravelerListResponse', 'TravelerResponse'] as $rootKey) {
            $errors = $body[$rootKey]['Result']['Error'] ?? [];
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

        return $messages;
    }

    private function normalizeTravelersFromResponse(array $body): array
    {
        $listResponse = $body['TravelerListResponse'] ?? null;

        if (is_array($listResponse)) {
            $travelerIds = $listResponse['TravelerID'] ?? null;
            if ($travelerIds !== null) {
                return $this->normalizeTravelerIdList($travelerIds);
            }

            $travelers = $listResponse['Traveler'] ?? null;

            if ($travelers !== null) {
                return $this->normalizeTravelerList($travelers);
            }

            $referenceList = $listResponse['ReferenceList'] ?? [];
            if (isset($referenceList['@type'])) {
                $referenceList = [$referenceList];
            }

            $merged = [];
            foreach ($referenceList as $reference) {
                if (!is_array($reference)) {
                    continue;
                }
                $chunk = $reference['Traveler'] ?? null;
                if ($chunk !== null) {
                    $merged = array_merge($merged, $this->normalizeTravelerList($chunk));
                }
            }

            if ($merged !== []) {
                return $merged;
            }
        }

        $single = $body['TravelerResponse']['Traveler'] ?? null;

        return $single !== null ? $this->normalizeTravelerList($single) : [];
    }

    private function normalizeTravelerList(mixed $travelers): array
    {
        if (!is_array($travelers)) {
            return [];
        }

        if (isset($travelers['Identifier']) || (isset($travelers['@type']) && !array_is_list($travelers))) {
            return [$travelers];
        }

        return array_values($travelers);
    }

    private function normalizeTravelerIdList(mixed $travelerIds): array
    {
        if (!is_array($travelerIds)) {
            return [];
        }

        if (isset($travelerIds['Identifier']) || isset($travelerIds['id'])) {
            return [$travelerIds];
        }

        return array_values($travelerIds);
    }

    private function extractTravelerIdentifiers(array $body): array
    {
        $ids = [];
        foreach ($this->normalizeTravelersFromResponse($body) as $traveler) {
            $ids[] = $traveler['Identifier']['value']
                ?? $traveler['identifier']['value']
                ?? null;
        }

        return array_values(array_filter($ids));
    }

    private function extractTravelerRefIds(array $body): array
    {
        $refs = [];
        foreach ($this->normalizeTravelersFromResponse($body) as $traveler) {
            $refs[] = $traveler['id']
                ?? $traveler['TravelerRef']
                ?? null;
        }

        return array_values(array_filter($refs));
    }

    private function travelerResponseSlice(array $body, int $index): ?array
    {
        $travelers = $this->normalizeTravelersFromResponse($body);

        return $travelers[$index] ?? null;
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
