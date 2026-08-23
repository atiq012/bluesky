<?php

namespace App\Models\FareRule;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Audit trail for §7.6 promo usage counting. One row per (fare_rule_id, booking_attempt_id) —
// the unique constraint is what makes a retried commit idempotent (FareRulePromoRedemptionService
// relies on it directly rather than re-deriving idempotency in PHP).
class FareRulePromoRedemption extends Model
{
    public $timestamps = false;

    protected $fillable = ['fare_rule_id', 'booking_attempt_id', 'agent_id', 'amount', 'redeemed_at'];

    protected $casts = [
        'amount'      => 'decimal:2',
        'redeemed_at' => 'datetime',
    ];

    public function fareRule(): BelongsTo
    {
        return $this->belongsTo(FareRule::class);
    }
}
