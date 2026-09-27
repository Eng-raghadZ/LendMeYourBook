<?php

namespace App\Http\Controllers;

use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $verificationCode = (string) random_int(100000, 999999);

        $user = new User;
        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->email = $validated['email'];
        $user->password = $validated['password'];
        $user->role = 'client';
        $user->account_status = 'active';
        $user->verification_code = Hash::make($verificationCode);
        $user->verification_expiry = now()->addMinutes(10);
        $user->save();

        try {
            Mail::to($user->email)->send(new EmailVerificationCodeMail($verificationCode));
        } catch (Throwable) {
            $user->verification_code = null;
            $user->verification_expiry = null;
            $user->save();

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route('verification.notice')
                ->withErrors(['email' => __('site.verification.delivery_failed')]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
