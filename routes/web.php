<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegistrationController;

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
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->name('register.store');
});

Route::view('/verify-email-notice', 'auth.verify-email-notice')
    ->middleware('auth')
    ->name('verify-email-notice');
