<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\RegistrationController;
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
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendCode'])
        ->middleware('throttle:password-reset-request')
        ->name('password.email');
    Route::get('/reset-password', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset-attempt')
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/my-books', [BookController::class, 'mine'])->name('books.mine');
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}', [BookController::class, 'show'])->whereNumber('book')->name('books.show');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->whereNumber('book')->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->whereNumber('book')->name('books.update');
    Route::patch('/books/{book}/archive', [BookController::class, 'archive'])->whereNumber('book')->name('books.archive');
    Route::patch('/books/{book}/unarchive', [BookController::class, 'unarchive'])->whereNumber('book')->name('books.unarchive');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/verify-email', [EmailVerificationController::class, 'show'])->name('verification.notice');
    Route::post('/verify-email', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:verification')
        ->name('verification.verify');
    Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:verification-resend')
        ->name('verification.resend');
});
