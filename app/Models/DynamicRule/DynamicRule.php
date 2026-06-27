<?php

namespace App\Models\DynamicRule;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DynamicRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'including_agency'  => 'array',
        'excluding_agency'  => 'array',
        'including_airline' => 'array',
        'excluding_airline' => 'array',
        'departure'         => 'array',
        'arrival'           => 'array',
    ];
}
