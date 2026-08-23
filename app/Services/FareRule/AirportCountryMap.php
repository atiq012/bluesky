<?php

namespace App\Services\FareRule;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Cached code -> ISO2 lookup (§7.7-G, §4.1). All 8,529 airports form a small associative array;
// cached under a key that includes COUNT(*) so a new airport row naturally produces a new key —
// never a per-search full-table read.
class AirportCountryMap
{
    private ?array $map = null;

    public function get(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $count = DB::table('airports')->count();
        $ttl   = (int) config('FareRules.engine.airport_map_ttl', 86400);

        return $this->map = Cache::remember("fare_rules:airports:c{$count}", $ttl, function () {
            return DB::table('airports')
                ->select('code', 'countryshortcode')
                ->whereNotNull('code')
                ->get()
                ->pluck('countryshortcode', 'code')
                ->all();
        });
    }

    public function country(string $airportCode): ?string
    {
        return $this->get()[$airportCode] ?? null;
    }

    // §4.1 trip-scope formula. 'onward' here is a given input (the itinerary's connection
    // type), not derived by this method — deriving it from raw segments is Phase 7 work, once
    // real itinerary data is wired in.
    public function resolveScope(string $origin, string $destination, string $onward = 'none'): string
    {
        $originCountry      = $this->country($origin);
        $destinationCountry = $this->country($destination);

        // Unknown airport code — fail toward the pricier/safer classification rather than
        // silently treating an unmapped code as domestic.
        if ($originCountry === null || $destinationCountry === null) {
            return 'international';
        }

        if ($originCountry === $destinationCountry) {
            return 'domestic';
        }

        return $onward === 'domestic' ? 'mixed' : 'international';
    }
}
