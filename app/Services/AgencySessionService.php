<?php

namespace App\Services;

use App\Jobs\BroadcastResourceEvent;
use App\Models\Agent\Agent;
use App\Models\User;

class AgencySessionService
{
    // Agency left Approved → kick every portal user on that agency (realtime ForceLogout).
    public function forceLogoutUsersIfBlocked(Agent $agent): void
    {
        $reason = $agent->loginBlockMessage();
        if ($reason === null) {
            return;
        }

        $userIds = User::query()
            ->where('agent_id', $agent->id)
            ->pluck('id');

        foreach ($userIds as $userId) {
            BroadcastResourceEvent::dispatchSync(
                'user-session.'.$userId,
                'ForceLogout',
                [
                    'user_id'        => (int) $userId,
                    'agency_status'  => $agent->status,
                    'reason'         => $reason,
                ]
            );
        }
    }
}
