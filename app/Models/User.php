<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Agent\Agent;
use App\Models\Role\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'user_role',
        'login_attamp',
        'is_active',
        'require_2fa',
        'registered_2fa',
        'google2fa_secret',
        'google2fa_qr',
        'password','password_updated_at','password_max_expired'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'password' => 'hashed',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // status: 1 Active, 2 On Hold, 3 Locked, 4 Deactivated — null means login/API allowed
    public function loginBlockMessage(): ?string
    {
        $status = (int) $this->status;
        if ($status === 1) {
            return null;
        }

        // External (agency) users → agency admin; internal → BlueSky support
        $contact = (int) $this->type === 2
            ? 'your agency admin'
            : 'BlueSky support';

        return match ($status) {
            2 => "Your account is on hold. Please contact {$contact}.",
            3 => "Your account is locked. Please contact {$contact}.",
            4 => "Your account has been deactivated. Please contact {$contact}.",
            default => "Your account is not active. Please contact {$contact}.",
        };
    }

    // Agency portal users: only Approved agency may stay logged in
    public function agencyLoginBlockMessage(): ?string
    {
        if ((int) $this->type !== 2) {
            return null;
        }

        $agent = $this->agent_id
            ? ($this->relationLoaded('agent') ? $this->agent : Agent::find($this->agent_id))
            : null;

        if ($agent && $agent->status === 'Approved') {
            return null;
        }

        return Agent::statusLoginBlockMessage($agent?->status);
    }

    // User status + agency status — first blocking reason wins
    public function sessionBlockMessage(): ?string
    {
        if ($message = $this->loginBlockMessage()) {
            return $message;
        }

        if ((int) $this->is_active !== 1) {
            $contact = (int) $this->type === 2
                ? 'your agency admin'
                : 'BlueSky support';

            return "Your account is not active. Please contact {$contact}.";
        }

        return $this->agencyLoginBlockMessage();
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'user_role','id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id','id');
    }
}
