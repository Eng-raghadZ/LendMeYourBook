<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_book_belongs_to_an_owner_and_category(): void
    {
        $book = Book::factory()->create();

        $this->assertInstanceOf(User::class, $book->owner);
        $this->assertSame($book->owner_id, $book->owner->id);
        $this->assertInstanceOf(Category::class, $book->category);
        $this->assertSame($book->category_id, $book->category->id);
    }

    public function test_users_and_categories_have_many_books(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $books = Book::factory()->count(2)->for($owner, 'owner')->for($category)->create();

        $this->assertCount(2, $owner->books);
        $this->assertTrue($owner->books->pluck('id')->diff($books->pluck('id'))->isEmpty());
        $this->assertCount(2, $category->books);
        $this->assertTrue($category->books->pluck('id')->diff($books->pluck('id'))->isEmpty());
    }

    public function test_book_factory_creates_valid_new_inventory(): void
    {
        $books = Book::factory()->count(10)->create();

        foreach ($books as $book) {
            $this->assertGreaterThanOrEqual(1, $book->total_copies);
            $this->assertGreaterThanOrEqual(0, $book->available_copies);
            $this->assertLessThanOrEqual($book->total_copies, $book->available_copies);
            $this->assertSame($book->total_copies, $book->available_copies);
            $this->assertGreaterThanOrEqual(0, (float) $book->rental_price);
            $this->assertGreaterThan(0, (float) $book->refundable_deposit);
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $book->rental_price);
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $book->refundable_deposit);
            $this->assertNull($book->archived_at);
        }
    }

    public function test_archived_at_is_nullable_and_cast_to_datetime(): void
    {
        $activeBook = Book::factory()->create();
        $archivedBook = Book::factory()->archived()->create();

        $this->assertNull($activeBook->archived_at);
        $this->assertInstanceOf(Carbon::class, $archivedBook->archived_at);
    }

    public function test_system_managed_fields_are_not_mass_assignable(): void
    {
        $book = new Book;
        $book->fill([
            'title' => 'A title',
            'author' => 'An author',
            'total_copies' => 3,
            'owner_id' => 999,
            'available_copies' => 999,
            'archived_at' => now(),
        ]);

        $this->assertSame('A title', $book->title);
        $this->assertSame('An author', $book->author);
        $this->assertSame(3, $book->total_copies);
        $this->assertNull($book->owner_id);
        $this->assertNull($book->available_copies);
        $this->assertNull($book->archived_at);
    }
}
