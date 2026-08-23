<?php

namespace App\Http\Controllers\Admin\API;

use App\Http\Controllers\BaseController;
use App\Services\FareRule\FareRuleCache;

class FareRuleCacheController extends BaseController
{
    public function __construct(
        private readonly FareRuleCache $fareRuleCache,
    ) {}

    // Search page polls this when Ably is down — no rule CRUD on agency portal.
    public function cacheStamp()
    {
        [$version, $stamp] = $this->fareRuleCache->versionAndStamp();

        return $this->SuccessResponse(['version' => $version, 'stamp' => $stamp], 'Fare rule cache stamp.');
    }
}
