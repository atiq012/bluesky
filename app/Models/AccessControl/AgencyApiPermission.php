<?php

namespace App\Models\AccessControl;

use App\Models\Agent\Agent;
use App\Models\APIManagement\ApiManagement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgencyApiPermission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'agency_id', 'api_id', 'is_allowed', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_allowed' => 'boolean',
    ];

    public function agency()
    {
        return $this->belongsTo(Agent::class, 'agency_id');
    }

    public function api()
    {
        return $this->belongsTo(ApiManagement::class, 'api_id');
    }
}
