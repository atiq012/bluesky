<?php

namespace App\Models\GroupRequest;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferPricePaymentTerm extends Model
{
    protected $table = 'offer_price_payment_terms';

    protected $fillable = [
        'offer_price_id',
        'sequence',
        'value',
        'value_type',
        'amount',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'value' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function priceOffer()
    {
        return $this->belongsTo(PriceOffer::class, 'offer_price_id');
    }
}
