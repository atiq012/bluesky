<?php

namespace App\Services\FareRule;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

// Versioned snapshot: DB version + freshness stamp + Redis (§11). The version+stamp row is
// NEVER cached — read from MySQL on every call, ~0.2ms, a deliberate trade for exact
// invalidation (§11.2). Only the snapshot itself (keyed by version+stamp) lives in Redis.
class FareRuleCache
{
    private const VERSION_ROW_KEY     = 'fare_rules';
    private const SNAPSHOT_PREFIX     = 'fare_rules:snap:';
    private const CURRENT_KEY_POINTER = 'fare_rules:snap:current';
    private const BUILD_LOCK          = 'fare_rules:build';

    public function __construct(private readonly FareRuleSnapshotBuilder $builder)
    {
    }

    public function snapshot(): FareRuleSnapshot
    {
        try {
            $row = DB::table('app_cache_versions')->where('key', self::VERSION_ROW_KEY)->first();
        } catch (Throwable $e) {
            Log::warning('FareRuleEngine: version row read failed, building direct', ['error' => $e->getMessage()]);

            return $this->builder->build();
        }

        $version = $row ? (int) $row->version : 1;
        $stamp   = $row && $row->stamp !== null ? (string) $row->stamp : $this->recomputeStamp();
        $key     = $this->snapshotKey($version, $stamp);

        try {
            $cached = Cache::get($key);
        } catch (Throwable $e) {
            Log::warning('FareRuleEngine: cache read failed, building direct', ['error' => $e->getMessage()]);

            return $this->builder->build();
        }

        if ($cached !== null) {
            return FareRuleSnapshot::fromArray($cached);
        }

        return $this->buildViaLock($key);
    }

    // Stampede protection: only one process rebuilds per key; the rest wait briefly and read
    // what it produced, or — if it never shows up — build locally without caching (§11.5).
    private function buildViaLock(string $key): FareRuleSnapshot
    {
        try {
            $lock = Cache::lock(self::BUILD_LOCK, (int) config('FareRules.engine.build_lock_seconds', 10));

            if ($lock->get()) {
                try {
                    $snapshot = $this->builder->build();
                    $this->store($key, $snapshot);

                    return $snapshot;
                } finally {
                    $lock->release();
                }
            }

            if ($lock->block((int) config('FareRules.engine.build_lock_wait', 3))) {
                $lock->release();
            }

            $cached = Cache::get($key);
            if ($cached !== null) {
                return FareRuleSnapshot::fromArray($cached);
            }
        } catch (Throwable $e) {
            Log::warning('FareRuleEngine: cache lock/build failed, building direct', ['error' => $e->getMessage()]);
        }

        // A cache outage must degrade speed, never correctness or availability.
        return $this->builder->build();
    }

    private function store(string $key, FareRuleSnapshot $snapshot): void
    {
        $ttl = (int) config('FareRules.engine.cache_ttl_seconds', 3600);

        // Forget the actual previous key via the pointer, not a reconstructed guess — the old
        // system forgot a version-only key while writes used a version+stamp key, so nothing
        // was ever evicted (§11.7 defect #2).
        $previous = Cache::get(self::CURRENT_KEY_POINTER);
        if ($previous !== null && $previous !== $key) {
            Cache::forget($previous);
        }

        Cache::put($key, $snapshot->toArray(), $ttl);
        Cache::put(self::CURRENT_KEY_POINTER, $key, $ttl);
    }

    // Bump version, recompute + persist stamp in ONE UPDATE, then warm. Called from the
    // observer's afterCommit hook (or the recovery command) — never from inside a transaction
    // that might roll back and never from the read path.
    public function invalidate(): void
    {
        [$version, $stamp] = DB::transaction(function () {
            $row     = DB::table('app_cache_versions')->where('key', self::VERSION_ROW_KEY)->lockForUpdate()->first();
            $version = ($row ? (int) $row->version : 0) + 1;
            $stamp   = $this->recomputeStamp();

            DB::table('app_cache_versions')->updateOrInsert(
                ['key' => self::VERSION_ROW_KEY],
                ['version' => $version, 'stamp' => $stamp, 'updated_at' => now()],
            );

            return [$version, $stamp];
        });

        try {
            $this->store($this->snapshotKey($version, $stamp), $this->builder->build());
        } catch (Throwable $e) {
            Log::warning('FareRuleEngine: warm after invalidate failed', ['error' => $e->getMessage()]);
        }
    }

    public function snapshotKey(int $version, string $stamp): string
    {
        return self::SNAPSHOT_PREFIX . 'v' . $version . ':s' . $stamp;
    }

    public function versionAndStamp(): array
    {
        $row = DB::table('app_cache_versions')->where('key', self::VERSION_ROW_KEY)->first();

        return [
            $row ? (int) $row->version : 0,
            $row && $row->stamp !== null ? (string) $row->stamp : '',
        ];
    }

    // MAX(updated_at):COUNT(*) across all three tables (§11.3) — a stamp over fare_rules alone
    // is blind to a dimension-only or route-only change (§11.7 defect #3). Aggregate query only,
    // never a row fetch (§7.7-A, defect #4) — runs on the write path and in recovery, never
    // per search.
    public function recomputeStamp(): string
    {
        $row = DB::selectOne('
            SELECT
              (SELECT MAX(updated_at) FROM fare_rules WHERE status = ? AND deleted_at IS NULL) AS r_ts,
              (SELECT COUNT(*)        FROM fare_rules WHERE status = ? AND deleted_at IS NULL) AS r_ct,
              (SELECT COUNT(*)        FROM fare_rule_dimensions)                                AS d_ct,
              (SELECT MAX(id)         FROM fare_rule_dimensions)                                AS d_mx,
              (SELECT COUNT(*)        FROM fare_rule_routes)                                    AS x_ct,
              (SELECT MAX(id)         FROM fare_rule_routes)                                    AS x_mx
        ', ['active', 'active']);

        $ts = $row->r_ts ? strtotime((string) $row->r_ts) : 0;

        return implode(':', [
            (int) $ts, (int) $row->r_ct, (int) $row->d_ct,
            (int) $row->d_mx, (int) $row->x_ct, (int) $row->x_mx,
        ]);
    }
}
