<?php

namespace App\Services\FareRule;

use App\Models\Agent\Agent;

// Turns a mapped search/price response into FareRuleContext objects and attaches `fare_pricing`
// (§9.1) to it. Called unconditionally by TravelportSearchService/TravelportPriceService — the
// only pricing path since Phase 13 (the old DynamicRule applier this used to run alongside,
// mutually exclusive via a flag, is gone).
class FareRuleSearchIntegration
{
    public function __construct(
        private readonly FareRuleCache $cache,
        private readonly FareRuleContextBuilder $contextBuilder,
        private readonly FareRulePricingApplier $applier,
        private readonly FareRuleCalculator $calculator,
        private readonly FareRuleMatcher $matcher,
        private readonly FarePrecedenceResolver $resolver,
        private readonly AirportCountryMap $countryMap,
    ) {}

    public function applyToFlights(array $flights, array $form, ?int $agencyId = null): array
    {
        $agent    = $agencyId ? Agent::find($agencyId) : null;
        $snapshot = $this->cache->snapshot();
        $weights  = config('FareRules.engine.specificity_weights', []);

        $contexts = [];
        foreach ($flights as $fi => $flight) {
            foreach (['outbound', 'inbound'] as $direction) {
                if (empty($flight[$direction])) {
                    continue;
                }

                foreach ($flight[$direction]['brand_options'] ?? [] as $bi => $brand) {
                    $contexts["{$fi}.{$direction}.{$bi}"] = $this->contextFromLeg($flight[$direction], $brand, $agent);
                }
            }
        }

        if ($contexts === []) {
            return $flights;
        }

        $run = $this->applier->applyCompact($snapshot, $contexts, $weights);

        foreach ($run['results'] as $key => $pricing) {
            [$fi, $direction, $bi] = explode('.', $key, 3);
            $brand = &$flights[(int) $fi][$direction]['brand_options'][(int) $bi];

            $brand['fare_pricing'] = $pricing;
            // Legacy flat keys + 'dynamic_pricing' container, mirrored from the new pricing —
            // every existing Vue component (§12.1) reads these directly today. This keeps them
            // correct with ZERO frontend changes required for the flag to go live safely; see
            // docs/FARE_RULE_ENGINE.md §"Phase 7" for the full field mapping.
            $brand = array_merge($brand, $this->legacyMirror($pricing, includeBreakdown: false));
            // Selling price used by search sort/filter — same role the old system's 'price'
            // overwrite played.
            $brand['price'] = $pricing['agency_pays'];
            unset($brand);
        }

        foreach ($flights as $fi => $flight) {
            foreach (['outbound', 'inbound'] as $direction) {
                if (empty($flight[$direction])) {
                    continue;
                }

                $defaultBrand = $flight[$direction]['brand_options'][0] ?? null;
                if (is_array($defaultBrand) && ! empty($defaultBrand['fare_pricing'])) {
                    $flights[$fi][$direction]['fare_pricing']   = $defaultBrand['fare_pricing'];
                    $flights[$fi][$direction]['dynamic_pricing'] = $defaultBrand['dynamic_pricing'];
                    $flights[$fi][$direction]['totalPrice']      = (float) $defaultBrand['fare_pricing']['agency_pays'];
                }
            }
        }

        return $flights;
    }

    public function applyToPriceData(array $priceData, array $form, ?int $agencyId = null): array
    {
        $agent    = $agencyId ? Agent::find($agencyId) : null;
        $snapshot = $this->cache->snapshot();
        $weights  = config('FareRules.engine.specificity_weights', []);

        $context  = $this->contextFromPriceData($priceData, $form, $agent);
        $memo     = new FareRuleResolutionMemo();
        $resolved = $memo->resolveForContext($snapshot, $this->matcher, $this->resolver, $context, $weights);
        $pricing  = $this->calculator->calculateFull($resolved, $context);

        $priceData['fare_pricing'] = $pricing;
        $priceData = array_merge($priceData, $this->legacyMirror($pricing, includeBreakdown: true));
        // Downstream (BookingPriceLog, payment/booking flow) reads total_price as the amount
        // charged — must carry the new agency_pays figure exactly as the old system carried
        // gross_payment here.
        $priceData['total_price'] = $pricing['agency_pays'];

        return $priceData;
    }

