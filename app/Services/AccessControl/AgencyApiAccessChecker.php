<?php

namespace App\Services\AccessControl;

use App\Models\AccessControl\AgencyApiPermission;
use Illuminate\Support\Facades\Cache;

class AgencyApiAccessChecker
{
    public function isBlocked(int $agencyId, int $apiId): bool
    {
        $blocked = Cache::remember("agency_api_permissions:agency:{$agencyId}", 600, fn() =>
            AgencyApiPermission::query()
                ->where('agency_id', $agencyId)
                ->where('is_allowed', 0)
                ->pluck('api_id')
                ->toArray()
        );

        return in_array($apiId, $blocked, true);
    }
}
