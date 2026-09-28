<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    private const CODE_LIFETIME_SECONDS = 600;

    private const MAX_CODE_ATTEMPTS = 5;

    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $request->merge(['email' => $this->normalizeEmail((string) $request->input('email'))]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $normalizedEmail = $validated['email'];
        $response = fn (): RedirectResponse => redirect()
            ->route('password.reset')
            ->with('status', __('site.password_reset.request_status'))
            ->withInput(['email' => $normalizedEmail]);

        $user = $this->userForEmail($normalizedEmail);

        if ($user === null || $user->account_status !== 'active') {
            return $response();
        }

        $currentReset = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        if (
            $currentReset?->created_at !== null
            && now()->lt(Carbon::parse($currentReset->created_at)->addMinute())
        ) {
            return $response();
        }

        $code = (string) random_int(100000, 999999);

        try {
            Mail::to($user->email)->send(new PasswordResetCodeMail($code));
        } catch (Throwable) {
            return $response();
        }

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($code), 'created_at' => now()],
        );

        RateLimiter::clear($this->codeAttemptKey($normalizedEmail));

        return $response();
    }

    public function resetForm(): View
    {
        return view('auth.reset-password');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->merge(['email' => $this->normalizeEmail((string) $request->input('email'))]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $normalizedEmail = $validated['email'];
        $attemptKey = $this->codeAttemptKey($normalizedEmail);

        if (RateLimiter::tooManyAttempts($attemptKey, self::MAX_CODE_ATTEMPTS)) {
            $this->deleteResetRow($normalizedEmail);

            return $this->invalidCodeResponse($normalizedEmail);
        }

        $user = $this->userForEmail($normalizedEmail);
        $reset = $user === null
            ? null
            : DB::table('password_reset_tokens')->where('email', $user->email)->first();

        $isValid = $reset?->created_at !== null
            && ! now()->gt(Carbon::parse($reset->created_at)->addSeconds(self::CODE_LIFETIME_SECONDS))
            && Hash::check($validated['code'], $reset->token)
            && $user !== null
            && $user->account_status === 'active';

        if (! $isValid) {
            RateLimiter::hit($attemptKey, self::CODE_LIFETIME_SECONDS);

            if (RateLimiter::attempts($attemptKey) >= self::MAX_CODE_ATTEMPTS) {
                $this->deleteResetRow($normalizedEmail);
            }

            return $this->invalidCodeResponse($normalizedEmail);
        }

        DB::transaction(function () use ($user, $validated): void {
            $user->password = $validated['password'];
            $user->remember_token = Str::random(60);
            $user->save();

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        });

        RateLimiter::clear($attemptKey);

        return redirect()->route('login')->with('status', __('site.password_reset.success'));
    }

    private function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function userForEmail(string $normalizedEmail): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();
    }

    private function codeAttemptKey(string $normalizedEmail): string
    {
        return 'password-reset-code:'.hash('sha256', $normalizedEmail);
    }

    private function deleteResetRow(string $normalizedEmail): void
    {
        DB::table('password_reset_tokens')
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->delete();
    }

    private function invalidCodeResponse(string $email): RedirectResponse
    {
        return redirect()->route('password.reset')
            ->withErrors(['code' => __('site.password_reset.invalid_code')])
            ->withInput(['email' => $email]);
    }
}
