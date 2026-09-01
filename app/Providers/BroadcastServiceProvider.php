<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pusher driver crashes on boot when PUSHER_APP_KEY is empty — fall back until configured.
        if (config('broadcasting.default') === 'pusher' && blank(config('broadcasting.connections.pusher.key'))) {
            config(['broadcasting.default' => 'log']);
        }

        Broadcast::routes();

        require base_path('routes/channels.php');
    }
}
