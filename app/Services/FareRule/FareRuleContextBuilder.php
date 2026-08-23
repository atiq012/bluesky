<?php

namespace App\Services\FareRule;

use App\Models\Agent\Agent;

// Turns raw leg/brand fields (already resolved by the caller — scope/onward come from
// AirportCountryMap once that exists, Phase 3/7) plus an optional agent into a FareRuleContext.
class FareRuleContextBuilder
{
    public function build(array $fields, ?Agent $agent = null): FareRuleContext
    {
        $bookingClass = $this->blankToNull($fields['booking_class'] ?? null);

        return new FareRuleContext(
            carrier: strtoupper(trim((string) ($this->blankToNull($fields['carrier'] ?? null) ?? ''))),
            supplier: $this->resolveSupplierKey($this->blankToNull($fields['supplier'] ?? null)),
            origin: strtoupper(trim((string) ($this->blankToNull($fields['origin'] ?? null) ?? ''))),
            destination: strtoupper(trim((string) ($this->blankToNull($fields['destination'] ?? null) ?? ''))),
            cabin: $this->blankToNull($fields['cabin'] ?? null),
            bookingClass: $bookingClass !== null ? strtoupper($bookingClass) : null,
            onward: $this->scalarOrDefault($fields['onward'] ?? null, $fields, 'none'),
            scope: $this->scalarOrDefault($fields['scope'] ?? null, $fields, 'domestic'),
            // HTML date inputs send "" not null — empty must not enter travel-window compares.
            flightDate: $this->blankToNull($fields['flight_date'] ?? null),
            hasAgent: $agent !== null,
            agentId: $agent?->id,
            agentTier: $agent !== null ? ($agent->tier ?? config('FareRules.tiers.default')) : null,
            promoCode: $this->blankToNull($fields['promo_code'] ?? null),
            feeEvent: $this->scalarOrDefault($fields['fee_event'] ?? null, $fields, 'issue'),
            taxLines: $fields['tax_lines'] ?? [],
            taxLinesExact: (bool) ($fields['tax_lines_exact'] ?? false),
            unspecifiedMeansAny: (bool) ($fields['unspecified_means_any'] ?? false),
            base: (float) ($fields['base'] ?? 0),
            yq: (float) ($fields['yq'] ?? 0),
            yr: (float) ($fields['yr'] ?? 0),
            totalTaxes: (float) ($fields['total_taxes'] ?? 0),
            segments: (int) ($fields['segments'] ?? 1),
            currency: (string) ($fields['currency'] ?? 'BDT'),
        );
    }

    // Simulator blank = Any (empty string). Search always has a real value, so fall back.
    private function scalarOrDefault(mixed $value, array $fields, string $searchDefault): string
    {
        $trimmed = $this->blankToNull($value);
        if ($trimmed !== null) {
            return $trimmed;
        }

        return ! empty($fields['unspecified_means_any']) ? '' : $searchDefault;
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    // Old matcher's 'Travelport <-> Travelport UAPI' fix, carried forward as config data
    // (config/FareRules/suppliers.php aliases) instead of a hardcoded str_contains.
    public function resolveSupplierKey(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $suppliers = config('FareRules.suppliers', []);

        if (isset($suppliers[$raw])) {
            return $raw;
        }

        foreach ($suppliers as $key => $supplier) {
            foreach ($supplier['aliases'] ?? [] as $alias) {
                if (strcasecmp($alias, $raw) === 0) {
                    return $key;
                }
            }
        }

        return null;
    }
}
