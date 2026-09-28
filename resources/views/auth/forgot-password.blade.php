@extends('layouts.app')

@section('title', __('site.password_reset.forgot_title'))

@section('content')
    <main class="min-h-screen bg-beige px-4 py-12 text-ink sm:px-6">
        <section class="mx-auto w-full max-w-lg rounded-lg bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-3xl font-bold text-navy">{{ __('site.password_reset.forgot_title') }}</h1>
            <p class="mt-4 text-gray-700">{{ __('site.password_reset.forgot_instructions') }}</p>

            <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-1 block font-medium">{{ __('site.password_reset.email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full rounded-md bg-navy px-4 py-3 font-semibold text-white hover:bg-navy-dark">
                    {{ __('site.password_reset.send_code') }}
                </button>
            </form>
        </section>
    </main>
@endsection
