<?php

namespace App\Services\FareRule;

use Illuminate\Support\Facades\Log;

// Applies compact pricing to a batch of contexts (one search response's worth of brands) using
// a single shared memo for the whole batch. Not yet wired to a real search/price service —
// that's Phase 7. Built now because the perf budget (§7.7) has to be proven by a benchmark that
// needs exactly this loop, and because "build the fast version later" is the anti-pattern the
// plan explicitly warns against (§16.6).
class FareRulePricingApplier
{
    public function __construct(
        private readonly FareRuleMatcher $matcher,
        private readonly FarePrecedenceResolver $resolver,
        private readonly FareRuleCalculator $calculator,
    ) {
    }

    /**
     * @param  FareRuleContext[]  $contexts
     * @return array{results: array, elapsed_ms: float}
     */
    public function applyCompact(FareRuleSnapshot $snapshot, array $contexts, array $weights): array
    {
        // Instance memo per call, never static (invariant §16.1-10) — one search/price request
        // gets one memo, shared across every brand in that request only.
        $memo = new FareRuleResolutionMemo();

        $start = microtime(true);

        $results = [];
        foreach ($contexts as $key => $context) {
            $resolved = $memo->resolveForContext($snapshot, $this->matcher, $this->resolver, $context, $weights);
            $results[$key] = $this->calculator->calculateCompact($resolved, $context);
        }

        $elapsedMs = (microtime(true) - $start) * 1000;

        $threshold = (float) config('FareRules.engine.slow_apply_ms', 150);
        if ($elapsedMs > $threshold) {
            Log::warning('FareRuleEngine: rule application exceeded budget', [
                'elapsed_ms'   => round($elapsedMs, 2),
                'threshold_ms' => $threshold,
                'contexts'     => count($contexts),
            ]);
        }

        return ['results' => $results, 'elapsed_ms' => $elapsedMs];
    }
}
