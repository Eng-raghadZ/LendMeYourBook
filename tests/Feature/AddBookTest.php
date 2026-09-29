<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AddBookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_access_add_book_page(): void
    {
        $this->get(route('books.create'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_create_a_book(): void
    {
        $category = Category::factory()->create();

        $this->post(route('books.store'), $this->validBookData($category))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('books', 0);
    }

    public function test_authenticated_user_can_access_add_book_page_with_only_active_categories(): void
    {
        $user = User::factory()->create();
        $activeCategory = Category::factory()->create([
            'name_en' => 'Active Category',
            'is_active' => true,
        ]);
        $inactiveCategory = Category::factory()->create([
            'name_en' => 'Inactive Category',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('books.create'))
            ->assertOk()
            ->assertSee($activeCategory->name_en)
            ->assertDontSee($inactiveCategory->name_en);
    }

    public function test_authenticated_user_can_create_a_valid_book(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user)
            ->post(route('books.store'), $this->validBookData($category))
            ->assertRedirect(route('books.create'))
            ->assertSessionHas('status', __('site.books.created'));

        $book = Book::sole();

        $this->assertTrue($book->owner->is($user));
        $this->assertTrue($book->category->is($category));
        $this->assertSame(3, $book->total_copies);
        $this->assertSame(3, $book->available_copies);
        $this->assertNull($book->archived_at);
        $this->assertSame('5.00', $book->rental_price);
        $this->assertSame('10.00', $book->refundable_deposit);
    }

    public function test_boundary_pricing_values_are_accepted(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user)
            ->post(route('books.store'), $this->validBookData($category, [
                'rental_price' => '0',
                'refundable_deposit' => '0.01',
            ]))
            ->assertRedirect(route('books.create'))
            ->assertSessionHasNoErrors();

        $book = Book::sole();

        $this->assertSame('0.00', $book->rental_price);
        $this->assertSame('0.01', $book->refundable_deposit);
    }

    #[DataProvider('invalidTotalCopiesProvider')]
    public function test_total_copies_must_be_a_positive_integer(mixed $value): void
    {
        $this->assertBookInputIsInvalid('total_copies', $value);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidTotalCopiesProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'non-integer' => ['1.5'],
        ];
    }

    #[DataProvider('invalidRentalPriceProvider')]
    public function test_rental_price_rejects_invalid_values(mixed $value): void
    {
        $this->assertBookInputIsInvalid('rental_price', $value);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidRentalPriceProvider(): array
    {
        return [
            'negative' => ['-1.00'],
            'too many decimal places' => ['1.001'],
            'above database maximum' => ['100000000.00'],
        ];
    }

    #[DataProvider('invalidRefundableDepositProvider')]
    public function test_refundable_deposit_rejects_invalid_values(mixed $value): void
    {
        $this->assertBookInputIsInvalid('refundable_deposit', $value);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidRefundableDepositProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-0.01'],
            'too many decimal places' => ['10.001'],
            'above database maximum' => ['100000000.00'],
        ];
    }

    public function test_required_fields_are_enforced(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('books.store'), [])
            ->assertSessionHasErrors([
                'category_id',
                'title',
                'author',
                'total_copies',
                'rental_price',
                'refundable_deposit',
            ]);

        $this->assertDatabaseCount('books', 0);
    }

    public function test_description_is_optional(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $data = $this->validBookData($category);
        unset($data['description']);

        $this->actingAs($user)
            ->post(route('books.store'), $data)
            ->assertRedirect(route('books.create'))
            ->assertSessionHasNoErrors();

        $this->assertNull(Book::sole()->description);
    }

    public function test_inactive_category_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->post(route('books.store'), $this->validBookData($category))
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_nonexistent_category_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->make(['id' => 999999]);

        $this->actingAs($user)
            ->post(route('books.store'), $this->validBookData($category))
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_system_managed_fields_cannot_be_injected(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user)
            ->post(route('books.store'), $this->validBookData($category, [
                'owner_id' => $otherUser->id,
                'available_copies' => 999,
                'archived_at' => now(),
            ]))
            ->assertRedirect(route('books.create'));

        $book = Book::sole();

        $this->assertTrue($book->owner->is($user));
        $this->assertSame($book->total_copies, $book->available_copies);
        $this->assertNull($book->archived_at);
    }

    private function assertBookInputIsInvalid(string $field, mixed $value): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user)
            ->post(route('books.store'), $this->validBookData($category, [$field => $value]))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validBookData(Category $category, array $overrides = []): array
    {
        return [
            'category_id' => $category->id,
            'title' => 'A Test Book',
            'author' => 'Test Author',
            'description' => 'A useful description.',
            'total_copies' => 3,
            'rental_price' => '5.00',
            'refundable_deposit' => '10.00',
            ...$overrides,
        ];
    }
}
