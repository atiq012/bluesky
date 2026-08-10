<?php
namespace App\Models\GroupPNR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Segment extends Model
{
    use HasFactory;

    protected $casts = [
        'metadata' => 'array',
    ];

    public function groupPnr()
    {
        return $this->belongsTo(Group_pnr::class);
    }
}
