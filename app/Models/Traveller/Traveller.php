<?php
namespace App\Models\Traveller;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Traveller extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id','title','first_name',
        'last_name', 'full_name',
        'pax_type',
        'email',
        'phone',
        'nationality',
        'passport_number',
        'passport_expiry_date',
        'dob',
        'gender',
    ];

}
