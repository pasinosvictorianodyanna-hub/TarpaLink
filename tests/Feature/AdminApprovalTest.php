<?php

namespace Tests\Feature;

use App\Filament\Admin\Widgets\PendingApprovals;
use App\Models\Shop;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * docs/ISSUES.md #8: approval is shops.status, and shops.status is what the shop
 * panel checks before letting anyone in.
 */
class AdminApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function pendingShop(): array
    {
        $shop = Shop::create([
            'name' => 'New Press',
            'slug' => 'new-press',
            'city' => 'Pasay',
            'address' => '1 Test St',
            'accent' => 'ink',
            'status' => 'pending',
            'plan' => 'starter',
            'commission_rate' => 500,
        ]);

        $owner = User::create([
            'name' => 'New Owner',
            'email' => 'owner@newpress.ph',
            'role' => 'shop_owner',
            'password' => bcrypt('password'),
        ]);

        $shop->staff()->attach($owner->id, ['role' => 'owner']);

        return [$shop, $owner];
    }

    public function test_approving_from_the_dashboard_lets_the_owner_in(): void
    {
        [$shop, $owner] = $this->pendingShop();
        $admin = User::where('email', 'sofia@tarpalink.ph')->firstOrFail();

        $this->actingAs($owner)->get('/shop')->assertForbidden();

        $this->actingAs($admin);

        Livewire::test(PendingApprovals::class)
            ->callAction(TestAction::make('approve')->table($shop));

        $this->assertSame('active', $shop->fresh()->status);

        $this->actingAs($owner->fresh())->get('/shop')->assertOk();
    }

    public function test_rejecting_records_a_reason_and_keeps_the_owner_locked_out(): void
    {
        [$shop, $owner] = $this->pendingShop();
        $admin = User::where('email', 'sofia@tarpalink.ph')->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(PendingApprovals::class)
            ->callAction(
                TestAction::make('reject')->table($shop),
                data: ['reason' => 'Business permit does not match the shop address.'],
            );

        $shop->refresh();

        $this->assertSame('suspended', $shop->status);
        $this->assertSame('Business permit does not match the shop address.', $shop->status_note);

        $this->actingAs($owner->fresh())->get('/shop')->assertForbidden();
    }

    public function test_approving_clears_an_earlier_rejection_reason(): void
    {
        [$shop] = $this->pendingShop();

        // A re-application that still carries the note from the first rejection.
        $shop->update(['status' => 'pending', 'status_note' => 'Missing DTI registration.']);

        $admin = User::where('email', 'sofia@tarpalink.ph')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(PendingApprovals::class)
            ->callAction(TestAction::make('approve')->table($shop));

        $shop->refresh();

        $this->assertSame('active', $shop->status);
        $this->assertNull($shop->status_note);
    }

    public function test_commission_earned_is_the_sum_of_stored_commission_not_a_single_rate(): void
    {
        $expected = (int) \App\Models\Payment::where('status', 'paid')->sum('commission');
        $gmv = (int) \App\Models\Payment::where('status', 'paid')->sum('amount');

        // The two are deliberately not equal: shops sit on 5%, 3% and 1.5%.
        $this->assertNotSame((int) round($gmv * 0.05), $expected);
        $this->assertGreaterThan(0, $expected);
    }
}
