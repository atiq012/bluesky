<?php

namespace App\Services\DynamicRule;

class DynamicRulePricingCalculator
{
    public function calculate(array $fareInput, ?array $rule): array
    {
        $baseFare   = round((float) ($fareInput['base_fare'] ?? 0), 2);
        $totalTaxes = round((float) ($fareInput['total_taxes'] ?? 0), 2);
        $grossFare  = round((float) ($fareInput['gross_fare'] ?? ($baseFare + $totalTaxes)), 2);
        $cabinClass = (string) ($fareInput['cabin_class'] ?? 'Economy');
        $taxLines   = is_array($fareInput['tax_lines'] ?? null) ? $fareInput['tax_lines'] : [];

        if ($rule === null) {
            return $this->emptyPricing($grossFare, $baseFare, $totalTaxes);
        }

        $commission     = $this->resolveAmount($baseFare, $rule['commission_value'] ?? null, $rule['commission_type'] ?? 'percent');
        $extraCommission  = $this->resolveExtraCommission($baseFare, $cabinClass, $rule);
        $commissionTotal  = round($commission + $extraCommission, 2);
        $stoppageDiscount = $this->resolveAmount($grossFare, $rule['stoppage_discount'] ?? null, $rule['stoppage_discount_type'] ?? 'percent');
        $serviceCharge    = $this->resolveAmount($grossFare, $rule['service_charge'] ?? null, $rule['service_charge_type'] ?? 'percent');
        $markup           = $this->resolveAmount($grossFare, $rule['markup_value'] ?? null, $rule['markup_type'] ?? 'percent');

        $grossForAit = $this->resolveGrossForAit($grossFare, $taxLines);
        $aitRate     = (float) config('dynamic_rules.ait_rate', 0.003);
        // Business rounds AIT to whole currency (199.71 → 200 in QR worksheet).
        $ait         = round($grossForAit * $aitRate, 0);

        $netFare = round($baseFare - $commissionTotal + $totalTaxes, 2);
        $totalPayable = round($grossFare - $commissionTotal - $stoppageDiscount + $ait + $serviceCharge, 2);
        $grossPayment = round($grossFare + $markup, 2);

        $breakdown = [
            ['label' => 'Base Fare', 'amount' => $baseFare, 'type' => 'line'],
            ['label' => 'Taxes', 'amount' => $totalTaxes, 'type' => 'line'],
            ['label' => 'Gross Fare', 'amount' => $grossFare, 'type' => 'subtotal'],
        ];

        if ($commissionTotal > 0) {
            $breakdown[] = ['label' => 'Commission', 'amount' => -$commissionTotal, 'type' => 'deduction'];
        }

        if ($stoppageDiscount > 0) {
            $breakdown[] = ['label' => 'Stoppage Discount', 'amount' => -$stoppageDiscount, 'type' => 'deduction'];
        }

        if ($ait > 0) {
            $breakdown[] = ['label' => 'AIT', 'amount' => $ait, 'type' => 'addition'];
        }

        if ($serviceCharge > 0) {
            $breakdown[] = ['label' => 'Service Charge', 'amount' => $serviceCharge, 'type' => 'addition'];
        }

        if ($markup > 0) {
            $breakdown[] = ['label' => 'Markup', 'amount' => $markup, 'type' => 'addition'];
        }

        $breakdown[] = ['label' => 'Net Fare', 'amount' => $netFare, 'type' => 'subtotal'];
        $breakdown[] = ['label' => 'Total Payable', 'amount' => $totalPayable, 'type' => 'total'];

        return [
            'gross_fare'       => $grossFare,
            'gross_for_ait'    => $grossForAit,
            'ait'              => $ait,
            'net_fare'         => $netFare,
            'total_payable'    => $totalPayable,
            'gross_payment'    => $grossPayment,
            'commission'       => $commissionTotal,
            'stoppage_discount' => $stoppageDiscount,
            'service_charge'   => $serviceCharge,
            'markup'           => $markup,
            'currency'         => (string) ($fareInput['currency'] ?? 'BDT'),
            'pricing_breakdown' => $breakdown,
            'rule_applied'     => true,
            'rule_id'          => (int) ($rule['id'] ?? 0),
            'rule_name'        => (string) ($rule['rule_name'] ?? ''),
        ];
    }

    private function emptyPricing(float $grossFare, float $baseFare, float $totalTaxes): array
    {
        return [
            'gross_fare'        => $grossFare,
            'gross_for_ait'     => $grossFare,
            'ait'               => 0.0,
            'net_fare'          => $grossFare,
            'total_payable'     => $grossFare,
            'gross_payment'     => $grossFare,
            'commission'        => 0.0,
            'stoppage_discount' => 0.0,
            'service_charge'    => 0.0,
            'markup'            => 0.0,
            'currency'          => 'BDT',
            'pricing_breakdown' => [
                ['label' => 'Base Fare', 'amount' => $baseFare, 'type' => 'line'],
                ['label' => 'Taxes', 'amount' => $totalTaxes, 'type' => 'line'],
                ['label' => 'Gross Fare', 'amount' => $grossFare, 'type' => 'total'],
            ],
            'rule_applied'      => false,
            'rule_id'           => null,
            'rule_name'         => null,
        ];
    }

    private function resolveExtraCommission(float $baseFare, string $cabinClass, array $rule): float
    {
        if (empty($rule['extra_commission'])) {
            return 0.0;
        }

        $cabin = strtolower($cabinClass);
        if (str_contains($cabin, 'business') || str_contains($cabin, 'first')) {
            return $this->resolveAmount($baseFare, $rule['business_extra'] ?? null, $rule['business_extra_type'] ?? 'percent');
        }

        return $this->resolveAmount($baseFare, $rule['economy_extra'] ?? null, $rule['economy_extra_type'] ?? 'percent');
    }

    private function resolveAmount(float $base, ?float $value, string $type): float
    {
        if ($value === null || $value <= 0) {
            return 0.0;
        }

        if ($type === 'flat') {
            return round($value, 2);
        }

        return round($base * ($value / 100), 2);
    }

    private function resolveGrossForAit(float $grossFare, array $taxLines): float
    {
        if (empty($taxLines)) {
            return $grossFare;
        }

        $deductible = config('dynamic_rules.ait_deductible_tax_codes', []);
        $deduction  = 0.0;

        foreach ($taxLines as $line) {
            $code = strtoupper((string) ($line['code'] ?? ''));
            if ($code !== '' && in_array($code, $deductible, true)) {
                $deduction += (float) ($line['amount'] ?? 0);
            }
        }

        return round(max(0, $grossFare - $deduction), 2);
    }
}
