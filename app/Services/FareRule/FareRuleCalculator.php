<?php

namespace App\Services\FareRule;

// The money math (§7.3), incl. AIT (§7.4). Takes FarePrecedenceResolver::resolveByType()'s
// output + a FareRuleContext (which carries the fare components) and produces the §9.1 payload.
//
// Mode resolution (f()): pct_base/pct_base_yq/pct_yq/pct_yr always read the raw fare's
// base/yq/yr directly, regardless of rule type. Only pct_total and the per-segment multiplier
// use the type-specific reference amount from the §7.3 table (supplier_fare, or
// supplier_fare+markup+service_fee for promo, etc) — that is the only self-consistent reading
// of "ref=" in the plan, since pct_yq must mean "% of YQ" for every rule type, not "% of a
// type-specific pool that happens to be called yq".
class FareRuleCalculator
{
    private const SEGMENT_SCALED_TYPES = ['gds_incent'];

    public function calculateCompact(array $resolvedByType, FareRuleContext $ctx): array
    {
        return $this->compute($resolvedByType, $ctx, withDetail: false);
    }

    public function calculateFull(array $resolvedByType, FareRuleContext $ctx): array
    {
        return $this->compute($resolvedByType, $ctx, withDetail: true);
    }

    private function compute(array $resolvedByType, FareRuleContext $ctx, bool $withDetail): array
    {
        $supplierFare = $ctx->base + $ctx->totalTaxes;

        $applied = [];
        $ruleApplied = false;
        $provisional = false;

        $sumType = function (string $type, float $ref) use ($resolvedByType, $ctx, &$applied, &$ruleApplied, &$provisional): float {
            $slot = $resolvedByType[$type] ?? ['base' => null, 'addons' => []];
            $total = 0.0;

            foreach (array_filter([$slot['base'], ...$slot['addons']]) as $rule) {
                $ruleApplied = true;
                $amount = $this->resolveAmount($rule, $ctx, $ref);
                $total += $amount;

                if (in_array($rule['mode'], ['pct_yq', 'pct_yr'], true) && ! $ctx->taxLinesExact) {
                    $provisional = true;
                }

                $applied[] = [
                    'type'    => $type,
                    'rule_id' => $rule['id'],
                    'name'    => $rule['rule_name'] ?? null,
                    'role'    => $rule === $slot['base'] ? 'base' : 'addon',
                    'amount'  => $amount,
                ];
            }

            return $total;
        };

        // Order matters: promo and cashback reference amounts already include markup/service_fee.
        $markup     = $sumType('markup', $supplierFare);
        $serviceFee = $sumType('service_fee', $supplierFare);
        $promo      = $sumType('promo', $supplierFare + $markup + $serviceFee);
        $commShare  = $sumType('comm_share', $supplierFare);
        $cashback   = $sumType('cashback', $supplierFare + $markup);
        $commission = $sumType('commission', $supplierFare);
        $plb        = $sumType('plb', $supplierFare);
        $gdsIncent  = $sumType('gds_incent', $supplierFare);

        // AIT — computed on the supplier gross ONLY, before markup/service_fee (§7.4, invariant §16.1-7).
        $deductibleCodes = config('FareRules.engine.ait_deductible_tax_codes', []);
        $deductible = 0.0;
        if ($ctx->taxLinesExact) {
            foreach ($deductibleCodes as $code) {
                $deductible += (float) ($ctx->taxLines[$code] ?? 0);
            }
        }
        // Not exact (search time) — deduction is skipped, i.e. taxed on the full gross.
        // Conservative: never understates the price shown (§8).

        $grossForAit = $supplierFare - $deductible;
        $aitRate = (float) config('FareRules.engine.ait_rate');
        $aitPrecise = round($grossForAit * $aitRate, 2);
        // Agency pays whole taka; simulator exposes ait_precise so small BD/UT/E5 changes are visible.
        $ait = round($grossForAit * $aitRate, 0);

        $grossFare  = $supplierFare;
        $agencyPays = $supplierFare + $markup + $serviceFee - $promo - $commShare + $ait;
        $agencyNet  = $agencyPays - $cashback;
        $blueskyEarn = $markup + $serviceFee + ($commission - $commShare) + $plb + $gdsIncent - $promo - $cashback;

        $result = [
            'gross_fare'          => $grossFare,
            'supplier_fare'       => $supplierFare,
            'markup'              => $markup,
            'service_fee'         => $serviceFee,
            'promo'               => $promo,
            'comm_share'          => $commShare,
            'cashback'            => $cashback,
            'gross_for_ait'       => $grossForAit,
            'deductible_total'    => $deductible,
            'ait_precise'         => $aitPrecise,
            'ait'                 => $ait,
            'agency_pays'         => $agencyPays,
            'agency_net'          => $agencyNet,
            'currency'            => $ctx->currency,
            'rule_applied'        => $ruleApplied,
            'pricing_provisional' => $provisional,
        ];

        if ($withDetail) {
            $result['applied']   = $applied;
            $result['breakdown'] = $this->buildBreakdown($grossFare, $markup, $serviceFee, $promo, $commShare, $ait, $agencyPays);
        }

        // Not part of the §16.3 frozen payload contract — admin/simulator/reporting only,
        // kept here so the calculator is the single source of truth for this number too.
        $result['_bluesky_earn'] = $blueskyEarn;

        return $result;
    }

    private function resolveAmount(array $rule, FareRuleContext $ctx, float $ref): float
    {
        $amount = match ($rule['mode']) {
            'fixed'       => (float) $rule['value'],
            'pct_base'    => $ctx->base * $rule['value'] / 100,
            'pct_base_yq' => ($ctx->base + $ctx->yq) * $rule['value'] / 100,
            'pct_yq'      => $ctx->yq * $rule['value'] / 100,
            'pct_yr'      => $ctx->yr * $rule['value'] / 100,
            'pct_total'   => $ref * $rule['value'] / 100,
            default       => 0.0,
        };

        $perSegment = ($rule['fee_basis'] ?? null) === 'per_segment'
            || in_array($rule['type'], self::SEGMENT_SCALED_TYPES, true);

        return $perSegment ? $amount * $ctx->segments : $amount;
    }

    private function buildBreakdown(float $grossFare, float $markup, float $serviceFee, float $promo, float $commShare, float $ait, float $agencyPays): array
    {
        $lines = [['label' => 'Gross fare', 'amount' => $grossFare, 'type' => 'line']];

        if ($markup > 0)     $lines[] = ['label' => 'Markup', 'amount' => $markup, 'type' => 'addition'];
        if ($serviceFee > 0) $lines[] = ['label' => 'Service fee', 'amount' => $serviceFee, 'type' => 'addition'];
        if ($commShare > 0)  $lines[] = ['label' => 'Commission share', 'amount' => $commShare, 'type' => 'deduction'];
        if ($promo > 0)      $lines[] = ['label' => 'Promo', 'amount' => $promo, 'type' => 'deduction'];
        $lines[] = ['label' => 'AIT', 'amount' => $ait, 'type' => 'addition'];
        $lines[] = ['label' => 'Payable', 'amount' => $agencyPays, 'type' => 'total'];

        return $lines;
    }
}
