<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The SRS names a Shop staff actor who works the queue and the board but cannot
 * change prices or billing. The data model had the pivot role; no user could be
 * created with it and no panel would admit one. These tests hold both halves.
 */
class ShopStaffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function shop(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    private function staff(): User
    {
        $staff = User::firstOrCreate(
            ['email' => 'mika@inkwell.ph'],
            ['name' => 'Mika Santos', 'password' => 'password', 'role' => 'staff'],
        );

        $staff->shops()->syncWithoutDetaching([$this->shop()->id => ['role' => 'staff']]);

        return $staff;
    }

    public function test_staff_can_work_the_console_and_the_queue(): void
    {
        $this->actingAs($this->staff())
            ->get('/console')->assertOk()
            ->assertSee('Shop staff');

        $this->actingAs($this->staff())->get('/shop')->assertOk();
        $this->actingAs($this->staff())->get('/shop/jobs')->assertOk();
        $this->actingAs($this->staff())->get('/shop/production-board')->assertOk();
    }

    public function test_staff_cannot_open_price_or_billing_screens(): void
    {
        $staff = $this->actingAs($this->staff());

        foreach (['/shop/price-matrix', '/shop/billing', '/shop/services', '/shop/quantity-discounts'] as $path) {
            $response = $staff->get($path);

            $this->assertContains($response->status(), [403], "{$path} should refuse staff");
        }
    }

    public function test_staff_cannot_change_a_service_or_a_price(): void
    {
        $staff = $this->staff();
        $service = $this->shop()->services()->firstOrFail();

        $this->assertTrue(Gate::forUser($staff)->denies('create', Service::class));
        $this->assertTrue(Gate::forUser($staff)->denies('update', $service));
        $this->assertTrue(Gate::forUser($staff)->denies('delete', $service));
    }

    public function test_staff_can_still_view_a_job_and_move_it_along(): void
    {
        $staff = $this->staff();
        $job = $this->shop()->jobs()->firstOrFail();

        $this->assertTrue(Gate::forUser($staff)->allows('view', $job));
        $this->assertTrue(Gate::forUser($staff)->allows('update', $job));
    }

    public function test_signing_in_takes_staff_to_the_console(): void
    {
        $this->staff();

        $this->post('/login', [
            'email' => 'mika@inkwell.ph',
            'password' => 'password',
        ])->assertRedirect(route('console.dashboard'));
    }

    public function test_the_console_hides_price_and_billing_links_from_staff(): void
    {
        $html = $this->actingAs($this->staff())->get('/console')->assertOk()->getContent();

        preg_match('/data-props="(.*?)"/s', $html, $match);
        $props = html_entity_decode(html_entity_decode($match[1] ?? ''));
        $data = json_decode($props, true);

        $labels = collect($data['shell']['groups'] ?? [])
            ->flatMap(fn (array $group) => array_column($group['items'], 'label'))
            ->all();

        $this->assertContains('Production board', $labels);
        $this->assertContains('Reports', $labels);
        $this->assertNotContains('Price matrix', $labels);
        $this->assertNotContains('Billing', $labels);
    }

    public function test_an_owner_keeps_full_access(): void
    {
        $owner = User::where('email', 'kevin@inkwell.ph')->firstOrFail();

        $this->assertTrue($owner->canManagePrices());
        $this->actingAs($owner)->get('/shop/price-matrix')->assertOk();
        $this->actingAs($owner)->get('/shop/billing')->assertOk();
    }
}
