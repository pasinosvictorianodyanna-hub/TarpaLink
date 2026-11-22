<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PageSections\Pages\ListPageSections;
use App\Filament\Admin\Resources\Shops\Pages\ListShops;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\PageSection;
use App\Models\Shop;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin panel has to be a place where work gets done, not a read-only view.
 *
 * Two of the admin lists (Shops, Users) are hand-written Blade tables that
 * override Filament's list view, which means the `recordActions()` declared on
 * their table classes never render. Site content used to be a third; it is a
 * native Filament table now, so its actions are tested through Filament's own
 * action testing rather than by looking for a button in the HTML.
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

    public function test_the_content_list_is_a_native_filament_table(): void
    {
        $this->actingAs($this->admin());

        // The table paginates at ten, so assert against the first page rather
        // than every section.
        Livewire::test(ListPageSections::class)
            ->assertOk()
            ->assertCanSeeTableRecords(PageSection::orderBy('sort')->take(5)->get())
            ->assertTableFilterExists('status');
    }

    public function test_the_content_row_offers_the_right_action_for_its_status(): void
    {
        $this->actingAs($this->admin());

        $published = PageSection::where('status', 'published')->firstOrFail();
        $draft = PageSection::where('status', 'draft')->firstOrFail();

        Livewire::test(ListPageSections::class)
            ->assertActionVisible(TestAction::make('unpublish')->table($published))
            ->assertActionVisible(TestAction::make('viewOnSite')->table($published))
            ->assertActionHidden(TestAction::make('publish')->table($published))
            ->assertActionVisible(TestAction::make('publish')->table($draft))
            ->assertActionHidden(TestAction::make('unpublish')->table($draft));
    }

    public function test_unpublishing_takes_the_page_off_the_site(): void
    {
        $section = PageSection::where('key', 'page.about')->firstOrFail();

        $this->get('/about')->assertOk();

        $this->actingAs($this->admin());

        Livewire::test(ListPageSections::class)
            ->callAction(TestAction::make('unpublish')->table($section));

        $this->assertSame('draft', $section->fresh()->status);
        $this->get('/about')->assertNotFound();
    }

    public function test_publishing_puts_it_back(): void
    {
        $section = PageSection::where('key', 'page.about')->firstOrFail();
        $section->update(['status' => 'draft']);

        $this->get('/about')->assertNotFound();

        $this->actingAs($this->admin());

        Livewire::test(ListPageSections::class)
            ->callAction(TestAction::make('publish')->table($section));

        $this->assertSame('published', $section->fresh()->status);
        $this->get('/about')->assertOk();
    }

    public function test_publishing_a_landing_section_changes_the_landing_page(): void
    {
        $banner = PageSection::where('key', 'banner')->firstOrFail();
        $this->assertSame('draft', $banner->status, 'The banner is seeded as a draft.');

        $this->actingAs($this->admin());

        Livewire::test(ListPageSections::class)
            ->callAction(TestAction::make('publish')->table($banner));

        // The announcement bar is fed by this section, so publishing it is
        // observable on the storefront rather than only in the database.
        $props = $this->get('/')->assertOk()->viewData('props');
        $this->assertNotNull($props['banner']);
    }

    public function test_sections_can_be_published_in_bulk(): void
    {
        $ids = PageSection::whereIn('key', ['banner', 'page.help'])->pluck('id')->all();
        PageSection::whereIn('id', $ids)->update(['status' => 'draft']);

        $this->actingAs($this->admin());

        Livewire::test(ListPageSections::class)
            ->selectTableRecords($ids)
            ->callAction(TestAction::make('publishSelected')->table()->bulk());

        $this->assertSame(0, PageSection::whereIn('id', $ids)->where('status', '!=', 'published')->count());
    }

    public function test_a_section_knows_where_it_appears(): void
    {
        $about = PageSection::where('key', 'page.about')->firstOrFail();
        $hero = PageSection::where('key', 'hero')->firstOrFail();

        $this->assertSame(route('page', 'about'), $about->publicUrl());
        $this->assertSame(route('home'), $hero->publicUrl());
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
