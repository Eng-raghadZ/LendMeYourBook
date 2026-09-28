<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $login = trim($validated['login']);
        $rateLimitKey = $this->rateLimitKey($login, $request->ip() ?? '');

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return redirect()->route('login')
                ->withErrors(['login' => __('site.login.throttled')])
                ->onlyInput('login');
        }

        $matches = User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->limit(2)
            ->get();

        $user = $matches->count() === 1 ? $matches->first() : null;
        $passwordIsValid = $user !== null && Hash::check($validated['password'], $user->password);

        if (! $passwordIsValid || $user->account_status !== 'active') {
            RateLimiter::hit($rateLimitKey, 60);

            return redirect()->route('login')
                ->withErrors(['login' => __('site.login.failed')])
                ->onlyInput('login');
        }

        Auth::login($user, $request->boolean('remember'));
        RateLimiter::clear($rateLimitKey);
        $request->session()->regenerate();

        return redirect('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function rateLimitKey(string $login, string $ip): string
    {
        $normalizedLogin = Str::lower(trim($login));

        return 'login:'.hash('sha256', $normalizedLogin.'|'.$ip);
    }
}
