@extends('layouts.app')

@section('title', __('site.password_reset.reset_title'))

@section('content')
    <main class="min-h-screen bg-beige px-4 py-12 text-ink sm:px-6">
        <section class="mx-auto w-full max-w-lg rounded-lg bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-3xl font-bold text-navy">{{ __('site.password_reset.reset_title') }}</h1>

            @if (session('status'))
                <p class="mt-4 text-sm text-success" role="status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-1 block font-medium">{{ __('site.password_reset.email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="code" class="mb-1 block font-medium">{{ __('site.password_reset.code') }}</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none">
                    @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block font-medium">{{ __('site.password_reset.new_password') }}</label>
                    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none">
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block font-medium">{{ __('site.password_reset.password_confirmation') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none">
                </div>

                <button type="submit" class="w-full rounded-md bg-navy px-4 py-3 font-semibold text-white hover:bg-navy-dark">
                    {{ __('site.password_reset.reset_button') }}
                </button>
            </form>

            <a href="{{ route('password.request') }}" class="mt-4 block text-center text-sm font-medium text-navy hover:underline">
                {{ __('site.password_reset.request_another') }}
            </a>
        </section>
    </main>
@endsection
