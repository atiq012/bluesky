<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingActivityLog extends Model
{
    protected $fillable = [
        'booking_attempt_id',
        'action_type',
        'user_id',
        'user_name',
        'status_before',
        'status_after',
        'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function bookingAttempt(): BelongsTo
    {
        return $this->belongsTo(BookingAttempt::class, 'booking_attempt_id');
    }
}
