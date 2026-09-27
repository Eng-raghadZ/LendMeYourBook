<?php

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