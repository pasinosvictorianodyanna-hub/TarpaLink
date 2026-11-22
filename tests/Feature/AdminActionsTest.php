<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Shops\Pages\ListShops;
use App\Filament\Admin\Resources\Users\UserResource;
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

    /*
     * The content screens moved to the CMS application. Their tests moved
     * with them — a table and the tests that hold it to its promises belong
     * in the same repository. What remains here is the transactional admin
     * this engine still owns: shops and users.
     */

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
