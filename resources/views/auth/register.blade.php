@extends('layouts.app')

@section('title', __('site.registration.title'))

@section('content')
    <main class="min-h-screen bg-beige px-4 py-12 text-ink sm:px-6">
        <section class="mx-auto w-full max-w-lg rounded-lg bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-3xl font-bold text-navy">{{ __('site.registration.title') }}</h1>

            <form method="POST" action="{{ route('register.store') }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="name" class="mb-1 block font-medium">{{ __('site.registration.name') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255" autocomplete="name" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="username" class="mb-1 block font-medium">{{ __('site.registration.username') }}</label>
                    <input id="username" name="username" type="text" value="{{ old('username') }}" required maxlength="255" autocomplete="username" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none">
                    @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="mb-1 block font-medium">{{ __('site.registration.email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block font-medium">{{ __('site.registration.password') }}</label>
                    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none">
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block font-medium">{{ __('site.registration.password_confirmation') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none">
                </div>

                <button type="submit" class="w-full rounded-md bg-navy px-4 py-3 font-semibold text-white hover:bg-navy-dark">
                    {{ __('site.registration.submit') }}
                </button>
            </form>
        </section>
    </main>
@endsection
