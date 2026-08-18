<?php

namespace App\Models\FareRule;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FareRuleVersion extends Model
{
    use HasFactory;

    // Table has created_at only, no updated_at — a version row is never modified after insert.
    const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function fareRule(): BelongsTo
    {
        return $this->belongsTo(FareRule::class);
    }
}