    // Reshapes a §9.1 pricing array into the OLD dynamic_pricing contract (flat keys +
    // 'dynamic_pricing' container), so every pre-Phase-7 component keeps reading correct numbers
    // without modification. Temporary compatibility layer — remove once the 7 components in
    // §12.1 are rewritten against `fare_pricing` directly (Phase 8+) and the old system is
    // dropped (plan §13).
    //
    // $includeBreakdown: search-flow pricing is compact (§7.7-F — no breakdown/applied[] built
    // for ~1,000 brands nobody expands), so the legacy pricing_breakdown/rule_name/rule_id fields
    // are only populated at price/confirm time, when `calculateFull()` ran. This means the
    // "View payable breakdown" click affordance in the search list does not appear under the new
    // engine the way it did under the old one — a deliberate, documented UX difference driven by
    // the §7.7 performance budget, not an oversight.
    private function legacyMirror(array $pricing, bool $includeBreakdown): array
    {
        $applied = $includeBreakdown ? ($pricing['applied'] ?? []) : [];
        $winner  = null;
        foreach ($applied as $entry) {
            if (($entry['role'] ?? null) === 'base') {
                $winner = $entry;
                break;
            }
        }
        $winner ??= $applied[0] ?? null;

        $legacy = [
            'gross_fare'      => $pricing['gross_fare'],
            'gross_payment'   => $pricing['gross_fare'], // §7.5 — new "gross" already excludes markup
            'total_payable'   => $pricing['agency_pays'],
            'commission'      => $pricing['comm_share'],
            'stoppage_discount' => $pricing['promo'],
            'service_charge'  => $pricing['service_fee'],
            'markup'          => $pricing['markup'],
            'ait'             => $pricing['ait'],
            'ait_precise'     => $pricing['ait_precise'] ?? null,
            'gross_for_ait'   => $pricing['gross_for_ait'],
            'net_fare'        => null, // old intermediate figure, never displayed — dropped per §9.1
            'currency'        => $pricing['currency'],
            'pricing_breakdown' => $includeBreakdown ? ($pricing['breakdown'] ?? []) : [],
            'rule_applied'    => $pricing['rule_applied'],
            'rule_name'       => $winner['name'] ?? null,
            'rule_id'         => $winner['rule_id'] ?? null,
        ];

        return ['dynamic_pricing' => $legacy] + $legacy;
    }

    private function contextFromLeg(array $leg, array $brand, ?Agent $agent): FareRuleContext
    {
        [$base, $totalTaxes, $taxLines] = $this->sumSearchBreakdown($brand['price_breakdown'] ?? []);

        $origin      = strtoupper((string) ($leg['origin'] ?? ''));
        $destination = strtoupper((string) ($leg['destination'] ?? ''));
        $carrier     = strtoupper((string) ($leg['first_carrier_code'] ?? ($leg['segments'][0]['carrier_code'] ?? '')));

        // Catalog Tax[] present → same AIT BD/UT/E5 path as price/confirm (modal match).
        // Empty Tax[] → tax_lines_exact false → full-gross AIT (conservative).
        $taxLinesExact = $taxLines !== [];

        return $this->contextBuilder->build([
            'carrier'        => $carrier,
            // This integration is only called from Travelport V2 search/price.
            'supplier'       => $this->liveSupplierKey(),
            'origin'         => $origin,
            'destination'    => $destination,
            'cabin'          => $brand['cabin'] ?? null,
            'booking_class'  => $brand['class_of_service'] ?? null,
            // Connection-domestic detection needs itinerary-segment logic not yet built
            // (documented gap since Phase 2/3) — 'none' is the conservative default.
            'onward'         => 'none',
            'scope'          => $this->countryMap->resolveScope($origin, $destination, 'none'),
            'flight_date'    => $leg['departure_date'] ?? null,
            'base'           => $base,
            'total_taxes'    => $totalTaxes,
            'yq'             => $taxLines['YQ'] ?? 0,
            'yr'             => $taxLines['YR'] ?? 0,
            'segments'       => max(1, count($leg['segments'] ?? [])),
            'currency'       => $brand['currency'] ?? 'BDT',
            'tax_lines'      => $taxLines,
            'tax_lines_exact' => $taxLinesExact,
        ], $agent);
    }

