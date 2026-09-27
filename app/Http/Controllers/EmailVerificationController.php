<?php

namespace App\Http\Controllers;

use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class EmailVerificationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->email_verified_at !== null) {
            return redirect('/');
        }

        return view('auth.verify-email-notice');
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'verification_code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return redirect('/');
        }

        if ($user->verification_code === null || $user->verification_expiry === null) {
            return redirect()->route('verification.notice')->withErrors([
                'verification_code' => __('site.verification.invalid_code'),
            ]);
        }

        if (now()->greaterThanOrEqualTo($user->verification_expiry)) {
            return redirect()->route('verification.notice')->withErrors([
                'verification_code' => __('site.verification.expired_code'),
            ]);
        }

        if (! Hash::check($validated['verification_code'], $user->verification_code)) {
            return redirect()->route('verification.notice')->withErrors([
                'verification_code' => __('site.verification.invalid_code'),
            ]);
        }

        $user->email_verified_at = now();
        $user->verification_code = null;
        $user->verification_expiry = null;
        $user->save();

        return redirect('/')->with('status', __('site.verification.success'));
    }

    public function resend(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return redirect('/');
        }

        if ($user->verification_expiry !== null) {
            $lastSentAt = $user->verification_expiry->copy()->subMinutes(10);

            if (now()->lt($lastSentAt->copy()->addMinute())) {
                return redirect()->route('verification.notice')->withErrors([
                    'resend' => __('site.verification.cooldown'),
                ]);
            }
        }

        $verificationCode = (string) random_int(100000, 999999);

        try {
            Mail::to($user->email)->send(new EmailVerificationCodeMail($verificationCode));
        } catch (Throwable) {
            return redirect()->route('verification.notice')->withErrors([
                'resend' => __('site.verification.delivery_failed'),
            ]);
        }

        $user->verification_code = Hash::make($verificationCode);
        $user->verification_expiry = now()->addMinutes(10);
        $user->save();

        return redirect()->route('verification.notice')->with('status', __('site.verification.sent'));
    }
}
