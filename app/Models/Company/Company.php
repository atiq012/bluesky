<?php

namespace App\Models\Company;

use App\Traits\AuditTraits;
use App\Traits\LogsModelActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes, AuditTraits, LogsModelActivity;

    protected static array $activityLogFields = [
        'name',
        'display_name',
        'iata',
        'trade_license',
        'ca_certificate_no',
        'address',
        'city',
        'country_code',
        'logo_path',
        'email',
        'phone',
        'registration_no',
        'status',
    ];

    protected static string $activityLogName = 'company';

    protected $fillable = [
        'name',
        'display_name',
        'iata',
        'trade_license',
        'ca_certificate_no',
        'address',
        'city',
        'country_code',
        'logo_path',
        'email',
        'phone',
        'registration_no',
        'status',
    ];
}