    private function contextFromPriceData(array $priceData, array $form, ?Agent $agent): FareRuleContext
    {
        $base = 0.0;
        $totalTaxes = 0.0;
        $taxLines = [];

        foreach ($priceData['price_breakdown'] ?? [] as $row) {
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $base       += (float) ($row['base_fare'] ?? 0) * $qty;
            $totalTaxes += (float) ($row['total_taxes'] ?? 0) * $qty;

            foreach ($row['taxes'] ?? [] as $tax) {
                $code = strtoupper((string) ($tax['code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $taxLines[$code] = ($taxLines[$code] ?? 0) + (float) ($tax['amount'] ?? 0) * $qty;
            }
        }

        $origin      = strtoupper((string) ($form['from'] ?? ''));
        $destination = strtoupper((string) ($form['to'] ?? ''));
        $carrier     = strtoupper((string) ($priceData['validating_airline'] ?? ''));

        return $this->contextBuilder->build([
            'carrier'        => $carrier,
            'supplier'       => $this->liveSupplierKey(),
            'origin'         => $origin,
            'destination'    => $destination,
            'cabin'          => $priceData['brand']['name'] ?? ($form['cabin_class'] ?? null),
            'booking_class'  => $this->bookingClassFromPriceData($priceData),
            'onward'         => 'none',
            'scope'          => $this->countryMap->resolveScope($origin, $destination, 'none'),
            'flight_date'    => $form['dep_date'] ?? null,
            'base'           => $base,
            'total_taxes'    => $totalTaxes,
            'yq'             => $taxLines['YQ'] ?? 0,
            'yr'             => $taxLines['YR'] ?? 0,
            'segments'       => $this->segmentCountFromPriceData($priceData),
            'currency'       => $priceData['currency'] ?? 'BDT',
            'tax_lines'      => $taxLines,
            'tax_lines_exact' => true,
        ], $agent);
    }

    // TravelportSearchService / TravelportPriceService are the only callers.
    private function liveSupplierKey(): string
    {
        return 'travelport_1g';
    }

    // PriceResponseMapper puts RBD on each product — first non-empty class is the priced brand.
    private function bookingClassFromPriceData(array $priceData): ?string
    {
        foreach ($priceData['products'] ?? [] as $product) {
            $cls = strtoupper(trim((string) ($product['class_of_service'] ?? '')));
            if ($cls !== '') {
                return $cls;
            }
        }

        return null;
    }

    // Mapper exposes stops per direction (segmentCount - 1). Sum both directions; never 0.
    private function segmentCountFromPriceData(array $priceData): int
    {
        $total = 0;
        foreach ($priceData['products'] ?? [] as $product) {
            $flight = $product['flight'] ?? null;
            if (! is_array($flight) || $flight === []) {
                continue;
            }
            $total += ((int) ($flight['stops'] ?? 0)) + 1;
        }

        return max(1, $total);
    }

    // Returns [base, totalTaxes, taxLinesByCode]. tax_lines from SearchResponseMapper.
    private function sumSearchBreakdown(array $breakdown): array
    {
        $base = 0.0;
        $totalTaxes = 0.0;
        $taxLines = [];

        foreach ($breakdown as $row) {
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $base       += (float) ($row['baseFare'] ?? 0) * $qty;
            $totalTaxes += (float) ($row['taxes'] ?? 0) * $qty;

            foreach ($row['tax_lines'] ?? [] as $tax) {
                $code = strtoupper((string) ($tax['code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $taxLines[$code] = ($taxLines[$code] ?? 0) + (float) ($tax['amount'] ?? 0) * $qty;
            }
        }

        return [$base, $totalTaxes, $taxLines];
    }
}
