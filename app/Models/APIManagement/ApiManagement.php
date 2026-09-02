<?php

namespace App\Models\APIManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiManagement extends Model
{
    use HasFactory;

    protected $table = 'api_management';

    protected $fillable = [
        'name',
        'author',
        'remark',
        'fields',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fields' => 'array',
    ];
}
