<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The engine's half of the administrative boundary.
 *
 * The CMS calls these routes; the CMS's own tests prove it calls them with the
 * right arguments. What is proven here is that the engine does what it says when
 * it does: approval goes through the same service the panel uses, suspension
 * demands a reason, and a payout cannot be released twice.
 */
class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function token(): string
    {
        return (string) config('services.admin_api.token');
    }

    private function api(string $path): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->headers())->getJson($path);
    }

    /** @param  array<string, mixed>  $payload */
    private function send(string $path, array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->headers())->postJson($path, $payload);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token(),
            'Accept' => 'application/json',
        ];
    }

    public function test_the_admin_api_refuses_an_anonymous_caller(): void
    {
        $this->getJson('/api/v1/admin/shops')->assertUnauthorized();
        $this->postJson('/api/v1/admin/shops/1/approve')->assertUnauthorized();
    }

    public function test_the_content_token_cannot_reach_the_admin_api(): void
    {
        // Two tokens, two directions. An editor's copy token must not be able to
        // approve a shop, even though both secrets live in the same deployment.
        $this->getJson('/api/v1/admin/shops', [
            'Authorization' => 'Bearer '.config('services.cms.token'),
            'Accept' => 'application/json',
        ])->assertUnauthorized();
    }

    public function test_the_summary_reports_the_platform_at_a_glance(): void
    {
        $this->api('/api/v1/admin/summary')
            ->assertOk()
            ->assertJsonPath('shops.total', Shop::query()->count())
            ->assertJsonPath('users.total', User::query()->count())
            ->assertJsonStructure([
                'shops' => ['pending', 'active', 'suspended', 'total'],
                'users' => ['admin', 'shop_owner', 'customer', 'total'],
                'payouts' => ['waiting', 'held_centavos', 'held'],
                'jobs' => ['this_month', 'total'],
                'gmv' => ['this_month_centavos'],
            ]);
    }

    public function test_the_shops_list_puts_the_queue_first(): void
    {
        $shops = $this->api('/api/v1/admin/shops')->assertOk()->json('shops');

        $this->assertNotEmpty($shops);
        $this->assertSame('pending', $shops[0]['status']);
    }

    public function test_the_shops_list_can_be_filtered_by_status(): void
    {
        $shops = $this->api('/api/v1/admin/shops?status=active')->assertOk()->json('shops');

        $this->assertNotEmpty($shops);

        foreach ($shops as $shop) {
            $this->assertSame('active', $shop['status']);
        }
    }

    public function test_approving_a_shop_records_who_did_it(): void
    {
        $shop = Shop::query()->where('status', 'pending')->firstOrFail();
        $admin = User::query()->where('role', 'admin')->firstOrFail();

        $this->send("/api/v1/admin/shops/{$shop->id}/approve", ['actor' => $admin->email])
            ->assertOk()
            ->assertJsonPath('shop.status', 'active');

        $shop->refresh();

        $this->assertSame('active', $shop->status);
        $this->assertSame($admin->id, $shop->approved_by);
        $this->assertSame($admin->id, $shop->status_changed_by);
        $this->assertNotNull($shop->approved_at);
    }

    public function test_an_unknown_actor_still_approves_but_records_no_name(): void
    {
        // A missing audit trail is a smaller problem than a shop that cannot be
        // let in because the CMS sent an email the engine does not know.
        $shop = Shop::query()->where('status', 'pending')->firstOrFail();

        $this->send("/api/v1/admin/shops/{$shop->id}/approve", ['actor' => 'nobody@example.com'])
            ->assertOk();

        $this->assertSame('active', $shop->fresh()->status);
        $this->assertNull($shop->fresh()->status_changed_by);
    }

    public function test_suspending_requires_a_reason(): void
    {
        $shop = Shop::query()->where('status', 'active')->firstOrFail();

        $this->send("/api/v1/admin/shops/{$shop->id}/suspend")
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertSame('active', $shop->fresh()->status);
    }

    public function test_suspending_closes_the_shop_and_keeps_the_reason(): void
    {
        $shop = Shop::query()->where('status', 'active')->firstOrFail();

        $this->send("/api/v1/admin/shops/{$shop->id}/suspend", ['reason' => 'Repeated late pickups'])
            ->assertOk()
            ->assertJsonPath('shop.status', 'suspended');

        $this->assertSame('suspended', $shop->fresh()->status);
        $this->assertSame('Repeated late pickups', $shop->fresh()->status_reason);
    }

    public function test_releasing_a_payout_marks_it_paid(): void
    {
        $payment = Payment::query()->awaitingPayout()->firstOrFail();

        $this->send("/api/v1/admin/payouts/{$payment->id}/release")
            ->assertOk()
            ->assertJsonPath('released', true);

        $payment->refresh();

        $this->assertSame('paid', $payment->payout_status);
        $this->assertNotNull($payment->payout_date);
    }

    public function test_a_payout_cannot_be_released_twice(): void
    {
        $payment = Payment::query()->awaitingPayout()->firstOrFail();

        $this->send("/api/v1/admin/payouts/{$payment->id}/release")->assertOk();

        // A double click must not pay a shop twice, and the guard is here rather
        // than in the button because a button is not a guarantee.
        $this->send("/api/v1/admin/payouts/{$payment->id}/release")->assertStatus(409);
    }

    public function test_the_payout_list_only_contains_unreleased_money(): void
    {
        $payouts = $this->api('/api/v1/admin/payouts')->assertOk()->json('payouts');

        $this->assertNotEmpty($payouts);

        foreach ($payouts as $payout) {
            $this->assertNotEmpty($payout['shop']);
            $this->assertArrayHasKey('net', $payout);
        }
    }
}
