<?php

namespace App\Http\Controllers\Admin\API;

use App\Http\Controllers\BaseController;
use App\Services\DynamicRule\DynamicRuleCache;

class DynamicRuleCacheController extends BaseController
{
    public function __construct(
        private readonly DynamicRuleCache $dynamicRuleCache,
    ) {}

    // Search page polls this when Ably is down — no rule CRUD on agency portal.
    public function cacheStamp()
    {
        return $this->SuccessResponse([
            'version' => $this->dynamicRuleCache->getVersion(),
            'stamp'   => $this->dynamicRuleCache->activeRulesFreshnessStamp(),
        ], 'Dynamic rule cache stamp.');
    }
}
