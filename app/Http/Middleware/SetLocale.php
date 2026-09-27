<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (! $locale) {
            $browserLocale = substr($request->server('HTTP_ACCEPT_LANGUAGE', 'ar'), 0, 2);
            $locale = in_array($browserLocale, ['ar', 'en']) ? $browserLocale : 'ar';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}