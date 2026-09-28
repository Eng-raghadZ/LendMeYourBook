<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        RateLimiter::for('password-reset-request', function (Request $request) {
            return Limit::perMinute(3)->by($this->passwordResetHttpKey($request));
        });

        RateLimiter::for('password-reset-attempt', function (Request $request) {
            return Limit::perMinute(10)->by($this->passwordResetHttpKey($request));
        });
    }

    private function passwordResetHttpKey(Request $request): string
    {
        $email = Str::lower(trim((string) $request->input('email')));

        return hash('sha256', $email.'|'.($request->ip() ?? ''));
    }
}
