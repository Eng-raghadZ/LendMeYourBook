<?php

use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/lang/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['ar', 'en'], true), 404);

    session()->put('locale', $locale);

    return back();
})->name('lang.switch');

Route::get('/test-layout', fn () => view('test-layout'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/verify-email', [EmailVerificationController::class, 'show'])->name('verification.notice');
    Route::post('/verify-email', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:verification')
        ->name('verification.verify');
    Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:verification-resend')
        ->name('verification.resend');
});
