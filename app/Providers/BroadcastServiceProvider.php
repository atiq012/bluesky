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
        // Ably driver crashes on boot when ABLY_KEY is empty — fall back until configured.
        if (config('broadcasting.default') === 'ably' && blank(config('broadcasting.connections.ably.key'))) {
            config(['broadcasting.default' => 'log']);
        }

        Broadcast::routes();

        require base_path('routes/channels.php');
    }
}
