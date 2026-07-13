<?php

namespace App\Models\GroupRequest;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
}
