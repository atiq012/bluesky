<?php

namespace App\Observers;

use App\Models\AccessControl\AgencyApiPermission;
use Illuminate\Support\Facades\Cache;

class AgencyApiPermissionObserver
{
    public function saved(AgencyApiPermission $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(AgencyApiPermission $model): void
    {
        $this->invalidate($model);
    }

    public function restored(AgencyApiPermission $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(AgencyApiPermission $model): void
    {
        // Versioned keys also expire; forget legacy key if present
        Cache::forget("agency_api_permissions:agency:{$model->agency_id}");
    }
}
