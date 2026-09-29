<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EditBookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_access_edit_page(): void
    {
        $book = Book::factory()->create();

        $this->get(route('books.edit', $book))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_submit_update(): void
    {
        $book = Book::factory()->create();

        $this->put(route('books.update', $book), $this->validBookData($book))
            ->assertRedirect(route('login'));

        $this->assertSame($book->title, $book->fresh()->title);
    }

    public function test_owner_can_access_edit_page(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($book->owner)
            ->get(route('books.edit', $book))
            ->assertOk()
            ->assertSee(__('site.books.edit'));
    }

    public function test_non_owner_gets_forbidden_on_edit(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('books.edit', $book))
            ->assertForbidden();
    }

    public function test_non_owner_gets_forbidden_on_update_without_changing_book(): void
    {
        $book = Book::factory()->create(['title' => 'Original title']);

        $this->actingAs(User::factory()->create())
            ->put(route('books.update', $book), $this->validBookData($book, ['title' => 'Changed']))
            ->assertForbidden();

        $this->assertSame('Original title', $book->fresh()->title);
    }

    public function test_owner_cannot_edit_or_update_an_archived_book(): void
    {
        $book = Book::factory()->archived()->create();

        $this->actingAs($book->owner)
            ->get(route('books.edit', $book))
            ->assertForbidden();

        $this->actingAs($book->owner)
            ->put(route('books.update', $book), $this->validBookData($book))
            ->assertForbidden();
    }

    public function test_owner_can_update_all_editable_book_fields(): void
    {
        $book = Book::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($book->owner)->put(route('books.update', $book), [
            'category_id' => $category->id,
            'title' => 'Updated title',
            'author' => 'Updated author',
            'description' => 'Updated description',
            'total_copies' => 4,
            'rental_price' => '7.25',
            'refundable_deposit' => '12.50',
        ]);

        $response->assertRedirect(route('books.edit', $book))
            ->assertSessionHas('status', __('site.books.updated'));

        $book->refresh();
        $this->assertTrue($book->category->is($category));
        $this->assertSame('Updated title', $book->title);
        $this->assertSame('Updated author', $book->author);
        $this->assertSame('Updated description', $book->description);
        $this->assertSame('7.25', $book->rental_price);
        $this->assertSame('12.50', $book->refundable_deposit);
    }

    #[DataProvider('inventoryUpdateProvider')]
    public function test_inventory_is_recalculated_while_preserving_unavailable_copies(
        int $oldTotal,
        int $oldAvailable,
        int $newTotal,
        int $expectedAvailable,
    ): void {
        $book = Book::factory()->create([
            'total_copies' => $oldTotal,
            'available_copies' => $oldAvailable,
        ]);

        $this->actingAs($book->owner)
            ->put(route('books.update', $book), $this->validBookData($book, [
                'total_copies' => $newTotal,
            ]))
            ->assertRedirect(route('books.edit', $book))
            ->assertSessionHasNoErrors();

        $book->refresh();
        $this->assertSame($newTotal, $book->total_copies);
        $this->assertSame($expectedAvailable, $book->available_copies);
        $this->assertGreaterThanOrEqual(1, $book->total_copies);
        $this->assertGreaterThanOrEqual(0, $book->available_copies);
        $this->assertLessThanOrEqual($book->total_copies, $book->available_copies);
    }

    /** @return array<string, array{int, int, int, int}> */
    public static function inventoryUpdateProvider(): array
    {
        return [
            'increase with all available' => [3, 3, 5, 5],
            'increase with unavailable copies' => [5, 2, 8, 5],
            'allowed decrease' => [5, 5, 2, 2],
            'exact unavailable boundary' => [5, 2, 3, 0],
            'unchanged total' => [5, 2, 5, 2],
        ];
    }

    public function test_total_copies_below_unavailable_boundary_rolls_back_all_changes(): void
    {
        $originalCategory = Category::factory()->create();
        $newCategory = Category::factory()->create();
        $book = Book::factory()->for($originalCategory)->create([
            'title' => 'Original title',
            'total_copies' => 5,
            'available_copies' => 2,
            'rental_price' => '5.00',
            'refundable_deposit' => '10.00',
        ]);

        $this->actingAs($book->owner)
            ->from(route('books.edit', $book))
            ->put(route('books.update', $book), $this->validBookData($book, [
                'category_id' => $newCategory->id,
                'title' => 'Changed title',
                'total_copies' => 2,
                'rental_price' => '99.00',
                'refundable_deposit' => '50.00',
            ]))
            ->assertRedirect(route('books.edit', $book))
            ->assertSessionHasErrors([
                'total_copies' => __('site.books.inventory_minimum_error', [
                    'minimum' => 3,
                    'count' => 3,
                ]),
            ]);

        $book->refresh();
        $this->assertSame($originalCategory->id, $book->category_id);
        $this->assertSame('Original title', $book->title);
        $this->assertSame(5, $book->total_copies);
        $this->assertSame(2, $book->available_copies);
        $this->assertSame('5.00', $book->rental_price);
        $this->assertSame('10.00', $book->refundable_deposit);
    }

    #[DataProvider('invalidTotalCopiesProvider')]
    public function test_static_invalid_totals_are_rejected(mixed $value): void
    {
        $this->assertBookInputIsInvalid('total_copies', $value);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidTotalCopiesProvider(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'non-integer' => ['1.5']];
    }

    public function test_malicious_system_managed_fields_are_ignored(): void
    {
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 2]);
        $originalOwnerId = $book->owner_id;
        $otherUser = User::factory()->create();

        $this->actingAs($book->owner)
            ->put(route('books.update', $book), $this->validBookData($book, [
                'total_copies' => 8,
                'owner_id' => $otherUser->id,
                'available_copies' => 999,
                'archived_at' => now(),
            ]))
            ->assertRedirect(route('books.edit', $book));

        $book->refresh();
        $this->assertSame($originalOwnerId, $book->owner_id);
        $this->assertSame(5, $book->available_copies);
        $this->assertNull($book->archived_at);
    }

    public function test_active_category_is_accepted_and_nonexistent_category_is_rejected(): void
    {
        $book = Book::factory()->create();
        $activeCategory = Category::factory()->create(['is_active' => true]);

        $this->actingAs($book->owner)
            ->put(route('books.update', $book), $this->validBookData($book, ['category_id' => $activeCategory->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($activeCategory->id, $book->fresh()->category_id);

        $this->actingAs($book->owner)
            ->put(route('books.update', $book), $this->validBookData($book, ['category_id' => 999999]))
            ->assertSessionHasErrors('category_id');
    }

    public function test_switching_to_another_inactive_category_is_rejected(): void
    {
        $book = Book::factory()->create();
        $inactiveCategory = Category::factory()->create(['is_active' => false]);

        $this->actingAs($book->owner)
            ->put(route('books.update', $book), $this->validBookData($book, ['category_id' => $inactiveCategory->id]))
            ->assertSessionHasErrors('category_id');
    }

    public function test_current_inactive_category_can_be_retained_and_is_the_only_inactive_option(): void
    {
        $currentCategory = Category::factory()->create([
            'name_en' => 'Current Inactive',
            'is_active' => false,
        ]);
        $unrelatedInactive = Category::factory()->create([
            'name_en' => 'Unrelated Inactive',
            'is_active' => false,
        ]);
        $book = Book::factory()->for($currentCategory)->create();

        $this->actingAs($book->owner)
            ->withSession(['locale' => 'en'])
            ->get(route('books.edit', $book))
            ->assertOk()
            ->assertSee('Current Inactive')
            ->assertSee(__('site.books.inactive_marker'))
            ->assertSee('value="'.$currentCategory->id.'" selected', false)
            ->assertDontSee('Unrelated Inactive');

        $this->actingAs($book->owner)
            ->put(route('books.update', $book), $this->validBookData($book))
            ->assertSessionHasNoErrors();

        $this->assertSame($currentCategory->id, $book->fresh()->category_id);
        $this->assertNotSame($unrelatedInactive->id, $book->fresh()->category_id);
    }

    public function test_book_can_switch_from_current_inactive_category_to_active_category(): void
    {
        $inactiveCategory = Category::factory()->create(['is_active' => false]);
        $activeCategory = Category::factory()->create(['is_active' => true]);
        $book = Book::factory()->for($inactiveCategory)->create();

        $this->actingAs($book->owner)
            ->put(route('books.update', $book), $this->validBookData($book, ['category_id' => $activeCategory->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($activeCategory->id, $book->fresh()->category_id);
    }

    public function test_inventory_helper_is_shown_only_when_copies_are_unavailable(): void
    {
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 2]);

        $this->actingAs($book->owner)
            ->get(route('books.edit', $book))
            ->assertSee(__('site.books.inventory_minimum_help', ['count' => 3]));

        $book->update(['total_copies' => 2]);
        $book->available_copies = 2;
        $book->save();

        $this->actingAs($book->owner)
            ->get(route('books.edit', $book))
            ->assertDontSee(__('site.books.inventory_minimum_help', ['count' => 0]));
    }

    #[DataProvider('validPricingProvider')]
    public function test_valid_pricing_values_are_accepted(string $field, string $value): void
    {
        $book = Book::factory()->create();

        $this->actingAs($book->owner)
            ->put(route('books.update', $book), $this->validBookData($book, [$field => $value]))
            ->assertSessionHasNoErrors();

        $this->assertSame(number_format((float) $value, 2, '.', ''), $book->fresh()->{$field});
    }

    /** @return array<string, array{string, string}> */
    public static function validPricingProvider(): array
    {
        return [
            'free rental' => ['rental_price', '0'],
            'positive rental' => ['rental_price', '4.25'],
            'minimum deposit' => ['refundable_deposit', '0.01'],
            'positive deposit' => ['refundable_deposit', '12.50'],
        ];
    }

    #[DataProvider('invalidPricingProvider')]
    public function test_invalid_pricing_values_are_rejected(string $field, string $value): void
    {
        $this->assertBookInputIsInvalid($field, $value);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidPricingProvider(): array
    {
        return [
            'negative rental' => ['rental_price', '-0.01'],
            'rental decimals' => ['rental_price', '1.001'],
            'rental above max' => ['rental_price', '100000000.00'],
            'zero deposit' => ['refundable_deposit', '0'],
            'negative deposit' => ['refundable_deposit', '-0.01'],
            'deposit decimals' => ['refundable_deposit', '1.001'],
            'deposit above max' => ['refundable_deposit', '100000000.00'],
        ];
    }

    private function assertBookInputIsInvalid(string $field, mixed $value): void
    {
        $book = Book::factory()->create();

        $this->actingAs($book->owner)
            ->put(route('books.update', $book), $this->validBookData($book, [$field => $value]))
            ->assertSessionHasErrors($field);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validBookData(Book $book, array $overrides = []): array
    {
        return [
            'category_id' => $book->category_id,
            'title' => $book->title,
            'author' => $book->author,
            'description' => $book->description,
            'total_copies' => $book->total_copies,
            'rental_price' => $book->rental_price,
            'refundable_deposit' => $book->refundable_deposit,
            ...$overrides,
        ];
    }
}
