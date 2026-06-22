<?php

namespace App\Models\AccessControl;

use App\Models\Agent\Agent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AirlineRestriction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'scope', 'agency_id', 'airline_code', 'is_active', 'reason', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function agency()
    {
        return $this->belongsTo(Agent::class, 'agency_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeGlobal($query)
    {
        return $query->where('scope', 'global');
    }

    public function scopeForAgency($query, int $agencyId)
    {
        return $query->where('agency_id', $agencyId);
    }
}
