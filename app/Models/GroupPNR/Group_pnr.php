<?php
namespace App\Models\GroupPNR;

use App\Models\GroupPNR\ChildInfantPolicy;
use App\Models\GroupPNR\PaymentSequence;
use App\Models\GroupPNR\Segment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group_pnr extends Model
{
    use HasFactory;

    public function segments()
    {
        return $this->hasMany(Segment::class);
    }

    public function paymentSequences()
    {
        return $this->hasMany(PaymentSequence::class);
    }

    public function childInfantPolicy()
    {
        return $this->belongsTo(ChildInfantPolicy::class,'id','group_pnr_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
