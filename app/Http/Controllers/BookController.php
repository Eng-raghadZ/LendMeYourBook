<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookController extends Controller
{
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

        return redirect()->route('books.create')
            ->with('status', __('site.books.created'));
    }
}
