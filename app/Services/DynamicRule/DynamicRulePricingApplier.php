<?php

namespace App\Services\DynamicRule;

use App\Services\HashIdService;

class DynamicRulePricingApplier
{
    public function __construct(
        private readonly DynamicRuleCache $cache,
        private readonly DynamicRuleMatcher $matcher,
        private readonly DynamicRulePricingCalculator $calculator,
        private readonly DynamicRuleContextBuilder $contextBuilder,
    ) {}

    public function applyToFlights(array $flights, array $form, ?int $agencyId = null, ?string $api = null): array
    {
        $rules = $this->cache->getActiveRules();
        $base  = $this->contextBuilder->buildFromSearch($form, $agencyId, $api);

        foreach ($flights as $index => $flight) {
            $flights[$index] = $this->applyToFlight($flight, $rules, $base);
        }

        return $flights;
    }

    public function applyToPriceData(array $priceData, array $form, ?int $agencyId = null, ?string $api = null): array
    {
        $rules   = $this->cache->getActiveRules();
        $base    = $this->contextBuilder->buildFromSearch($form, $agencyId, $api);
        $carrier = strtoupper((string) ($priceData['validating_airline'] ?? ''));
        $context = new DynamicRuleMatchContext(
            travelDate: $base->travelDate,
            agencyId: $base->agencyId,
            agencyName: $base->agencyName,
            agencyType: $base->agencyType,
            agencyGroup: $base->agencyGroup,
            api: $base->api,
            departureCode: $base->departureCode,
            arrivalCode: $base->arrivalCode,
            airlineCodes: $carrier !== '' ? [$carrier] : [],
            airlineNames: [],
            flightType: $base->flightType,
            wayType: $base->wayType,
            cabinClass: (string) ($priceData['brand']['name'] ?? $base->cabinClass),
            isRoundTrip: $base->isRoundTrip,
        );

        $rule = $this->matcher->findFirstMatch($rules, $context);
        $fareInput = [
            'base_fare'   => (float) ($priceData['base_fare'] ?? 0),
            'total_taxes' => (float) ($priceData['total_taxes'] ?? 0),
            'gross_fare'  => (float) ($priceData['total_price'] ?? 0),
            'cabin_class' => (string) ($form['cabin_class'] ?? 'Economy'),
            'currency'    => (string) ($priceData['currency'] ?? 'BDT'),
            'tax_lines'   => $this->extractTaxLines($priceData),
        ];

        $pricing = $this->calculator->calculate($fareInput, $rule);
        $priceData['gross_fare']      = $pricing['gross_fare'];
        $priceData['total_payable']   = $pricing['total_payable'];
        $priceData['gross_payment']   = $pricing['gross_payment'];
        $priceData['dynamic_pricing'] = $this->publicPricing($pricing);
        $priceData['total_price']     = $pricing['gross_payment'];

        return $priceData;
    }

    private function extractTaxLines(array $priceData): array
    {
        $lines = [];
        foreach ($priceData['price_breakdown'] ?? [] as $row) {
            foreach ($row['taxes'] ?? [] as $tax) {
                $lines[] = [
                    'code'   => (string) ($tax['code'] ?? ''),
                    'amount' => (float) ($tax['amount'] ?? 0),
                ];
            }
        }

        return $lines;
    }

    private function applyToFlight(array $flight, array $rules, DynamicRuleMatchContext $base): array
    {
        if (! empty($flight['outbound'])) {
            $flight['outbound'] = $this->applyToLeg($flight['outbound'], $rules, $base);
        }

        if (! empty($flight['inbound'])) {
            $flight['inbound'] = $this->applyToLeg($flight['inbound'], $rules, $base);
        }

        return $flight;
    }

    private function applyToLeg(array $leg, array $rules, DynamicRuleMatchContext $base): array
    {
        $context = $this->contextBuilder->buildForLeg($base, $leg);
        $rule    = $this->matcher->findFirstMatch($rules, $context);

        if (! empty($leg['brand_options']) && is_array($leg['brand_options'])) {
            foreach ($leg['brand_options'] as $idx => $brand) {
                $leg['brand_options'][$idx] = $this->applyPricingToBrand($brand, $rule, $context);
            }
        }

        $defaultBrand = $leg['brand_options'][0] ?? null;
        if (is_array($defaultBrand) && ! empty($defaultBrand['dynamic_pricing'])) {
            $leg['dynamic_pricing'] = $defaultBrand['dynamic_pricing'];
            $leg['totalPrice']      = (float) ($defaultBrand['gross_payment'] ?? $leg['totalPrice']);
        }

        return $leg;
    }

    private function applyPricingToBrand(array $brand, ?array $rule, DynamicRuleMatchContext $context): array
    {
        $fareInput = $this->buildFareInput($brand, $context);
        $pricing   = $this->calculator->calculate($fareInput, $rule);

        $brand['gross_fare']      = $pricing['gross_fare'];
        $brand['total_payable']   = $pricing['total_payable'];
        $brand['gross_payment']   = $pricing['gross_payment'];
        $brand['dynamic_pricing'] = $this->publicPricing($pricing);

        // Keep selling price as gross payment for existing UI filters/sorts.
        $brand['price'] = $pricing['gross_payment'];

        return $brand;
    }

    private function buildFareInput(array $brand, DynamicRuleMatchContext $context): array
    {
        $breakdown  = is_array($brand['price_breakdown'] ?? null) ? $brand['price_breakdown'] : [];
        $baseFare   = 0.0;
        $totalTaxes = 0.0;

        foreach ($breakdown as $row) {
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $baseFare   += (float) ($row['baseFare'] ?? 0) * $qty;
            $totalTaxes += (float) ($row['taxes'] ?? 0) * $qty;
        }

        $grossFare = (float) ($brand['price'] ?? ($baseFare + $totalTaxes));
        if ($baseFare <= 0 && $grossFare > 0) {
            $baseFare = max(0, $grossFare - $totalTaxes);
        }

        return [
            'base_fare'   => $baseFare,
            'total_taxes' => $totalTaxes > 0 ? $totalTaxes : max(0, $grossFare - $baseFare),
            'gross_fare'  => $grossFare,
            'cabin_class' => (string) ($brand['cabin'] ?? $context->cabinClass),
            'currency'    => (string) ($brand['currency'] ?? 'BDT'),
            'tax_lines'   => [],
        ];
    }

    private function publicPricing(array $pricing): array
    {
        $payload = $pricing;
        if (! empty($pricing['rule_id'])) {
            $payload['rule_id'] = hashid_encode(HashIdService::DYNAMIC_RULE, (int) $pricing['rule_id']);
        }

        return $payload;
    }
}
