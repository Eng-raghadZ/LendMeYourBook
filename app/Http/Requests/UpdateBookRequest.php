<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Book $book */
        $book = $this->route('book');

        return $this->user()->can('update', $book);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Book $book */
        $book = $this->route('book');

        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(
                    fn (Builder $query) => $query->where('is_active', true)
                        ->orWhere('id', $book->category_id),
                ),
            ],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'total_copies' => ['required', 'integer', 'min:1'],
            'rental_price' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:99999999.99',
            ],
            'refundable_deposit' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0.01',
                'max:99999999.99',
            ],
        ];
    }
}
