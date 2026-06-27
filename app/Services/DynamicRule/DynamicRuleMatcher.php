<?php

namespace App\Services\DynamicRule;

use Carbon\Carbon;

class DynamicRuleMatcher
{
    public function findFirstMatch(array $rules, DynamicRuleMatchContext $context): ?array
    {
        foreach ($rules as $rule) {
            if ($this->matches($rule, $context)) {
                return $rule;
            }
        }

        return null;
    }

    public function matches(array $rule, DynamicRuleMatchContext $context): bool
    {
        if (! $this->matchesDate($rule, $context->travelDate)) {
            return false;
        }

        if ($context->agencyId !== null || $context->agencyName) {
            if (! $this->matchesScalar($rule['agency_type'] ?? null, $context->agencyType)) {
                return false;
            }

            if (! $this->matchesScalar($rule['agency_group'] ?? null, $context->agencyGroup)) {
                return false;
            }

            if (! $this->matchesAgencyLists($rule, $context)) {
                return false;
            }
        }

        if (! $this->matchesApi($rule['api'] ?? null, $context->api)) {
            return false;
        }

        if (! $this->matchesAirportList($rule['departure'] ?? [], $context->departureCode)) {
            return false;
        }

        if (! $this->matchesAirportList($rule['arrival'] ?? [], $context->arrivalCode)) {
            return false;
        }

        if (! $this->matchesAirlineLists($rule, $context)) {
            return false;
        }

        if (! $this->matchesSingleIncludeExclude(
            $rule['including_flight_type'] ?? null,
            $rule['excluding_flight_type'] ?? null,
            $context->flightType
        )) {
            return false;
        }

        if (! $this->matchesSingleIncludeExclude(
            $rule['including_way_type'] ?? null,
            $rule['excluding_way_type'] ?? null,
            $context->wayType
        )) {
            return false;
        }

        if (! $this->matchesSingleIncludeExclude(
            $rule['including_cabin_class'] ?? null,
            $rule['excluding_cabin_class'] ?? null,
            $context->cabinClass
        )) {
            return false;
        }

        return true;
    }

    private function matchesDate(array $rule, string $travelDate): bool
    {
        $date = Carbon::parse($travelDate)->startOfDay();

        if (! empty($rule['start_date'])) {
            $start = Carbon::parse($rule['start_date'])->startOfDay();
            if ($date->lt($start)) {
                return false;
            }
        }

        if (empty($rule['run_continuously']) && ! empty($rule['end_date'])) {
            $end = Carbon::parse($rule['end_date'])->endOfDay();
            if ($date->gt($end)) {
                return false;
            }
        }

        return true;
    }

    private function matchesApi(?string $ruleValue, ?string $contextValue): bool
    {
        $ruleValue = $this->normalizeApiToken($ruleValue);
        if ($ruleValue === '' || $this->isAllToken($ruleValue)) {
            return true;
        }

        $contextValue = $this->normalizeApiToken($contextValue);
        if ($contextValue === '') {
            return false;
        }

        if (strcasecmp($ruleValue, $contextValue) === 0) {
            return true;
        }

        // Admin API label often "Travelport UAPI" while search sends shorter "Travelport".
        $ruleLower    = strtolower($ruleValue);
        $contextLower = strtolower($contextValue);

        return str_contains($ruleLower, $contextLower) || str_contains($contextLower, $ruleLower);
    }

    private function normalizeApiToken(?string $value): string
    {
        return preg_replace('/\s+/', ' ', trim((string) $value));
    }

    private function matchesScalar(?string $ruleValue, ?string $contextValue): bool
    {
        $ruleValue = $this->normalizeToken($ruleValue);
        if ($ruleValue === '' || $this->isAllToken($ruleValue)) {
            return true;
        }

        $contextValue = $this->normalizeToken($contextValue);

        return $contextValue !== '' && strcasecmp($ruleValue, $contextValue) === 0;
    }

