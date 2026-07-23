<?php

namespace App\Models\GroupRequest;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceOffer extends Model
{
    protected $table = 'price_offers';

    protected $fillable = [
        'group_req_id',
        'request_type',
        'group_type',
        'class_type',
        'class_code',
        'currency',
        'exchange_rate',
        'offered_flight',
        'offered_flight_no',
        'offered_return_flight',
        'origin',
        'destination',
        'departure_date',
        'return_origin',
        'return_destination',
        'return_date',
        'adult_traveler',
        'child_traveler',
        'infant_traveler',
        'total_traveler',
        'adult_base_fare',
        'adult_tax',
        'adult_ait',
        'adult_max_pax',
        'child_base_fare',
        'child_tax',
        'child_ait',
        'child_max_pax',
        'infant_base_fare',
        'infant_tax',
        'infant_ait',
        'infant_max_pax',
        'est_total_fare',
        'markup_value',
        'markup_type',
        'service_charge_value',
        'service_charge_type',
        'estimate_net_payable',
        'policy_fare_rules',
        'pnr',
        'pcc',
        'gdc',
        'pnr_date',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'departure_date' => 'datetime',
        'return_date' => 'datetime',
        'exchange_rate' => 'decimal:2',
        'adult_base_fare' => 'decimal:2',
        'adult_tax' => 'decimal:2',
        'adult_ait' => 'decimal:2',
        'child_base_fare' => 'decimal:2',
        'child_tax' => 'decimal:2',
        'child_ait' => 'decimal:2',
        'infant_base_fare' => 'decimal:2',
        'infant_tax' => 'decimal:2',
        'infant_ait' => 'decimal:2',
        'est_total_fare' => 'decimal:2',
        'markup_value' => 'decimal:2',
        'service_charge_value' => 'decimal:2',
        'estimate_net_payable' => 'decimal:2',
    ];

    public function groupRequest()
    {
        return $this->belongsTo(GroupRequest::class, 'group_req_id');
    }

    public function segments()
    {
        return $this->hasMany(OfferPriceSegment::class, 'offer_price_id');
    }

    public function paymentTerms()
    {
        return $this->hasMany(OfferPricePaymentTerm::class, 'offer_price_id');
    }
}
