<?php
namespace App\Models\GroupPNR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSequence extends Model
{
    use HasFactory;
    public function groupPnr()
    {
        return $this->belongsTo(Group_pnr::class);
    }
}
