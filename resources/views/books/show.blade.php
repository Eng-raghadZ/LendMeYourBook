@extends('layouts.app')

@section('title', __('site.books.details'))

@section('content')
    <main class="min-h-screen bg-beige px-4 py-12 text-ink sm:px-6">
        <article class="mx-auto w-full max-w-3xl rounded-lg bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    @if ($book->archived_at !== null)
                        <span class="inline-block rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700">
                            {{ __('site.books.archived') }}
                        </span>
                    @endif
                    <h1 class="mt-2 text-3xl font-bold text-navy">{{ $book->title }}</h1>
                    <p class="mt-2 text-lg text-gray-700">{{ $book->author }}</p>
                </div>

                @can('update', $book)
                    <a href="{{ route('books.edit', $book) }}" class="rounded-md border border-navy px-4 py-2 font-semibold text-navy hover:bg-beige" data-action="edit">
                        {{ __('site.books.edit_action') }}
                    </a>
                @endcan
            </div>

            <dl class="mt-8 grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="font-semibold text-navy">{{ __('site.books.category') }}</dt>
                    <dd class="mt-1">{{ app()->getLocale() === 'ar' ? $book->category->name_ar : $book->category->name_en }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-navy">{{ __('site.books.owner') }}</dt>
                    <dd class="mt-1">{{ $book->owner->username }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-navy">{{ __('site.books.total_copies') }}</dt>
                    <dd class="mt-1" dir="ltr">{{ $book->total_copies }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-navy">{{ __('site.books.available_copies') }}</dt>
                    <dd class="mt-1" dir="ltr">{{ $book->available_copies }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-navy">{{ __('site.books.rental_price') }}</dt>
                    <dd class="mt-1" dir="ltr">{{ $book->rental_price }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-navy">{{ __('site.books.refundable_deposit') }}</dt>
                    <dd class="mt-1" dir="ltr">{{ $book->refundable_deposit }}</dd>
                </div>
            </dl>

            @if ($book->description !== null && trim($book->description) !== '')
                <section class="mt-8" aria-labelledby="book-description-heading">
                    <h2 id="book-description-heading" class="text-xl font-bold text-navy">{{ __('site.books.description') }}</h2>
                    <p class="mt-3 text-gray-700">{{ $book->description }}</p>
                </section>
            @endif

            @if (auth()->id() === $book->owner_id)
                <a href="{{ route('books.mine') }}" class="mt-8 inline-block font-semibold text-navy hover:text-navy-dark">
                    {{ __('site.books.back_to_mine') }}
                </a>
            @endif
        </article>
    </main>
@endsection
