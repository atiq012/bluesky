<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingSearchLog extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'booking_search_logs';

    protected $fillable = [
        'user_id',
        'booking_attempt_id',
        'request_id',
        'search_payload',
        'way',
        'from_airport',
        'to_airport',
        'dep_date',
        'arrival_date',
        'adt',
        'cnn',
        'kid',
        'inf',
        'cabin_class',
        'provider',
        'endpoint',
        'response_file_path',
        'response_size_bytes',
        'flight_count',
        'status',
        'error_message',
        'http_status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'search_payload'       => 'array',
        'dep_date'             => 'date',
        'arrival_date'         => 'date',
        'response_size_bytes'  => 'integer',
        'flight_count'         => 'integer',
        'http_status'          => 'integer',
    ];

    public function bookingAttempt(): BelongsTo
    {
        return $this->belongsTo(BookingAttempt::class, 'booking_attempt_id');
    }
}
