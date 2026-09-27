@extends('layouts.app')

@section('title', 'Layout Test')

@section('content')
    <main class="min-h-screen p-8">
        <h1 class="text-4xl font-bold">
            {{ __('site.welcome') }}
        </h1>

        <p class="mt-4 text-gray-600">
            Current locale: {{ app()->getLocale() }}
        </p>
    </main>
@endsection