@extends('layouts.app')

@section('title', __('site.verification.page_title'))

@section('content')
    <main class="min-h-screen bg-beige px-4 py-12 text-ink sm:px-6">
        <section class="mx-auto w-full max-w-lg rounded-lg bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-3xl font-bold text-navy">{{ __('site.verification.page_title') }}</h1>
            <p class="mt-4 text-gray-700">{{ __('site.verification.instructions') }}</p>

            @if (session('status'))
                <p class="mt-4 text-sm text-success" role="status">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <div class="mt-4 space-y-1 text-sm text-red-600" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('verification.verify') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="verification_code" class="mb-1 block font-medium">{{ __('site.verification.code_label') }}</label>
                    <input
                        id="verification_code"
                        name="verification_code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none"
                    >
                    @error('verification_code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full rounded-md bg-navy px-4 py-3 font-semibold text-white hover:bg-navy-dark">
                    {{ __('site.verification.verify_button') }}
                </button>
            </form>

            <form method="POST" action="{{ route('verification.resend') }}" class="mt-4">
                @csrf
                <button type="submit" class="w-full rounded-md border border-navy px-4 py-3 font-semibold text-navy hover:bg-beige">
                    {{ __('site.verification.resend_button') }}
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="w-full rounded-md px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    {{ __('site.login.logout') }}
                </button>
            </form>
        </section>
    </main>
@endsection
