<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsModelActivity
{
    use LogsActivity;

    protected array $activityLogContext = [];

    public function setActivityLogContext(array $context): static
    {
        $this->activityLogContext = $context;

        return $this;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->resolveActivityLogFields())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->resolveActivityLogName());
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($this->activityLogContext !== []) {
            $activity->properties = $activity->properties->merge([
                'context' => $this->activityLogContext,
            ]);
        }
    }

    protected function resolveActivityLogFields(): array
    {
        return static::$activityLogFields ?? [];
    }

    protected function resolveActivityLogName(): string
    {
        return static::$activityLogName ?? class_basename(static::class);
    }
}
