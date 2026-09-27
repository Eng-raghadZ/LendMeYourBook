<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('verification', function (Request $request) {
            if ($request->user()?->email_verified_at !== null) {
                return redirect('/');
            }

            return Limit::perMinute(6)
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('verification-resend', function (Request $request) {
            if ($request->user()?->email_verified_at !== null) {
                return redirect('/');
            }

            return Limit::perMinute(3)
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });
    }
}
