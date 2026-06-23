<?php

namespace App\Models\Agent;

use App\Traits\AuditTraits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentBalanceLedger extends Model
{
    use AuditTraits;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'agent_id',
        'event_type',
        'amount',
        'direction',
        'net_balance_before',
        'net_balance_after',
        'credit_balance_before',
        'credit_balance_after',
        'reference_type',
        'reference_id',
        'description',
        'metadata',
        'transaction_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'net_balance_before' => 'float',
        'net_balance_after' => 'float',
        'credit_balance_before' => 'float',
        'credit_balance_after' => 'float',
        'metadata' => 'array',
        'transaction_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
