<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingPax extends Model
{
    use SoftDeletes;

    protected $table = 'booking_paxes';

    protected $fillable = [
        'booking_attempt_id',
        'booking_session_id',
        'traveller_id',
        'travelport_traveler_id',
        'pax_type',
        'sequence',
        'is_primary_contact',
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'dob',
        'gender',
        'nationality',
        'passport_number',
        'passport_expiry_date',
        'frequent_flyer_number',
        'email',
        'phone',
        'meal_preference',
        'wheelchair_needed',
        'passport_image_path',
        'visa_image_path',
        'travelport_response',
        'status',
        'error_message',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dob'                  => 'date',
        'passport_expiry_date' => 'date',
        'wheelchair_needed'    => 'boolean',
        'is_primary_contact'   => 'boolean',
        'travelport_response'  => 'array',
    ];

    public function bookingAttempt(): BelongsTo
    {
        return $this->belongsTo(BookingAttempt::class, 'booking_attempt_id');
    }

    public function bookingSession(): BelongsTo
    {
        return $this->belongsTo(BookingSession::class, 'booking_session_id');
    }

    public function traveller(): BelongsTo
    {
        return $this->belongsTo(Traveller::class, 'traveller_id');
    }
}
