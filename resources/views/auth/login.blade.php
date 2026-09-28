@extends('layouts.app')

@section('title', __('site.login.title'))

@section('content')
    <main class="min-h-screen bg-beige px-4 py-12 text-ink sm:px-6">
        <section class="mx-auto w-full max-w-lg rounded-lg bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-3xl font-bold text-navy">{{ __('site.login.title') }}</h1>

            <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="login" class="mb-1 block font-medium">{{ __('site.login.identifier') }}</label>
                    <input
                        id="login"
                        name="login"
                        type="text"
                        value="{{ old('login') }}"
                        required
                        autocomplete="username"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none"
                    >
                    @error('login')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block font-medium">{{ __('site.login.password') }}</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none"
                    >
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <label for="remember" class="flex items-center gap-2">
                    <input id="remember" name="remember" type="checkbox" value="1" class="rounded border-gray-300 text-navy focus:ring-navy">
                    <span>{{ __('site.login.remember') }}</span>
                </label>
                @error('remember')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <button type="submit" class="w-full rounded-md bg-navy px-4 py-3 font-semibold text-white hover:bg-navy-dark">
                    {{ __('site.login.submit') }}
                </button>
            </form>
        </section>
    </main>
@endsection
