<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user === null
            || $user->email_verified_at !== null
            || $request->routeIs(
                'verification.notice',
                'verification.verify',
                'verification.resend',
                'lang.switch',
            )
        ) {
            return $next($request);
        }

        return redirect()->route('verification.notice');
    }
}
