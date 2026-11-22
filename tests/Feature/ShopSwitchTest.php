<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Shop;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Switching which shop the panel is looking at.
 *
 * The failure this guards against is not a crash — it is an owner of two shops
 * quietly editing the second shop's prices while the topbar says the first
 * shop's name. Everything here is about the panel never showing one shop's rows
 * under another shop's heading.
 */
class ShopSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Filament::setCurrentPanel('shop');
    }

    private function owner(): User
    {
        return User::where('email', 'kevin@inkwell.ph')->firstOrFail();
    }

    private function inkwell(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    /** Give the owner a second, active shop and return it. */
    private function secondShop(): Shop
    {
        $second = Shop::where('slug', 'halftone-hub')->firstOrFail();

        $this->owner()->shops()->syncWithoutDetaching([
            $second->id => ['role' => 'owner'],
        ]);

        return $second;
    }

    public function test_a_single_shop_owner_sees_no_picker(): void
    {
        $this->assertCount(1, $this->owner()->shops);

        $this->actingAs($this->owner())
            ->get('/shop')
            ->assertOk()
            ->assertDontSee('Switch shop');
    }

    public function test_an_owner_of_two_shops_sees_the_picker(): void
    {
        $this->secondShop();

        $this->actingAs($this->owner())
            ->get('/shop')
            ->assertOk()
            ->assertSee('Switch shop')
            ->assertSee('Halftone Hub');
    }

    public function test_switching_changes_which_shops_jobs_the_panel_shows(): void
    {
        $second = $this->secondShop();

        $theirJob = Job::where('shop_id', $second->id)->firstOrFail();
        $ourJob = Job::where('shop_id', $this->inkwell()->id)->firstOrFail();

        $this->actingAs($this->owner())
            ->get('/shop/jobs')
            ->assertOk()
            ->assertSee($ourJob->reference);

        $this->actingAs($this->owner())
            ->post('/shop/switch-shop', ['shop_id' => $second->id])
            ->assertRedirect();

        $this->actingAs($this->owner())
            ->get('/shop/jobs')
            ->assertOk()
            ->assertSee($theirJob->reference)
            ->assertDontSee($ourJob->reference);
    }

    public function test_the_brand_follows_the_chosen_shop(): void
    {
        $second = $this->secondShop();

        $this->actingAs($this->owner())
            ->post('/shop/switch-shop', ['shop_id' => $second->id])
            ->assertRedirect();

        $this->actingAs($this->owner())
            ->get('/shop')
            ->assertOk()
            ->assertSee($second->name);
    }

    public function test_an_owner_cannot_switch_to_a_shop_they_do_not_run(): void
    {
        $stranger = Shop::where('slug', 'inkwell-press')->firstOrFail();

        // Somebody else's shop, chosen by id from a form the user controls.
        $foreign = Shop::where('id', '!=', $stranger->id)->firstOrFail();

        $this->actingAs($this->owner())
            ->post('/shop/switch-shop', ['shop_id' => $foreign->id])
            ->assertForbidden();

        $this->assertSame($stranger->id, $this->owner()->fresh()->currentShop()?->id);
    }

    public function test_a_forged_shop_id_in_the_session_is_ignored(): void
    {
        $foreign = Shop::where('slug', 'halftone-hub')->firstOrFail();

        // The picker is not the only thing that can write to the session, so
        // `currentShop()` has to treat the value as a preference to be checked
        // rather than a decision to be honoured.
        $this->actingAs($this->owner())
            ->withSession(['shop_id' => $foreign->id])
            ->get('/shop')
            ->assertOk()
            ->assertDontSee($foreign->name);

        $this->assertSame($this->inkwell()->id, $this->owner()->fresh()->currentShop()?->id);
    }

    public function test_the_admin_panel_never_shows_the_picker(): void
    {
        $admin = User::where('email', 'sofia@tarpalink.ph')->firstOrFail();

        // The hook is registered app-wide so both panels get the same topbar.
        // An administrator has no shops, so the view has to render nothing
        // rather than an empty dropdown on the platform's own dashboard.
        $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('Switch shop');
    }

    public function test_an_owner_cannot_switch_to_a_paused_shop(): void
    {
        $second = $this->secondShop();
        $second->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($this->owner())
            ->post('/shop/switch-shop', ['shop_id' => $second->id])
            ->assertForbidden();

        // A switch that reported success and then did nothing would leave the
        // topbar naming one shop and the figures belonging to another.
        $this->assertSame($this->inkwell()->id, $this->owner()->fresh()->currentShop()?->id);
    }

    public function test_an_owner_parked_on_a_suspended_shop_falls_back_to_a_trading_one(): void
    {
        $second = $this->secondShop();

        $this->actingAs($this->owner())
            ->post('/shop/switch-shop', ['shop_id' => $this->inkwell()->id])
            ->assertRedirect();

        $this->inkwell()->forceFill(['status' => 'suspended'])->save();

        // Suspended shops are refused at the door, so falling back to the one
        // that is still trading is the difference between a nuisance and a
        // lockout with no way out.
        $this->actingAs($this->owner())->get('/shop')->assertOk();

        $this->assertSame($second->id, $this->owner()->fresh()->currentShop()?->id);
    }
}
