<?php

namespace App\Models\GroupRequest;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupRequestSegment extends Model
{
    protected $table = 'group_request_segments';
    protected $guarded = ['id'];

    protected $casts = [
        'departure_date' => 'datetime',
    ];

    public function groupRequest()
    {
        return $this->belongsTo(GroupRequest::class, 'group_request_id');
    }
}
