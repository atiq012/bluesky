<?php

namespace App\Models\FareRule;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FareRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'auto_name'      => 'boolean',
        'addon'          => 'boolean',
        'value'          => 'decimal:4',
        // Y-m-d wire format — default date cast JSON'd as UTC midnight and shifted a day
        // backward in Asia/Dhaka (Jul 1 → Jun 30 on the edit form).
        'travel_from'    => 'date:Y-m-d',
        'travel_to'      => 'date:Y-m-d',
        'effective_from' => 'date:Y-m-d',
        'effective_to'   => 'date:Y-m-d',
    ];

    public function dimensions(): HasMany
    {
        return $this->hasMany(FareRuleDimension::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(FareRuleRoute::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FareRuleVersion::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(FareRulePromoRedemption::class);
    }
}
