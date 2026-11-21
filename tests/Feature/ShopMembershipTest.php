<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\RelationManagers\ShopsRelationManager;
use App\Models\Shop;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * docs/ISSUES.md #9: a shop owner is an ordinary user row plus a shop_users
 * entry. If the panel cannot create that entry, it cannot create a shop owner.
 */
class ShopMembershipTest extends TestCase
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

    public function test_promoting_a_customer_to_shop_owner_grants_panel_access(): void
    {
        $customer = User::where('email', 'marisol@example.com')->firstOrFail();
        $shop = Shop::where('slug', 'tinta-and-co')->firstOrFail();

        // Before: a customer cannot reach the shop panel at all.
        $this->actingAs($customer)->get('/shop')->assertForbidden();

        // The request above left the shop panel current; point back at admin so
        // the admin resource resolves its own routes.
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // 1. Give them the role.
        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $customer->getKey()])
            ->fillForm(['role' => 'shop_owner'])
            ->call('save')
            ->assertHasNoFormErrors();

        // 2. Give them a shop, through the relation manager that owns the pivot.
        Livewire::actingAs($this->admin())
            ->test(ShopsRelationManager::class, [
                'ownerRecord' => $customer->fresh(),
                'pageClass' => EditUser::class,
            ])
            ->callAction(
                TestAction::make('attach')->table(),
                data: ['recordId' => $shop->id, 'role' => 'owner'],
            );

        $this->assertDatabaseHas('shop_users', [
            'user_id' => $customer->id,
            'shop_id' => $shop->id,
            'role' => 'owner',
        ]);

        $fresh = $customer->fresh();

        $this->assertSame('shop_owner', $fresh->role);
        $this->assertSame($shop->id, $fresh->activeShop()?->id);
        $this->actingAs($fresh)->get('/shop')->assertOk();
    }

    public function test_a_shop_can_be_attached_as_staff_rather_than_owner(): void
    {
        $person = User::where('email', 'dennis@example.com')->firstOrFail();
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($this->admin())
            ->test(ShopsRelationManager::class, [
                'ownerRecord' => $person,
                'pageClass' => EditUser::class,
            ])
            ->callAction(
                TestAction::make('attach')->table(),
                data: ['recordId' => $shop->id, 'role' => 'staff'],
            );

        $this->assertDatabaseHas('shop_users', [
            'user_id' => $person->id,
            'shop_id' => $shop->id,
            'role' => 'staff',
        ]);
    }

    public function test_editing_a_customer_leaves_their_shop_memberships_alone(): void
    {
        // A staff member: has a pivot row but is not a shop owner.
        $staff = User::where('email', 'dennis@example.com')->firstOrFail();
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();
        $shop->staff()->attach($staff->id, ['role' => 'staff']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $staff->getKey()])
            ->fillForm(['city' => 'Manila'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('shop_users', [
            'user_id' => $staff->id,
            'shop_id' => $shop->id,
            'role' => 'staff',
        ]);
    }

    public function test_a_shop_owner_with_no_shop_is_refused_by_the_panel(): void
    {
        $orphan = User::create([
            'name' => 'Orphan Owner',
            'email' => 'orphan@example.com',
            'role' => 'shop_owner',
            'password' => bcrypt('password'),
        ]);

        $this->assertNull($orphan->activeShop());
        $this->actingAs($orphan)->get('/shop')->assertForbidden();
    }
}
