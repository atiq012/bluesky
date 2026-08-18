<?php

namespace App\Services\FareRule;

use Illuminate\Support\Facades\DB;

// DB rows -> FareRuleSnapshot. Three plain query-builder queries, assembled into flat arrays
// (invariant §16.1-2 — never Eloquent). In production this runs only on a cache miss (Phase 4);
// here it is the single source of truth for "what does a rule row look like in memory".
class FareRuleSnapshotBuilder
{
    private const DIMENSION_GROUPS = [
        'airline'       => 'airlines',
        'supplier'      => 'suppliers',
        'cabin'         => 'cabins',
        'booking_class' => 'booking_classes',
        'tier'          => 'tiers',
        'agency'        => 'agencies',
    ];

    public function build(): FareRuleSnapshot
    {
        $rows = DB::table('fare_rules')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        $ruleIds = $rows->keys()->all();

        $dimensions = DB::table('fare_rule_dimensions')
            ->whereIn('fare_rule_id', $ruleIds)
            ->get()
            ->groupBy('fare_rule_id');

        $routes = DB::table('fare_rule_routes')
            ->whereIn('fare_rule_id', $ruleIds)
            ->get()
            ->groupBy('fare_rule_id');

        $rules = [];
        foreach ($rows as $id => $row) {
            $rule = (array) $row;

            foreach (self::DIMENSION_GROUPS as $group) {
                $rule[$group] = [];
            }

            foreach ($dimensions->get($id, []) as $dim) {
                $group = self::DIMENSION_GROUPS[$dim->dimension] ?? null;
                if ($group !== null) {
                    $rule[$group][] = $dim->value;
                }
            }

            $rule['routes'] = [];
            foreach ($routes->get($id, []) as $route) {
                $rule['routes'][] = [$route->origin, $route->destination];
            }

            $rule['addon'] = (bool) $rule['addon'];

            $rules[$id] = $rule;
        }

        return FareRuleSnapshot::fromArray([
            'rules'      => $rules,
            'indexes'    => $this->buildIndexes($rules),
            '_built_at'  => now()->toIso8601String(), // for `fare-rules:cache-status`'s snapshot-age reading
        ]);
    }

    private function buildIndexes(array $rules): array
    {
        $idx = [
            'by_type'         => [],
            'by_airline'      => [],
            'airline_any'     => [],
            'by_origin'       => [],
            'origin_any'      => [],
            'origin_wildcard' => [],
            'by_dest'         => [],
            'dest_any'        => [],
            'dest_wildcard'   => [],
            'uses_cabin'      => [],
            'uses_rbd'        => [],
        ];

        foreach ($rules as $id => $rule) {
            $idx['by_type'][$rule['type']][] = $id;

            if ($rule['airlines'] === []) {
                $idx['airline_any'][] = $id;
            } else {
                foreach ($rule['airlines'] as $code) {
                    $idx['by_airline'][$code][] = $id;
                }
            }

            if ($rule['routes'] === []) {
                $idx['origin_any'][] = $id;
                $idx['dest_any'][] = $id;
            } else {
                foreach ($rule['routes'] as [$origin, $destination]) {
                    if ($origin === '*') {
                        $idx['origin_wildcard'][] = $id;
                    } else {
                        $idx['by_origin'][$origin][] = $id;
                    }

                    if ($destination === '*') {
                        $idx['dest_wildcard'][] = $id;
                    } else {
                        $idx['by_dest'][$destination][] = $id;
                    }
                }
            }

            if ($rule['cabins'] !== []) {
                $idx['uses_cabin'][] = $id;
            }

            if ($rule['booking_classes'] !== []) {
                $idx['uses_rbd'][] = $id;
            }
        }

        foreach (['airline_any', 'origin_any', 'origin_wildcard', 'dest_any', 'dest_wildcard', 'uses_cabin', 'uses_rbd'] as $key) {
            $idx[$key] = array_values(array_unique($idx[$key]));
        }

        return $idx;
    }
}
