<?php

namespace App\Services\FareRule;

// priority -> specificity -> narrowness -> id. One place, reused everywhere (§16.2 trap 5) —
// never duplicate this comparator.
class FarePrecedenceResolver
{
    // Per type: exactly one base winner (by precedence) + every matching add-on (§16.2 trap 6).
    // Add-ons never compete for the base-winner slot — they always stack.
    public function resolveByType(array $matched, array $weights): array
    {
        $byType = [];
        foreach ($matched as $rule) {
            $byType[$rule['type']][] = $this->annotate($rule, $weights);
        }

        $result = [];
        foreach ($byType as $type => $ofType) {
            $bases = array_values(array_filter($ofType, fn (array $r) => ! $r['addon']));
            usort($bases, [self::class, 'compare']);

            $addons = array_values(array_filter($ofType, fn (array $r) => $r['addon']));
            usort($addons, [self::class, 'compare']);

            $result[$type] = [
                'base'   => $bases[0] ?? null,
                'addons' => $addons,
            ];
        }

        return $result;
    }

    public function annotate(array $rule, array $weights): array
    {
        $rule['specificity'] = $this->specificity($rule, $weights);
        $rule['narrowness']  = $this->narrowness($rule);

        return $rule;
    }

    public function specificity(array $rule, array $weights): int
    {
        $sum = 0;

        if (! empty($rule['agencies']))        $sum += $weights['agencies'] ?? 0;
        if (! empty($rule['tiers']))           $sum += $weights['tiers'] ?? 0;
        if (! empty($rule['routes']))          $sum += $weights['routes'] ?? 0;
        if (($rule['scope'] ?? 'any') !== 'any')  $sum += $weights['scope'] ?? 0;
        if (($rule['onward'] ?? 'any') !== 'any') $sum += $weights['onward'] ?? 0;
        if (! empty($rule['booking_classes']))  $sum += $weights['booking_classes'] ?? 0;
        if (! empty($rule['cabins']))           $sum += $weights['cabins'] ?? 0;
        if (! empty($rule['airlines']))         $sum += $weights['airlines'] ?? 0;
        if (! empty($rule['suppliers']))        $sum += $weights['suppliers'] ?? 0;

        return $sum;
    }

    public function narrowness(array $rule): int
    {
        return count($rule['airlines'] ?? [])
            + count($rule['suppliers'] ?? [])
            + count($rule['routes'] ?? [])
            + count($rule['cabins'] ?? [])
            + count($rule['booking_classes'] ?? [])
            + count($rule['tiers'] ?? [])
            + count($rule['agencies'] ?? []);
    }

    // priority DESC, specificity DESC, narrowness ASC (narrower wins), id ASC (stable).
    public static function compare(array $a, array $b): int
    {
        return $b['priority']    <=> $a['priority']
            ?: $b['specificity'] <=> $a['specificity']
            ?: $a['narrowness']  <=> $b['narrowness']
            ?: $a['id']          <=> $b['id'];
    }
}
