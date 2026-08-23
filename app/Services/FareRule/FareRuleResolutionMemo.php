<?php

namespace App\Services\FareRule;

// Per-request memo keyed by context signature (§7.7-E). Instance state, NEVER static
// (invariant §16.1-10) — a static memo would leak one agency's resolved prices into another
// agency's request the moment this runs under Octane or a queue worker.
class FareRuleResolutionMemo
{
    private array $cache = [];

    public function has(string $signature): bool
    {
        return array_key_exists($signature, $this->cache);
    }

    public function resolveForContext(
        FareRuleSnapshot $snapshot,
        FareRuleMatcher $matcher,
        FarePrecedenceResolver $resolver,
        FareRuleContext $context,
        array $specificityWeights,
    ): array {
        if ($this->has($context->signature)) {
            return $this->cache[$context->signature];
        }

        $candidates = $snapshot->candidatesForLeg($context->carrier, $context->origin, $context->destination);
        $matched    = array_values(array_filter(
            $candidates,
            fn (array $rule) => $matcher->matches($rule, $context)
        ));

        return $this->cache[$context->signature] = $resolver->resolveByType($matched, $specificityWeights);
    }
}
