<?php

namespace App\Services\AccessControl;

use App\Models\AccessControl\AgencyApiPermission;
use Illuminate\Support\Facades\Cache;

class AgencyApiAccessChecker
{
    public function isBlocked(int $agencyId, int $apiId): bool
    {
        // Stamp from DB so BlueSky writes invalidate B2B file cache
        $ver = $this->cacheVersion($agencyId);

        $blocked = Cache::remember("agency_api_permissions:agency:{$agencyId}:{$ver}", 600, fn() =>
            AgencyApiPermission::query()
                ->where('agency_id', $agencyId)
                ->where('is_allowed', 0)
                ->pluck('api_id')
                ->map(fn($id) => (int) $id)
                ->all()
        );

        return in_array($apiId, $blocked, true);
    }

    private function cacheVersion(int $agencyId): string
    {
        $stamp = AgencyApiPermission::withTrashed()
            ->where('agency_id', $agencyId)
            ->max('updated_at');

        return $stamp ? (string) strtotime((string) $stamp) : '0';
    }
}
