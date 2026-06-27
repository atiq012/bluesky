<?php

namespace App\Services\DynamicRule;

use App\Models\DynamicRule\DynamicRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DynamicRuleCache
{
    private const VERSION_ROW_KEY = 'dynamic_rules';

    // Legacy file-cache key — migrated once into app_cache_versions.
    private const LEGACY_VERSION_KEY = 'dynamic_rules:version';

    private const RULES_KEY_PREFIX = 'dynamic_rules:active:v';

    public function getVersion(): int
    {
        $row = DB::table('app_cache_versions')
            ->where('key', self::VERSION_ROW_KEY)
            ->first();

        if ($row === null) {
            return $this->bootstrapVersion();
        }

        $dbVersion = (int) $row->version;
        $legacy    = (int) Cache::get(self::LEGACY_VERSION_KEY, 0);

        // One-time lift when moving version counter from file cache to DB.
        if ($legacy > $dbVersion) {
            $this->persistVersion($legacy);

            return $legacy;
        }

        return $dbVersion;
    }

    public function bumpVersion(): int
    {
        return (int) DB::transaction(function () {
            $row = DB::table('app_cache_versions')
                ->where('key', self::VERSION_ROW_KEY)
                ->lockForUpdate()
                ->first();

            $current = $row ? (int) $row->version : $this->bootstrapVersion();
            $next    = $current + 1;

            DB::table('app_cache_versions')->updateOrInsert(
                ['key' => self::VERSION_ROW_KEY],
                ['version' => $next, 'updated_at' => now()],
            );

            Cache::forget(self::RULES_KEY_PREFIX . $current);
            Cache::forget(self::LEGACY_VERSION_KEY);

            return $next;
        });
    }

    public function invalidate(): void
    {
        $this->bumpVersion();
        // Warm immediately so first post-edit search does not read a stale snapshot.
        $this->getActiveRules();
    }

    public function getActiveRules(): array
    {
        $version = $this->getVersion();
        $stamp   = $this->activeRulesFreshnessStamp();
        $key     = self::RULES_KEY_PREFIX . $version . ':s' . $stamp;
        $ttl     = max((int) config('dynamic_rules.cache_ttl_seconds', 86400), 60);

        return Cache::remember($key, now()->addSeconds($ttl), function () {
            return $this->loadActiveRulesFromDatabase();
        });
    }

    // Changes when any active rule is saved, deleted, or status toggled — safety net if version bump is missed.
    public function activeRulesFreshnessStamp(): string
    {
        $rules = DynamicRule::query()
            ->where('status', true)
            ->whereNull('deleted_at')
            ->get(['id', 'updated_at']);

        if ($rules->isEmpty()) {
            return '0:0';
        }

        $latestTs = (int) $rules->max(fn(DynamicRule $rule) => $rule->updated_at?->getTimestamp() ?? 0);

        return $latestTs . ':' . $rules->count();
    }

    private function loadActiveRulesFromDatabase(): array
    {
        return DynamicRule::query()
            ->where('status', true)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn(DynamicRule $rule) => $this->normalizeRule($rule))
            ->all();
    }

    private function bootstrapVersion(): int
    {
        $legacy  = (int) Cache::get(self::LEGACY_VERSION_KEY, 0);
        $version = $legacy > 0 ? $legacy : 1;

        $this->persistVersion($version);

        return $version;
    }

    private function persistVersion(int $version): void
    {
        try {
            DB::table('app_cache_versions')->updateOrInsert(
                ['key' => self::VERSION_ROW_KEY],
                ['version' => $version, 'updated_at' => now()],
            );
        } catch (\Throwable $e) {
            Log::warning('Dynamic rule cache version persist failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function normalizeRule(DynamicRule $rule): array
    {
        return [
            'id'                     => (int) $rule->id,
            'rule_name'              => (string) $rule->rule_name,
            'start_date'             => $rule->start_date,
            'end_date'               => $rule->end_date,
            'run_continuously'       => (bool) $rule->run_continuously,
            'agency_type'            => $rule->agency_type,
            'agency_group'           => $rule->agency_group,
            'including_agency'       => $this->toList($rule->including_agency),
            'excluding_agency'       => $this->toList($rule->excluding_agency),
            'api'                    => $rule->api,
            'departure'              => $this->toList($rule->departure),
            'arrival'                => $this->toList($rule->arrival),
            'including_airline'      => $this->toList($rule->including_airline),
            'excluding_airline'      => $this->toList($rule->excluding_airline),
            'including_flight_type'  => $rule->including_flight_type,
            'excluding_flight_type'  => $rule->excluding_flight_type,
            'including_way_type'     => $rule->including_way_type,
            'excluding_way_type'     => $rule->excluding_way_type,
            'including_cabin_class'  => $rule->including_cabin_class,
            'excluding_cabin_class'  => $rule->excluding_cabin_class,
            'commission_value'       => $rule->commission_value !== null ? (float) $rule->commission_value : null,
            'commission_type'        => (string) ($rule->commission_type ?? 'percent'),
            'extra_commission'       => (bool) $rule->extra_commission,
            'economy_extra'          => $rule->economy_extra !== null ? (float) $rule->economy_extra : null,
            'economy_extra_type'     => (string) ($rule->economy_extra_type ?? 'percent'),
            'business_extra'         => $rule->business_extra !== null ? (float) $rule->business_extra : null,
            'business_extra_type'    => (string) ($rule->business_extra_type ?? 'percent'),
            'stoppage_discount'      => $rule->stoppage_discount !== null ? (float) $rule->stoppage_discount : null,
            'stoppage_discount_type' => (string) ($rule->stoppage_discount_type ?? 'percent'),
            'service_charge'         => $rule->service_charge !== null ? (float) $rule->service_charge : null,
            'service_charge_type'    => (string) ($rule->service_charge_type ?? 'percent'),
            'markup_value'           => $rule->markup_value !== null ? (float) $rule->markup_value : null,
            'markup_type'            => (string) ($rule->markup_type ?? 'percent'),
        ];
    }

    private function toList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, static fn($v) => $v !== '' && $v !== null));
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded)
                ? array_values(array_filter($decoded, static fn($v) => $v !== '' && $v !== null))
                : [$value];
        }

        return [];
    }
}
