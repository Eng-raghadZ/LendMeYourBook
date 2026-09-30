@extends('layouts.app')

@section('title', __('site.books.edit'))

@section('content')
    <main class="min-h-screen bg-beige px-4 py-12 text-ink sm:px-6">
        <section class="mx-auto w-full max-w-2xl rounded-lg bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-3xl font-bold text-navy">{{ __('site.books.edit') }}</h1>
            <p class="mt-3 text-gray-700">{{ __('site.books.edit_intro') }}</p>
            <a href="{{ route('books.mine') }}" class="mt-4 inline-block font-semibold text-navy hover:text-navy-dark">
                {{ __('site.books.back_to_mine') }}
            </a>

            @if (session('status'))
                <p class="mt-4 text-sm text-success" role="status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('books.update', $book) }}" class="mt-8 space-y-5" novalidate>
                @csrf
                @method('PUT')

                <div>
                    <label for="category_id" class="mb-1 block font-medium">{{ __('site.books.category') }}</label>
                    <select id="category_id" name="category_id" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 focus:border-navy focus:outline-none">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id', $book->category_id) === (string) $category->id)>
                                {{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_en }}
                                @unless ($category->is_active) {{ __('site.books.inactive_marker') }} @endunless
                            </option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="title" class="mb-1 block font-medium">{{ __('site.books.title') }}</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $book->title) }}" required maxlength="255" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none">
                    @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="author" class="mb-1 block font-medium">{{ __('site.books.author') }}</label>
                    <input id="author" name="author" type="text" value="{{ old('author', $book->author) }}" required maxlength="255" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none">
                    @error('author') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="description" class="mb-1 block font-medium">{{ __('site.books.description') }}</label>
                    <textarea id="description" name="description" rows="5" aria-describedby="description-help" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none">{{ old('description', $book->description) }}</textarea>
                    <p id="description-help" class="mt-1 text-sm text-gray-600">{{ __('site.books.description_help') }}</p>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="total_copies" class="mb-1 block font-medium">{{ __('site.books.total_copies') }}</label>
                    <input id="total_copies" name="total_copies" type="number" value="{{ old('total_copies', $book->total_copies) }}" required min="1" step="1" @if ($unavailableCopies > 0) aria-describedby="inventory-minimum-help" @endif class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none" dir="ltr">
                    @if ($unavailableCopies > 0)
                        <p id="inventory-minimum-help" class="mt-1 text-sm text-gray-600">{{ __('site.books.inventory_minimum_help', ['count' => $unavailableCopies]) }}</p>
                    @endif
                    @error('total_copies') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="rental_price" class="mb-1 block font-medium">{{ __('site.books.rental_price') }}</label>
                    <input id="rental_price" name="rental_price" type="number" value="{{ old('rental_price', $book->rental_price) }}" required min="0" max="99999999.99" step="0.01" inputmode="decimal" aria-describedby="rental-price-help" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none" dir="ltr">
                    <p id="rental-price-help" class="mt-1 text-sm text-gray-600">{{ __('site.books.rental_price_help') }}</p>
                    @error('rental_price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="refundable_deposit" class="mb-1 block font-medium">{{ __('site.books.refundable_deposit') }}</label>
                    <input id="refundable_deposit" name="refundable_deposit" type="number" value="{{ old('refundable_deposit', $book->refundable_deposit) }}" required min="0.01" max="99999999.99" step="0.01" inputmode="decimal" aria-describedby="refundable-deposit-help" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-navy focus:outline-none" dir="ltr">
                    <p id="refundable-deposit-help" class="mt-1 text-sm text-gray-600">{{ __('site.books.refundable_deposit_help') }}</p>
                    @error('refundable_deposit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="w-full rounded-md bg-navy px-4 py-3 font-semibold text-white hover:bg-navy-dark">
                    {{ __('site.books.save_changes') }}
                </button>
            </form>
        </section>
    </main>
@endsection
