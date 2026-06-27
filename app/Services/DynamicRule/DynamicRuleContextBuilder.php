<?php

namespace App\Services\DynamicRule;

use App\Models\Agent\Agent;

class DynamicRuleContextBuilder
{
    public function buildFromSearch(array $form, ?int $agencyId, ?string $api = null): DynamicRuleMatchContext
    {
        $departure = strtoupper((string) ($form['from'] ?? ''));
        $arrival   = strtoupper((string) ($form['to'] ?? ''));
        $way       = (int) ($form['Way'] ?? 1);

        $agencyName  = null;
        $agencyType  = 'B2B';
        $agencyGroup = null;

        if ($agencyId) {
            $agent = Agent::query()->find($agencyId);
            if ($agent) {
                $agencyName  = (string) $agent->name;
                $agencyGroup = ! empty($agent->iata_number) ? 'IATA' : 'Non-IATA';
            }
        }

        return new DynamicRuleMatchContext(
            travelDate: (string) ($form['dep_date'] ?? now()->toDateString()),
            agencyId: $agencyId,
            agencyName: $agencyName,
            agencyType: $agencyType,
            agencyGroup: $agencyGroup,
            api: $api ?: (string) config('dynamic_rules.default_api', 'Travelport'),
            departureCode: $departure,
            arrivalCode: $arrival,
            airlineCodes: [],
            airlineNames: [],
            flightType: $this->resolveFlightType($departure, $arrival),
            wayType: $this->resolveWayType($way),
            cabinClass: (string) ($form['cabin_class'] ?? 'Economy'),
            isRoundTrip: $way === 2,
        );
    }

    public function buildForLeg(DynamicRuleMatchContext $base, array $leg): DynamicRuleMatchContext
    {
        $codes = [];
        $names = [];

        foreach ($leg['segments'] ?? [] as $segment) {
            $code = strtoupper((string) ($segment['carrier_code'] ?? ''));
            $name = (string) ($segment['airline_name'] ?? '');
            if ($code !== '') {
                $codes[] = $code;
            }
            if ($name !== '' && $name !== 'No Information') {
                $names[] = $name;
            }
        }

        if (empty($codes) && ! empty($leg['first_carrier_code'])) {
            $codes[] = strtoupper((string) $leg['first_carrier_code']);
        }

        if (empty($names) && ! empty($leg['first_airline_name'])) {
            $names[] = (string) $leg['first_airline_name'];
        }

        return new DynamicRuleMatchContext(
            travelDate: $base->travelDate,
            agencyId: $base->agencyId,
            agencyName: $base->agencyName,
            agencyType: $base->agencyType,
            agencyGroup: $base->agencyGroup,
            api: $base->api,
            departureCode: strtoupper((string) ($leg['origin'] ?? $base->departureCode)),
            arrivalCode: strtoupper((string) ($leg['destination'] ?? $base->arrivalCode)),
            airlineCodes: array_values(array_unique($codes)),
            airlineNames: array_values(array_unique($names)),
            flightType: $this->resolveFlightType(
                (string) ($leg['origin'] ?? $base->departureCode),
                (string) ($leg['destination'] ?? $base->arrivalCode)
            ),
            wayType: $base->wayType,
            cabinClass: (string) ($leg['cabin'] ?? $base->cabinClass),
            isRoundTrip: $base->isRoundTrip,
        );
    }

    private function resolveWayType(int $way): string
    {
        return match ($way) {
            2       => 'Round Trip',
            3       => 'Multi-City',
            default => 'One Way',
        };
    }

    private function resolveFlightType(string $departure, string $arrival): string
    {
        if ($departure === '' || $arrival === '') {
            return 'International';
        }

        $depCountry = $this->airportCountry($departure);
        $arrCountry = $this->airportCountry($arrival);

        if ($depCountry !== '' && $arrCountry !== '' && strcasecmp($depCountry, $arrCountry) === 0) {
            return 'Domestic';
        }

        $domesticCodes = array_map('strtoupper', config('dynamic_rules.domestic_airport_codes', []));
        if (
            in_array(strtoupper($departure), $domesticCodes, true)
            && in_array(strtoupper($arrival), $domesticCodes, true)
        ) {
            return 'Domestic';
        }

        return 'International';
    }

    private function airportCountry(string $code): string
    {
        // airports table has no country column — domestic detection uses config list only.
        return '';
    }
}
