<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingAttempt extends Model
{
    use SoftDeletes;

    protected $table = 'booking_attempts';

    protected $fillable = [
        'user_id',
        'status',
        'closing_stage',
        'last_api_step',
        'last_api_status',
        'last_api_error',
        'last_api_at',
        'booking_search_log_id',
        'booking_price_log_id',
        'workbench_identifier',
        'booking_workbench_session_id',
        'selection_json',
        'snapshot_json',
        'post_commit_snapshot_json',
        'confirmed_at',
        'confirmed_by',
        'booking_commit_session_id',
        'gds_pnr',
        'airline_pnr',
        'airline_code',
        'airline_name',
        'cabin_class',
        'reservation_identifier',
        'ticket_numbers',
        'ticketed_at',
        'cancelled_at',
        'voided_at',
        'commit_error',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'selection_json' => 'array',
        'snapshot_json'  => 'array',
        'post_commit_snapshot_json' => 'array',
        'ticket_numbers' => 'array',
        'confirmed_at'   => 'datetime',
        'ticketed_at'    => 'datetime',
        'cancelled_at'   => 'datetime',
        'voided_at'      => 'datetime',
        'last_api_at'    => 'datetime',
    ];

    public function searchLog(): BelongsTo
    {
        return $this->belongsTo(BookingSearchLog::class, 'booking_search_log_id');
    }

    public function priceLog(): BelongsTo
    {
        return $this->belongsTo(BookingPriceLog::class, 'booking_price_log_id');
    }

    public function workbenchSession(): BelongsTo
    {
        return $this->belongsTo(BookingSession::class, 'booking_workbench_session_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(BookingSession::class, 'booking_attempt_id');
    }

    public function paxes(): HasMany
    {
        return $this->hasMany(BookingPax::class, 'booking_attempt_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(BookingActivityLog::class, 'booking_attempt_id');
    }

    public function commitSession(): BelongsTo
    {
        return $this->belongsTo(BookingSession::class, 'booking_commit_session_id');
    }
}
