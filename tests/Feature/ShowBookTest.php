<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowBookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $book = Book::factory()->create();

        $this->get(route('books.show', $book))->assertRedirect(route('login'));
    }

    public function test_owner_can_view_all_active_book_details(): void
    {
        $owner = User::factory()->create(['username' => 'detail_owner']);
        $category = Category::factory()->create(['name_en' => 'Distinct English Category']);
        $book = Book::factory()->for($owner, 'owner')->for($category)->create([
            'title' => 'Distinct Detail Title',
            'author' => 'Distinct Detail Author',
            'total_copies' => 7,
            'available_copies' => 4,
            'rental_price' => '12.34',
            'refundable_deposit' => '56.78',
        ]);

        $this->actingAs($owner)
            ->withSession(['locale' => 'en'])
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('Distinct Detail Title')
            ->assertSee('Distinct Detail Author')
            ->assertSee('Distinct English Category')
            ->assertSee('7')
            ->assertSee('4')
            ->assertSee('12.34')
            ->assertSee('56.78')
            ->assertSee('detail_owner');
    }

    public function test_authenticated_non_owner_can_view_active_book(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('books.show', $book))
            ->assertOk();
    }

    public function test_archived_owner_can_view_book_and_archived_state(): void
    {
        $book = Book::factory()->archived()->create();

        $this->actingAs($book->owner)
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertSee(__('site.books.archived'));
    }

    public function test_archived_non_owner_receives_not_found_without_title_disclosure(): void
    {
        $book = Book::factory()->archived()->create(['title' => 'Hidden Archived Title 987']);

        $this->actingAs(User::factory()->create())
            ->get(route('books.show', $book))
            ->assertNotFound()
            ->assertDontSee('Hidden Archived Title 987');
    }

    public function test_invalid_and_missing_book_routes_return_not_found_without_shadowing_create(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/books/999999')->assertNotFound();
        $this->actingAs($user)->get('/books/abc')->assertNotFound();
        $this->actingAs($user)->get(route('books.create'))->assertOk();
    }

    public function test_description_is_rendered_only_when_present(): void
    {
        $owner = User::factory()->create();
        $withDescription = Book::factory()->for($owner, 'owner')->create([
            'description' => 'Distinct present description.',
        ]);
        $withoutDescription = Book::factory()->for($owner, 'owner')->create(['description' => null]);

        $this->actingAs($owner)
            ->get(route('books.show', $withDescription))
            ->assertOk()
            ->assertSee(__('site.books.description'))
            ->assertSee('Distinct present description.');

        $this->actingAs($owner)
            ->get(route('books.show', $withoutDescription))
            ->assertOk()
            ->assertDontSee('id="book-description-heading"', false);
    }

    public function test_category_name_follows_english_and_arabic_locales(): void
    {
        $category = Category::factory()->create([
            'name_en' => 'Locale English Category',
            'name_ar' => 'تصنيف عربي مميز',
        ]);
        $book = Book::factory()->for($category)->create();

        $this->actingAs($book->owner)
            ->withSession(['locale' => 'en'])
            ->get(route('books.show', $book))
            ->assertSee('Locale English Category')
            ->assertDontSee('تصنيف عربي مميز');

        $this->actingAs($book->owner)
            ->withSession(['locale' => 'ar'])
            ->get(route('books.show', $book))
            ->assertSee('تصنيف عربي مميز')
            ->assertDontSee('Locale English Category');
    }

    public function test_only_owner_username_is_exposed(): void
    {
        $owner = User::factory()->create([
            'username' => 'privacy_owner_2468',
            'email' => 'private-owner-2468@example.test',
            'phone' => '0599992468',
            'whatsapp' => '0566662468',
        ]);
        $book = Book::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('privacy_owner_2468')
            ->assertDontSee('private-owner-2468@example.test')
            ->assertDontSee('0599992468')
            ->assertDontSee('0566662468');
    }

    public function test_user_provided_html_is_escaped_and_never_rendered_raw(): void
    {
        $rawTag = '<script>alert("xss-test-2468")</script>';
        $book = Book::factory()->create([
            'title' => $rawTag,
            'description' => $rawTag,
        ]);

        $this->actingAs($book->owner)
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertDontSee($rawTag, false)
            ->assertSee(e($rawTag), false);
    }

    public function test_edit_and_back_links_follow_owner_and_archive_rules(): void
    {
        $owner = User::factory()->create();
        $active = Book::factory()->for($owner, 'owner')->create();
        $archived = Book::factory()->archived()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->get(route('books.show', $active))
            ->assertSee(route('books.edit', $active))
            ->assertSee(route('books.mine'));

        $this->actingAs(User::factory()->create())
            ->get(route('books.show', $active))
            ->assertDontSee(route('books.edit', $active))
            ->assertDontSee(route('books.mine'));

        $this->actingAs($owner)
            ->get(route('books.show', $archived))
            ->assertDontSee(route('books.edit', $archived))
            ->assertSee(route('books.mine'));
    }

    public function test_my_books_has_view_links_for_active_and_archived_books(): void
    {
        $owner = User::factory()->create();
        $active = Book::factory()->for($owner, 'owner')->create();
        $archived = Book::factory()->archived()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->get(route('books.mine'))
            ->assertOk()
            ->assertSee(route('books.show', $active))
            ->assertSee(route('books.show', $archived));
    }

    public function test_details_page_contains_no_future_domain_or_archive_actions(): void
    {
        $book = Book::factory()->create();
        $content = $this->actingAs($book->owner)
            ->get(route('books.show', $book))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(route('books.archive', $book), $content);
        $this->assertStringNotContainsString(route('books.unarchive', $book), $content);
        $this->assertStringNotContainsString('data-action="borrow"', $content);
        $this->assertStringNotContainsString('data-action="loan"', $content);
        $this->assertStringNotContainsString('data-action="reserve"', $content);
    }
}
