<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingPriceLog extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'booking_price_logs';

    protected $fillable = [
        'user_id',
        'booking_search_log_id',
        'booking_attempt_id',
        'request_id',
        'catalog_identifier',
        'offer_identifier',
        'outbound_offering_id',
        'outbound_product_ref',
        'inbound_offering_id',
        'inbound_product_ref',
        'selection_json',
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
        'price_payload',
        'total_price',
        'currency',
        'base_fare',
        'total_taxes',
        'provider',
        'status',
        'error_message',
        'http_status',
        'response_file_path',
        'response_size_bytes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'price_payload'       => 'array',
        'selection_json'      => 'array',
        'dep_date'            => 'date',
        'arrival_date'        => 'date',
        'total_price'         => 'float',
        'base_fare'           => 'float',
        'total_taxes'         => 'float',
        'response_size_bytes' => 'integer',
        'http_status'         => 'integer',
    ];

    public function searchLog(): BelongsTo
    {
        return $this->belongsTo(BookingSearchLog::class, 'booking_search_log_id');
    }

    public function bookingAttempt(): BelongsTo
    {
        return $this->belongsTo(BookingAttempt::class, 'booking_attempt_id');
    }
}