    private function matchesAgencyLists(array $rule, DynamicRuleMatchContext $context): bool
    {
        $including = $rule['including_agency'] ?? [];
        $excluding = $rule['excluding_agency'] ?? [];
        $agency    = $this->normalizeToken($context->agencyName);

        if ($agency === '') {
            return empty($including) || $this->listHasAll($including);
        }

        if ($this->listHasValue($excluding, $agency)) {
            return false;
        }

        if (empty($including) || $this->listHasAll($including)) {
            return true;
        }

        return $this->listHasValue($including, $agency);
    }

    private function matchesAirportList(array $ruleList, string $airportCode): bool
    {
        if (empty($ruleList) || $this->listHasAll($ruleList)) {
            return true;
        }

        $code = strtoupper(trim($airportCode));
        if ($code === '') {
            return false;
        }

        foreach ($ruleList as $item) {
            if ($this->normalizeAirportToken((string) $item) === $code) {
                return true;
            }
        }

        return false;
    }

    private function matchesAirlineLists(array $rule, DynamicRuleMatchContext $context): bool
    {
        $including = $rule['including_airline'] ?? [];
        $excluding = $rule['excluding_airline'] ?? [];

        $candidates = array_values(array_unique(array_filter([
            ...array_map('strtoupper', $context->airlineCodes),
            ...array_map(fn($n) => $this->normalizeToken($n), $context->airlineNames),
        ])));

        if (empty($candidates)) {
            return empty($including) || $this->listHasAll($including);
        }

        foreach ($candidates as $candidate) {
            if ($this->listHasAirline($excluding, $candidate, $context)) {
                return false;
            }
        }

        if (empty($including) || $this->listHasAll($including)) {
            return true;
        }

        foreach ($candidates as $candidate) {
            if ($this->listHasAirline($including, $candidate, $context)) {
                return true;
            }
        }

        return false;
    }

    private function matchesSingleIncludeExclude(?string $including, ?string $excluding, string $actual): bool
    {
        $actual = $this->normalizeToken($actual);
        if ($actual === '') {
            return true;
        }

        $excluding = $this->normalizeToken($excluding);
        if ($excluding !== '' && ! $this->isAllToken($excluding) && strcasecmp($excluding, $actual) === 0) {
            return false;
        }

        $including = $this->normalizeToken($including);
        if ($including === '' || $this->isAllToken($including)) {
            return true;
        }

        return strcasecmp($including, $actual) === 0;
    }

    private function listHasAirline(array $list, string $candidate, DynamicRuleMatchContext $context): bool
    {
        if ($this->listHasAll($list)) {
            return true;
        }

        foreach ($list as $item) {
            $token = $this->normalizeToken((string) $item);
            if ($token === '') {
                continue;
            }

            if (strcasecmp($token, $candidate) === 0) {
                return true;
            }

            // Rule stores airline name; context may pass IATA code.
            foreach ($context->airlineNames as $name) {
                if (
                    strcasecmp($this->normalizeToken($name), $token) === 0
                    && in_array(strtoupper($candidate), array_map('strtoupper', $context->airlineCodes), true)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function listHasAll(array $list): bool
    {
        foreach ($list as $item) {
            if ($this->isAllToken((string) $item)) {
                return true;
            }
        }

        return false;
    }

    private function listHasValue(array $list, string $value): bool
    {
        foreach ($list as $item) {
            if (strcasecmp($this->normalizeToken((string) $item), $value) === 0) {
                return true;
            }
        }

        return false;
    }

    private function normalizeAirportToken(string $value): string
    {
        $value = trim($value);
        if (str_contains($value, ' - ')) {
            return strtoupper(trim(explode(' - ', $value, 2)[0]));
        }

        return strtoupper($value);
    }

    private function normalizeToken(?string $value): string
    {
        return trim((string) $value);
    }

    private function isAllToken(string $value): bool
    {
        return strcasecmp(trim($value), 'all') === 0;
    }
}
