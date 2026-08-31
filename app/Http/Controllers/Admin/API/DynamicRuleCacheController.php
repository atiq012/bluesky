<?php

namespace App\Http\Controllers\Admin\API;

use App\Http\Controllers\BaseController;
use App\Services\DynamicRule\DynamicRuleCache;
use Illuminate\Support\Facades\Schema;

class DynamicRuleCacheController extends BaseController
{
    public function __construct(
        private readonly DynamicRuleCache $dynamicRuleCache,
    ) {}

    // Search page polls this when Pusher is down — no rule CRUD on agency portal.
    public function cacheStamp()
    {
        // Fare-rule-engine plan §12.2 — once `dynamic_rules` is dropped in BlueSky, pin this to a
        // static, unchanging payload so the poll stops ever perceiving a "change" and firing a
        // refresh. `getVersion()` reads a different, unaffected table (app_cache_versions) and
        // would otherwise keep returning whatever real version was last recorded, which is
        // harmless but not the flat, unambiguous "nothing to see here" signal the safe-mode step
        // calls for.
        if (! Schema::hasTable('dynamic_rules')) {
            return $this->SuccessResponse([
                'version'  => 0,
                'stamp'    => '0:0',
                'degraded' => true,
            ], 'Dynamic rule cache stamp.');
        }

        return $this->SuccessResponse([
            'version'  => $this->dynamicRuleCache->getVersion(),
            'stamp'    => $this->dynamicRuleCache->activeRulesFreshnessStamp(),
            'degraded' => false,
        ], 'Dynamic rule cache stamp.');
    }
}
