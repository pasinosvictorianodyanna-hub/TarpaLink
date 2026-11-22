<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\PageSection;
use App\Models\QuantityDiscount;
use App\Models\Shop;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Walks every page of the application as the role allowed to see it.
 *
 * The assertion is deliberately loose on status (anything under 500) and strict
 * on collecting failures — a smoke test that stops at the first broken page
 * hides the other nine.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /**
     * Switch identity the way a real browser would.
     *
     * The panels run Laravel's AuthenticateSession, which logs the user out when
     * the session's stored password hash no longer matches. `actingAs()` alone
     * leaves the previous user's hash behind, so one session reused across roles
     * 302s to login on the second switch — a harness artefact, not an app bug.
     */
    private function actAs(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->actingAs($user);
    }

    /** @param  array<int, string>  $urls */
    private function assertNoServerErrors(array $urls): void
    {
        $failures = [];

        foreach ($urls as $url) {
            $response = $this->get($url);

            if ($response->status() >= 500) {
                $failures[] = sprintf('%s -> %d', $url, $response->status());
            }
        }

        $this->assertSame([], $failures, "Server errors:\n".implode("\n", $failures));
    }

    public function test_public_pages_render(): void
    {
        $this->assertNoServerErrors([
            '/',
            '/shops',
            '/shops/inkwell-press',
            '/shops/inkwell-press/build',
            '/shops/inkwell-press/checkout',
            '/login',
            '/open-a-shop',
            '/admin/login',
            '/login',
        ]);
    }

    public function test_customer_pages_render(): void
    {
        $customer = $this->user('jomar@tarpalink.ph');
        $job = Job::where('user_id', $customer->id)->firstOrFail();

        $this->actAs($customer);

        $this->assertNoServerErrors([
            '/account',
            '/jobs/'.$job->reference,
        ]);
    }

    public function test_console_pages_render(): void
    {
        $owner = $this->user('kevin@inkwell.ph');
        $shop = $owner->currentShop();

        $job = $shop->jobs()->firstOrFail();
        $service = $shop->services()->firstOrFail();
        $discount = QuantityDiscount::where('shop_id', $shop->id)->firstOrFail();

        $this->actAs($owner);

        $this->assertNoServerErrors([
            '/console',
            '/console/reports',
            '/console/billing',
            '/console/production-board',
            '/console/jobs',
            '/console/jobs/'.$job->getRouteKey(),
            '/console/services',
            '/console/services/create',
            '/console/services/'.$service->getRouteKey().'/edit',
            '/console/quantity-discounts',
            '/console/quantity-discounts/create',
            '/console/quantity-discounts/'.$discount->getRouteKey().'/edit',
        ]);
    }


    /**
     * The shop panel is retired, and this is what keeps it retired.
     *
     * Deleting the files is not enough: a `ShopPanelProvider` left in
     * `bootstrap/providers.php`, or a resource re-registered by hand, would put
     * seventeen routes back on the shop prefix and quietly rebuild the second
     * shell that #66 spent a whole pass removing.
     */
    public function test_the_filament_shop_panel_is_gone(): void
    {
        $left = collect(Route::getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => $uri === 'shop' || str_starts_with($uri, 'shop/'))
            ->values()
            ->all();

        $this->assertSame([], $left, 'The shop panel is back: '.implode(', ', $left));
    }

    /**
     * And nothing may point at it either.
     *
     * A 404 on `/shop` is only half the promise — a console page that still
     * links there would send the owner to a dead end, which is worse than the
     * second shell was.
     */
    public function test_no_console_page_links_into_the_old_panel(): void
    {
        $this->actAs($this->user('kevin@inkwell.ph'));

        $shop = $this->user('kevin@inkwell.ph')->currentShop();
        $job = $shop->jobs()->firstOrFail();

        $pages = [
            '/console',
            '/console/jobs',
            '/console/jobs/'.$job->getRouteKey(),
            '/console/production-board',
            '/console/price-matrix',
            '/console/reports',
            '/console/billing',
            '/console/services',
            '/console/quantity-discounts',
        ];

        foreach ($pages as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            foreach (['href="/shop', 'action="/shop'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $html,
                    "{$page} still links into the retired panel.",
                );
            }
        }
    }

    public function test_admin_panel_pages_render(): void
    {
        $admin = $this->user('sofia@tarpalink.ph');
        $shop = Shop::firstOrFail();
        $user = User::where('role', 'customer')->firstOrFail();
        $section = PageSection::firstOrFail();
        $setting = SiteSetting::firstOrFail();

        $this->actAs($admin);

        $this->assertNoServerErrors([
            '/admin',
            '/admin/shops',
            '/admin/shops/'.$shop->getRouteKey(),
            '/admin/shops/'.$shop->getRouteKey().'/edit',
            '/admin/users',
            '/admin/users/create',
            '/admin/users/'.$user->getRouteKey().'/edit',
            '/admin/site-content',
            '/admin/site-content/'.$section->getRouteKey().'/edit',
            '/admin/site-settings',
            '/admin/shop-requests',
            '/admin/site-settings/create',
            '/admin/site-settings/'.$setting->getRouteKey().'/edit',
        ]);
    }

    public function test_each_role_is_kept_out_of_the_other_panels(): void
    {
        $customer = $this->user('jomar@tarpalink.ph');
        $owner = $this->user('kevin@inkwell.ph');
        $admin = $this->user('sofia@tarpalink.ph');

        $this->actAs($customer);
        $this->get('/console')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->get('/account')->assertOk();

        $this->actAs($owner);
        $this->get('/admin')->assertForbidden();
        $this->get('/console')->assertOk();

        $this->actAs($admin);
        $this->get('/console')->assertForbidden();
        $this->get('/admin')->assertOk();
    }

    public function test_unknown_records_return_404_not_500(): void
    {
        $this->actAs($this->user('sofia@tarpalink.ph'));

        $this->get('/admin/shops/999999/edit')->assertNotFound();

        $this->actAs($this->user('kevin@inkwell.ph'));
        // The console's service screen is the edit form; there is no separate
        // read-only view, so that is the route an unknown id has to 404 on.
        $this->get('/console/services/999999/edit')->assertNotFound();
    }

    public function test_a_shop_owner_cannot_open_another_shops_service(): void
    {
        $owner = $this->user('kevin@inkwell.ph');
        $foreign = Shop::where('slug', 'halftone-hub')->firstOrFail()->services()->firstOrFail();

        $this->actAs($owner);

        // 404 rather than 403: the record is out of scope, so it does not exist
        // for this shop. Hiding it is the point.
        $this->get('/console/services/'.$foreign->getRouteKey().'/edit')->assertNotFound();
    }

    public function test_the_quote_endpoint_rejects_garbage_without_a_500(): void
    {
        $this->postJson('/api/quote', [
            'shop' => 'inkwell-press',
            'service_id' => 1,
            'width_in' => 0,
            'height_in' => 'abc',
            'qty' => -5,
        ])->assertStatus(422);
    }

    public function test_a_size_outside_every_tier_returns_a_validation_error(): void
    {
        $shop = Shop::where('slug', 'halftone-hub')->firstOrFail();
        $service = $shop->services()->where('slug', 'stickers')->firstOrFail();

        $this->postJson('/api/quote', [
            'shop' => 'halftone-hub',
            'service_id' => $service->id,
            'width_in' => 500,
            'height_in' => 500,
            'qty' => 1,
        ])->assertStatus(422);
    }

    public function test_the_quote_endpoint_refuses_another_shops_service(): void
    {
        $foreign = Shop::where('slug', 'halftone-hub')->firstOrFail()->services()->firstOrFail();

        $this->postJson('/api/quote', [
            'shop' => 'inkwell-press',
            'service_id' => $foreign->id,
            'width_in' => 24,
            'height_in' => 36,
            'qty' => 1,
        ])->assertStatus(422);
    }

    public function test_the_quote_endpoint_refuses_an_inactive_shop(): void
    {
        Shop::where('slug', 'inkwell-press')->update(['status' => 'suspended']);

        $this->postJson('/api/quote', [
            'shop' => 'inkwell-press',
            'service_id' => 1,
            'width_in' => 24,
            'height_in' => 36,
            'qty' => 1,
        ])->assertStatus(404);
    }
}
