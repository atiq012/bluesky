<?php

namespace App\Models\GroupRequest;

use App\Models\Agent\Agent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\GroupPNR\Group_pnr;
use Illuminate\Database\Eloquent\Model;

class GroupRequest extends Model
{
    protected $table = 'group_requests';
    protected $guarded = ['id'];

    protected $casts = [
        'departure_date' => 'datetime',
        'return_date'    => 'datetime',
        'assigned_date'  => 'datetime',
    ];

    public function segments()
    {
        return $this->hasMany(GroupRequestSegment::class, 'group_request_id');
    }

    public function agent()
    {
        return $this->hasOne(Agent::class, 'agent_code', 'agent_code');
    }

    public function assignedGroup()
    {
        return $this->belongsTo(Group_pnr::class, 'group_pnr_id','id');
    }
}
