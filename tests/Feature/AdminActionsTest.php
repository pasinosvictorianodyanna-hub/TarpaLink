<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PageSections\Pages\ListPageSections;
use App\Filament\Admin\Resources\Shops\Pages\ListShops;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\PageSection;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin panel has to be a place where work gets done, not a read-only view.
 *
 * Three of the four admin lists are hand-written Blade tables that override
 * Filament's list view, which means the `recordActions()` declared on their
 * table classes never render. That is invisible in a browser until you look for
 * a button that is not there: the row shows a status and nothing else, and the
 * only way to act is to guess a URL. These tests exist so the buttons stay.
 */
class AdminActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function admin(): User
    {
        return User::where('email', 'sofia@tarpalink.ph')->firstOrFail();
    }

    public function test_the_content_list_offers_a_publish_and_unpublish_button(): void
    {
        $html = $this->actingAs($this->admin())
            ->get('/admin/site-content')
            ->assertOk()
            ->getContent();

        // The seed has thirteen published sections and one draft, so both
        // directions are on screen at once.
        $this->assertStringContainsString('wire:click="unpublish(', $html);
        $this->assertStringContainsString('wire:click="publish(', $html);
    }

    public function test_unpublishing_from_the_list_takes_the_page_off_the_site(): void
    {
        $section = PageSection::where('key', 'page.about')->firstOrFail();

        $this->get('/about')->assertOk();

        $this->actingAs($this->admin());
        Livewire::test(ListPageSections::class)->call('unpublish', $section->id);

        $this->assertSame('draft', $section->fresh()->status);
        $this->get('/about')->assertNotFound();
    }

    public function test_publishing_from_the_list_puts_it_back(): void
    {
        $section = PageSection::where('key', 'page.about')->firstOrFail();
        $section->update(['status' => 'draft']);

        $this->get('/about')->assertNotFound();

        $this->actingAs($this->admin());
        Livewire::test(ListPageSections::class)->call('publish', $section->id);

        $this->assertSame('published', $section->fresh()->status);
        $this->get('/about')->assertOk();
    }

    public function test_publishing_a_landing_section_changes_the_landing_page(): void
    {
        $banner = PageSection::where('key', 'banner')->firstOrFail();
        $this->assertSame('draft', $banner->status, 'The banner is seeded as a draft.');

        $this->actingAs($this->admin());
        Livewire::test(ListPageSections::class)->call('publish', $banner->id);

        // The announcement bar is fed by this section, so publishing it is
        // observable on the storefront rather than only in the database.
        $props = $this->get('/')->assertOk()->viewData('props');
        $this->assertNotNull($props['banner']);
    }

    public function test_a_section_row_links_to_where_it_actually_appears(): void
    {
        $this->actingAs($this->admin());

        $page = Livewire::test(ListPageSections::class)->instance();
        $about = PageSection::where('key', 'page.about')->firstOrFail();
        $hero = PageSection::where('key', 'hero')->firstOrFail();

        $this->assertSame(route('page', 'about'), $page->urlFor($about));
        $this->assertSame(route('home'), $page->urlFor($hero));
    }

    public function test_the_shops_list_opens_on_every_shop_not_only_the_queue(): void
    {
        $total = Shop::count();
        $this->assertGreaterThan(1, $total);

        $this->actingAs($this->admin());
        $component = Livewire::test(ListShops::class);

        // This page used to force status = 'pending' in mount(), so an admin saw
        // one row and a header reading "1 shop" while the rest were hidden — and
        // the status dropdown still said "All statuses".
        $this->assertSame('', $component->instance()->status);
        $this->assertSame($total, $component->instance()->total());
    }

    public function test_the_queue_still_sorts_first_without_being_the_only_thing_shown(): void
    {
        $this->actingAs($this->admin());

        $first = Livewire::test(ListShops::class)->instance()->shops()->items()[0] ?? null;

        $this->assertNotNull($first);
        $this->assertSame('pending', $first->status);
    }

    public function test_an_admin_can_approve_a_shop_from_the_list(): void
    {
        $shop = Shop::where('status', 'pending')->firstOrFail();

        $this->actingAs($this->admin());
        Livewire::test(ListShops::class)->call('approve', $shop->id);

        $this->assertSame('active', $shop->fresh()->status);
    }

    public function test_the_users_list_offers_a_way_into_each_user(): void
    {
        $user = User::where('email', 'jomar@tarpalink.ph')->firstOrFail();

        $this->actingAs($this->admin())
            ->get('/admin/users')
            ->assertOk()
            ->assertSee(UserResource::getUrl('edit', ['record' => $user]), false);
    }
}
