<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class MyBooksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('books.mine'))->assertRedirect(route('login'));
    }

    public function test_user_sees_only_their_own_active_and_archived_books(): void
    {
        $user = User::factory()->create();
        $ownActive = Book::factory()->for($user, 'owner')->create(['title' => 'Owned active title']);
        $ownArchived = Book::factory()->archived()->for($user, 'owner')->create(['title' => 'Owned archived title']);
        Book::factory()->create(['title' => 'Foreign active title']);
        Book::factory()->archived()->create(['title' => 'Foreign archived title']);

        $response = $this->actingAs($user)->get(route('books.mine'));

        $response->assertOk()
            ->assertSee($ownActive->title)
            ->assertSee($ownArchived->title)
            ->assertDontSee('Foreign active title')
            ->assertDontSee('Foreign archived title');

        $activeBooks = $response->viewData('activeBooks');
        $archivedBooks = $response->viewData('archivedBooks');

        $this->assertInstanceOf(LengthAwarePaginator::class, $activeBooks);
        $this->assertInstanceOf(LengthAwarePaginator::class, $archivedBooks);
        $this->assertSame([$ownActive->id], $activeBooks->pluck('id')->all());
        $this->assertSame([$ownArchived->id], $archivedBooks->pluck('id')->all());
        $this->assertTrue($activeBooks->first()->relationLoaded('category'));
        $this->assertTrue($archivedBooks->first()->relationLoaded('category'));
    }

    public function test_both_empty_states_render_with_an_add_book_link(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('books.mine'))
            ->assertOk()
            ->assertSee(__('site.books.no_active'))
            ->assertSee(__('site.books.no_archived'))
            ->assertSee(route('books.create'));
    }

    public function test_success_flash_is_rendered(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['status' => __('site.books.archived_successfully')])
            ->get(route('books.mine'))
            ->assertOk()
            ->assertSee(__('site.books.archived_successfully'));
    }

    public function test_category_name_follows_the_current_locale(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create([
            'name_en' => 'English Category Name',
            'name_ar' => 'اسم التصنيف العربي',
        ]);
        Book::factory()->for($user, 'owner')->for($category)->create();

        $this->actingAs($user)
            ->withSession(['locale' => 'ar'])
            ->get(route('books.mine'))
            ->assertSee('اسم التصنيف العربي')
            ->assertDontSee('English Category Name');
    }

    public function test_actions_match_policy_for_active_and_archived_books(): void
    {
        $user = User::factory()->create();
        $active = Book::factory()->for($user, 'owner')->create(['title' => 'Policy active title']);
        $archived = Book::factory()->archived()->for($user, 'owner')->create(['title' => 'Policy archived title']);

        $content = $this->actingAs($user)->get(route('books.mine'))->assertOk()->getContent();

        $activeCard = $this->bookCard($content, $active->id);
        $this->assertStringContainsString(route('books.edit', $active), $activeCard);
        $this->assertStringContainsString(route('books.archive', $active), $activeCard);
        $this->assertStringNotContainsString(route('books.unarchive', $active), $activeCard);

        $archivedCard = $this->bookCard($content, $archived->id);
        $this->assertStringNotContainsString(route('books.edit', $archived), $archivedCard);
        $this->assertStringNotContainsString(route('books.archive', $archived), $archivedCard);
        $this->assertStringContainsString(route('books.unarchive', $archived), $archivedCard);
        $this->assertStringNotContainsString('method="DELETE"', $archivedCard);
        $this->assertStringNotContainsString('value="DELETE"', $archivedCard);
    }

    public function test_independent_paginators_preserve_each_others_page_parameter(): void
    {
        $user = User::factory()->create();
        Book::factory()->count(12)->for($user, 'owner')->create();
        Book::factory()->archived()->count(12)->for($user, 'owner')->create();
        Book::factory()->count(2)->create();
        Book::factory()->archived()->count(2)->create();

        $response = $this->actingAs($user)->get(route('books.mine', [
            'active_page' => 2,
            'archived_page' => 2,
        ]));

        $response->assertOk();
        $activeBooks = $response->viewData('activeBooks');
        $archivedBooks = $response->viewData('archivedBooks');

        $this->assertSame('active_page', $activeBooks->getPageName());
        $this->assertSame('archived_page', $archivedBooks->getPageName());
        $this->assertCount(2, $activeBooks);
        $this->assertCount(2, $archivedBooks);
        $this->assertStringContainsString('archived_page=2', $activeBooks->url(1));
        $this->assertStringContainsString('active_page=2', $archivedBooks->url(1));
        $this->assertSame([$user->id], $activeBooks->pluck('owner_id')->unique()->values()->all());
        $this->assertSame([$user->id], $archivedBooks->pluck('owner_id')->unique()->values()->all());
        $this->assertTrue($activeBooks->every(fn (Book $book) => $book->archived_at === null));
        $this->assertTrue($archivedBooks->every(fn (Book $book) => $book->archived_at !== null));
    }

    public function test_arabic_pagination_labels_and_summary_are_translated(): void
    {
        $user = User::factory()->create();
        Book::factory()->count(12)->for($user, 'owner')->create();

        $this->actingAs($user)
            ->withSession(['locale' => 'ar'])
            ->get(route('books.mine', ['active_page' => 2]))
            ->assertOk()
            ->assertSee('السابق')
            ->assertSee('التالي')
            ->assertSee('عرض')
            ->assertSee('إلى')
            ->assertSee('من')
            ->assertSee('نتيجة');
    }

    private function bookCard(string $content, int $bookId): string
    {
        $startMarker = 'data-book-id="'.$bookId.'"';
        $start = strpos($content, $startMarker);

        $this->assertNotFalse($start);

        $next = strpos($content, 'data-book-id="', $start + strlen($startMarker));

        return substr($content, $start, $next === false ? null : $next - $start);
    }
}
