<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingSession extends Model
{
    use SoftDeletes;

    protected $table = 'booking_sessions';

    protected $fillable = [
        'user_id',
        'booking_attempt_id',
        'booking_price_log_id',
        'session_type',
        'request_payload',
        'response_payload',
        'response_file_path',
        'response_size_bytes',
        'identifier_authority',
        'identifier_value',
        'provider',
        'status',
        'http_status',
        'error_message',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'request_payload'  => 'array',
        'response_payload' => 'array',
    ];

    public function bookingAttempt(): BelongsTo
    {
        return $this->belongsTo(BookingAttempt::class, 'booking_attempt_id');
    }

    public function priceLog(): BelongsTo
    {
        return $this->belongsTo(BookingPriceLog::class, 'booking_price_log_id');
    }

    public function paxes(): HasMany
    {
        return $this->hasMany(BookingPax::class, 'booking_session_id');
    }
}
