@extends('layouts.app')

@section('title', __('site.registration.verification_required'))

@section('content')
    <main class="min-h-screen bg-beige px-4 py-12 text-ink sm:px-6">
        <section class="mx-auto w-full max-w-lg rounded-lg bg-white p-6 shadow-sm sm:p-8">
            <p class="text-lg text-navy">{{ __('site.registration.verification_required') }}</p>
        </section>
    </main>
@endsection
