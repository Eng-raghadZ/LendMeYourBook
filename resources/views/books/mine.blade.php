@extends('layouts.app')

@section('title', __('site.books.mine'))

@section('content')
    <main class="min-h-screen bg-beige px-4 py-12 text-ink sm:px-6">
        <div class="mx-auto w-full max-w-6xl space-y-10">
            <section class="rounded-lg bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h1 class="text-3xl font-bold text-navy">{{ __('site.books.mine') }}</h1>
                    <a href="{{ route('books.create') }}" class="rounded-md bg-navy px-4 py-3 font-semibold text-white hover:bg-navy-dark">
                        {{ __('site.books.add') }}
                    </a>
                </div>

                @if (session('status'))
                    <p class="mt-4 text-sm text-success" role="status">{{ session('status') }}</p>
                @endif
            </section>

            <section id="active-books" class="space-y-5" aria-labelledby="active-books-heading">
                <h2 id="active-books-heading" class="text-2xl font-bold text-navy">{{ __('site.books.active') }}</h2>

                @forelse ($activeBooks as $book)
                    <article class="rounded-lg bg-white p-6 shadow-sm" data-book-id="{{ $book->id }}" data-book-state="active">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 class="text-xl font-bold text-navy">{{ $book->title }}</h3>
                                <p class="mt-1 text-gray-700">{{ $book->author }}</p>
                                <p class="mt-2 text-sm text-gray-600">{{ app()->getLocale() === 'ar' ? $book->category->name_ar : $book->category->name_en }}</p>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                @can('update', $book)
                                    <a href="{{ route('books.edit', $book) }}" class="rounded-md border border-navy px-4 py-2 font-semibold text-navy hover:bg-beige" data-action="edit">
                                        {{ __('site.books.edit_action') }}
                                    </a>
                                @endcan
                                @can('archive', $book)
                                    <form method="POST" action="{{ route('books.archive', $book) }}" data-action="archive">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-md bg-navy px-4 py-2 font-semibold text-white hover:bg-navy-dark">{{ __('site.books.archive') }}</button>
                                    </form>
                                @endcan
                                @can('unarchive', $book)
                                    <form method="POST" action="{{ route('books.unarchive', $book) }}" data-action="unarchive">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-md bg-navy px-4 py-2 font-semibold text-white hover:bg-navy-dark">{{ __('site.books.unarchive') }}</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                        <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div><dt class="font-semibold">{{ __('site.books.total_copies') }}</dt><dd dir="ltr">{{ $book->total_copies }}</dd></div>
                            <div><dt class="font-semibold">{{ __('site.books.available_copies') }}</dt><dd dir="ltr">{{ $book->available_copies }}</dd></div>
                            <div><dt class="font-semibold">{{ __('site.books.rental_price') }}</dt><dd dir="ltr">{{ $book->rental_price }}</dd></div>
                            <div><dt class="font-semibold">{{ __('site.books.refundable_deposit') }}</dt><dd dir="ltr">{{ $book->refundable_deposit }}</dd></div>
                        </dl>
                    </article>
                @empty
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <p>{{ __('site.books.no_active') }}</p>
                        <a href="{{ route('books.create') }}" class="mt-3 inline-block font-semibold text-navy hover:text-navy-dark">{{ __('site.books.add') }}</a>
                    </div>
                @endforelse

                <div data-paginator="active">{{ $activeBooks->links() }}</div>
            </section>

            <section id="archived-books" class="space-y-5" aria-labelledby="archived-books-heading">
                <h2 id="archived-books-heading" class="text-2xl font-bold text-navy">{{ __('site.books.archived_books') }}</h2>

                @forelse ($archivedBooks as $book)
                    <article class="rounded-lg border border-gray-300 bg-white p-6 shadow-sm" data-book-id="{{ $book->id }}" data-book-state="archived">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <span class="inline-block rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700">{{ __('site.books.archived') }}</span>
                                <h3 class="mt-2 text-xl font-bold text-navy">{{ $book->title }}</h3>
                                <p class="mt-1 text-gray-700">{{ $book->author }}</p>
                                <p class="mt-2 text-sm text-gray-600">{{ app()->getLocale() === 'ar' ? $book->category->name_ar : $book->category->name_en }}</p>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                @can('update', $book)
                                    <a href="{{ route('books.edit', $book) }}" class="rounded-md border border-navy px-4 py-2 font-semibold text-navy hover:bg-beige" data-action="edit">{{ __('site.books.edit_action') }}</a>
                                @endcan
                                @can('archive', $book)
                                    <form method="POST" action="{{ route('books.archive', $book) }}" data-action="archive">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-md bg-navy px-4 py-2 font-semibold text-white hover:bg-navy-dark">{{ __('site.books.archive') }}</button>
                                    </form>
                                @endcan
                                @can('unarchive', $book)
                                    <form method="POST" action="{{ route('books.unarchive', $book) }}" data-action="unarchive">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-md bg-navy px-4 py-2 font-semibold text-white hover:bg-navy-dark">{{ __('site.books.unarchive') }}</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                        <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div><dt class="font-semibold">{{ __('site.books.total_copies') }}</dt><dd dir="ltr">{{ $book->total_copies }}</dd></div>
                            <div><dt class="font-semibold">{{ __('site.books.available_copies') }}</dt><dd dir="ltr">{{ $book->available_copies }}</dd></div>
                            <div><dt class="font-semibold">{{ __('site.books.rental_price') }}</dt><dd dir="ltr">{{ $book->rental_price }}</dd></div>
                            <div><dt class="font-semibold">{{ __('site.books.refundable_deposit') }}</dt><dd dir="ltr">{{ $book->refundable_deposit }}</dd></div>
                        </dl>
                    </article>
                @empty
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p>{{ __('site.books.no_archived') }}</p></div>
                @endforelse

                <div data-paginator="archived">{{ $archivedBooks->links() }}</div>
            </section>
        </div>
    </main>
@endsection
