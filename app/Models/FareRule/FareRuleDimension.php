<?php

namespace App\Models\FareRule;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FareRuleDimension extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    public function fareRule(): BelongsTo
    {
        return $this->belongsTo(FareRule::class);
    }
}
