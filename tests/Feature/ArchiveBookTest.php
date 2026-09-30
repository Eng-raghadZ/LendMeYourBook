<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_archive_or_unarchive(): void
    {
        $active = Book::factory()->create();
        $archived = Book::factory()->archived()->create();

        $this->patch(route('books.archive', $active))->assertRedirect(route('login'));
        $this->patch(route('books.unarchive', $archived))->assertRedirect(route('login'));
        $this->assertNull($active->fresh()->archived_at);
        $this->assertNotNull($archived->fresh()->archived_at);
    }

    public function test_owner_can_archive_an_active_book_without_changing_other_state(): void
    {
        $book = Book::factory()->create([
            'total_copies' => 5,
            'available_copies' => 2,
            'rental_price' => '7.25',
            'refundable_deposit' => '12.50',
        ]);
        $before = $this->protectedState($book);

        $this->actingAs($book->owner)
            ->patch(route('books.archive', $book))
            ->assertRedirect(route('books.mine'))
            ->assertSessionHas('status', __('site.books.archived_successfully'));

        $book->refresh();
        $this->assertNotNull($book->archived_at);
        $this->assertSame($before, $this->protectedState($book));
    }

    public function test_non_owner_cannot_archive_and_book_remains_unchanged(): void
    {
        $book = Book::factory()->create();
        $before = Book::query()->findOrFail($book->id)->getRawOriginal();

        $this->actingAs(User::factory()->create())
            ->patch(route('books.archive', $book))
            ->assertForbidden();

        $after = Book::query()->findOrFail($book->id)->getRawOriginal();
        $this->assertEqualsCanonicalizing($before, $after);
    }

    public function test_second_archive_is_forbidden_and_preserves_stored_timestamp_exactly(): void
    {
        $book = Book::factory()->archived()->create([
            'archived_at' => now()->subDays(3)->startOfSecond(),
        ]);
        $before = Book::query()->whereKey($book)->value('archived_at');

        $this->actingAs($book->owner)
            ->patch(route('books.archive', $book))
            ->assertForbidden();

        $after = Book::query()->whereKey($book)->value('archived_at');
        $this->assertSame($before->format('Y-m-d H:i:s.u'), $after->format('Y-m-d H:i:s.u'));
    }

    public function test_owner_can_unarchive_without_changing_other_state(): void
    {
        $book = Book::factory()->archived()->create([
            'total_copies' => 5,
            'available_copies' => 2,
            'rental_price' => '7.25',
            'refundable_deposit' => '12.50',
        ]);
        $before = $this->protectedState($book);

        $this->actingAs($book->owner)
            ->patch(route('books.unarchive', $book))
            ->assertRedirect(route('books.mine'))
            ->assertSessionHas('status', __('site.books.unarchived_successfully'));

        $book->refresh();
        $this->assertNull($book->archived_at);
        $this->assertSame($before, $this->protectedState($book));
    }

    public function test_non_owner_cannot_unarchive_and_book_remains_unchanged(): void
    {
        $book = Book::factory()->archived()->create();
        $before = Book::query()->findOrFail($book->id)->getRawOriginal();

        $this->actingAs(User::factory()->create())
            ->patch(route('books.unarchive', $book))
            ->assertForbidden();

        $after = Book::query()->findOrFail($book->id)->getRawOriginal();
        $this->assertEqualsCanonicalizing($before, $after);
    }

    public function test_unarchive_of_active_book_is_forbidden(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($book->owner)
            ->patch(route('books.unarchive', $book))
            ->assertForbidden();

        $this->assertNull($book->fresh()->archived_at);
    }

    public function test_archive_and_unarchive_ignore_malicious_request_values(): void
    {
        $book = Book::factory()->create([
            'total_copies' => 5,
            'available_copies' => 2,
            'rental_price' => '7.25',
            'refundable_deposit' => '12.50',
        ]);
        $otherUser = User::factory()->create();
        $otherCategory = Category::factory()->create();
        $before = $this->protectedState($book);
        $malicious = [
            'owner_id' => $otherUser->id,
            'category_id' => $otherCategory->id,
            'total_copies' => 99,
            'available_copies' => 99,
            'rental_price' => '99.99',
            'refundable_deposit' => '88.88',
            'archived_at' => now()->subYear(),
        ];

        $this->actingAs($book->owner)->patch(route('books.archive', $book), $malicious)->assertRedirect(route('books.mine'));
        $book->refresh();
        $this->assertNotNull($book->archived_at);
        $this->assertSame($before, $this->protectedState($book));

        $malicious['archived_at'] = now()->addYear();
        $this->actingAs($book->owner)->patch(route('books.unarchive', $book), $malicious)->assertRedirect(route('books.mine'));
        $book->refresh();
        $this->assertNull($book->archived_at);
        $this->assertSame($before, $this->protectedState($book));
    }

    public function test_archive_blocks_edit_and_update_then_unarchive_restores_edit_access(): void
    {
        $book = Book::factory()->create();
        $owner = $book->owner;

        $this->actingAs($owner)->patch(route('books.archive', $book))->assertRedirect(route('books.mine'));
        $this->actingAs($owner)->get(route('books.edit', $book))->assertForbidden();
        $this->actingAs($owner)->put(route('books.update', $book), [])->assertForbidden();

        $this->actingAs($owner)->patch(route('books.unarchive', $book))->assertRedirect(route('books.mine'));
        $this->actingAs($owner)->get(route('books.edit', $book))->assertOk();
    }

    /** @return array<string, int|string> */
    private function protectedState(Book $book): array
    {
        $book->refresh();

        return [
            'owner_id' => $book->owner_id,
            'category_id' => $book->category_id,
            'total_copies' => $book->total_copies,
            'available_copies' => $book->available_copies,
            'rental_price' => $book->rental_price,
            'refundable_deposit' => $book->refundable_deposit,
        ];
    }
}
