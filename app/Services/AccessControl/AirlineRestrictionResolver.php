<?php

namespace App\Services\AccessControl;

use App\Models\AccessControl\AirlineRestriction;
use Illuminate\Support\Facades\Cache;

class AirlineRestrictionResolver
{
    public function getBlockedCodes(?int $agencyId): array
    {
        // Stamp from DB so BlueSky writes invalidate B2B file cache (separate cache stores)
        $ver = $this->cacheVersion();

        $global = Cache::remember(
            "airline_restrictions:global:{$ver}",
            3600,
            fn() =>
            AirlineRestriction::query()
                ->where('scope', 'global')
                ->where('is_active', 1)
                ->pluck('airline_code')
                ->map(fn($c) => strtoupper((string) $c))
                ->toArray()
        );

        $agency = [];
        if ($agencyId) {
            $agency = Cache::remember(
                "airline_restrictions:agency:{$agencyId}:{$ver}",
                600,
                fn() =>
                AirlineRestriction::query()
                    ->where('agency_id', $agencyId)
                    ->where('is_active', 1)
                    ->pluck('airline_code')
                    ->map(fn($c) => strtoupper((string) $c))
                    ->toArray()
            );
        }

        $merged = array_unique(array_merge($global, $agency));

        return $merged ? array_flip($merged) : [];
    }

    private function cacheVersion(): string
    {
        $stamp = AirlineRestriction::withTrashed()->max('updated_at');

        return $stamp ? (string) strtotime((string) $stamp) : '0';
    }
}
