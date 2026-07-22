<?php

namespace App\Models\GroupRequest;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferPriceSegment extends Model
{
    protected $table = 'offer_price_segments';

    protected $fillable = [
        'offer_price_id',
        'sequence',
        'origin',
        'destination',
        'departure_date',
        'flight_no',
    ];

    protected $casts = [
        'departure_date' => 'datetime',
    ];

    public function priceOffer()
    {
        return $this->belongsTo(PriceOffer::class, 'offer_price_id');
    }
}
