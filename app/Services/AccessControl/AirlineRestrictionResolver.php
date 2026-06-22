<?php

namespace App\Services\AccessControl;

use App\Models\AccessControl\AirlineRestriction;
use Illuminate\Support\Facades\Cache;

class AirlineRestrictionResolver
{
    public function getBlockedCodes(?int $agencyId): array
    {
        $global = Cache::remember('airline_restrictions:global', 3600, fn() =>
            AirlineRestriction::query()
                ->where('scope', 'global')
                ->where('is_active', 1)
                ->pluck('airline_code')
                ->toArray()
        );

        $agency = [];
        if ($agencyId) {
            $agency = Cache::remember("airline_restrictions:agency:{$agencyId}", 600, fn() =>
                AirlineRestriction::query()
                    ->where('agency_id', $agencyId)
                    ->where('is_active', 1)
                    ->pluck('airline_code')
                    ->toArray()
            );
        }

        $merged = array_unique(array_merge($global, $agency));

        return $merged ? array_flip($merged) : [];
    }
}
