<?php

namespace App\Models\Policy;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Policy extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'point', 'sort_order', 'status', 'created_by', 'updated_by'];

    // slug => label, groups collapsed — single source for validation and label rendering
    public static function typeMap(): array
    {
        return collect(config('policy.groups'))->collapse()->all();
    }
}
