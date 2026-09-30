<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookController extends Controller
{
    public function mine(Request $request): View
    {
        $activeBooks = $request->user()->books()
            ->with('category')
            ->whereNull('archived_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'active_page')
            ->withQueryString();

        $archivedBooks = $request->user()->books()
            ->with('category')
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'archived_page')
            ->withQueryString();

        return view('books.mine', compact('activeBooks', 'archivedBooks'));
    }

    public function create(): View
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en')
            ->get();

        return view('books.create', compact('categories'));
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $book = $request->user()->books()->make($validated);
        $book->available_copies = $validated['total_copies'];
        $book->save();

        return redirect()->route('books.mine')
            ->with('status', __('site.books.created'));
    }

    public function edit(Book $book): View
    {
        Gate::authorize('update', $book);

        $categories = Category::query()
            ->where('is_active', true)
            ->orWhereKey($book->category_id)
            ->orderBy(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en')
            ->get();
        $unavailableCopies = $book->total_copies - $book->available_copies;

        return view('books.edit', compact('book', 'categories', 'unavailableCopies'));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        Gate::authorize('update', $book);

        $validated = $request->validated();

        DB::transaction(function () use ($book, $validated): void {
            $lockedBook = Book::query()
                ->whereKey($book->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('update', $lockedBook);

            $unavailableCopies = $lockedBook->total_copies - $lockedBook->available_copies;
            $newTotalCopies = $validated['total_copies'];

            if ($newTotalCopies < $unavailableCopies) {
                throw ValidationException::withMessages([
                    'total_copies' => __('site.books.inventory_minimum_error', [
                        'minimum' => $unavailableCopies,
                        'count' => $unavailableCopies,
                    ]),
                ]);
            }

            $lockedBook->fill($validated);
            $lockedBook->available_copies = $newTotalCopies - $unavailableCopies;
            $lockedBook->save();
        });

        return redirect()->route('books.edit', $book)
            ->with('status', __('site.books.updated'));
    }

    public function archive(Book $book): RedirectResponse
    {
        Gate::authorize('archive', $book);

        DB::transaction(function () use ($book): void {
            $lockedBook = Book::query()
                ->whereKey($book->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('archive', $lockedBook);

            $lockedBook->archived_at = now();
            $lockedBook->save();
        });

        return redirect()->route('books.mine')
            ->with('status', __('site.books.archived_successfully'));
    }

    public function unarchive(Book $book): RedirectResponse
    {
        Gate::authorize('unarchive', $book);

        DB::transaction(function () use ($book): void {
            $lockedBook = Book::query()
                ->whereKey($book->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('unarchive', $lockedBook);

            $lockedBook->archived_at = null;
            $lockedBook->save();
        });

        return redirect()->route('books.mine')
            ->with('status', __('site.books.unarchived_successfully'));
    }
}
