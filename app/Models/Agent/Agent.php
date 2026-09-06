<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;

    // null = Approved / portal allowed
    public function loginBlockMessage(): ?string
    {
        if ($this->status === 'Approved') {
            return null;
        }

        return self::statusLoginBlockMessage($this->status);
    }

    public static function statusLoginBlockMessage(?string $status): string
    {
        return match ($status) {
            'Pending' => 'Your agency registration is pending approval. You cannot log in until it is approved.',
            'Recommended' => 'Your agency is under final review. Login will be available after approval.',
            'Hold' => 'Your agency account is on hold. Please contact BlueSky support.',
            'Block' => 'Your agency account has been blocked. Please contact BlueSky support.',
            'Decline', 'Reject' => 'Your agency registration has been declined. Please contact BlueSky support.',
            default => 'Your agency is not approved for portal access. Please contact BlueSky support.',
        };
    }
}
