<?php

namespace App\Providers;

use App\Models\AccessControl\AgencyApiPermission;
use App\Observers\AgencyApiPermissionObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $nEmail = base64_encode($notifiable->email);
            return 'https://devb2b.blueskyndc.com/PassReset/' . $token . '/' . $nEmail;
        });

        AgencyApiPermission::observe(AgencyApiPermissionObserver::class);
    }
}
