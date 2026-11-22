<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Plan;
use App\Models\QuantityDiscount;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use App\Services\JobWorkflow;
use App\Services\PriceCalculator;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rest of the shop console.
 *
 * The dashboard was the only screen the console owned, so every link out of it
 * left for the Filament panel and the shell changed under the click. These are
 * the screens that used to be over there: each one is now reached, rendered and
 * — where it writes — written through.
 */
class ConsoleScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function owner(): User
    {
        return User::where('email', 'kevin@inkwell.ph')->firstOrFail();
    }

    private function shop(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    /** A staff account in this shop: works the queue, must not see the money. */
    private function staff(): User
    {
        $staff = User::firstOrCreate(
            ['email' => 'mika@inkwell.ph'],
            ['name' => 'Mika Santos', 'password' => 'password', 'role' => 'staff'],
        );

        $staff->shops()->syncWithoutDetaching([$this->shop()->id => ['role' => 'staff']]);

        return $staff;
    }

    private function job(string $status = 'in_production'): Job
    {
        return Job::where('shop_id', $this->shop()->id)->where('status', $status)->firstOrFail();
    }

    // ---------------------------------------------------------------- jobs

    public function test_the_jobs_list_shows_this_shops_jobs(): void
    {
        $response = $this->actingAs($this->owner())->get('/console/jobs')->assertOk();

        $this->assertSame(
            $this->shop()->jobs()->count(),
            $response->viewData('jobs')->total(),
        );

        $this->assertStringContainsString($this->job('quoted')->reference, $response->getContent());
    }

    public function test_the_jobs_list_filters_by_status(): void
    {
        $response = $this->actingAs($this->owner())->get('/console/jobs?status=ready')->assertOk();

        $this->assertSame(
            $this->shop()->jobs()->where('status', 'ready')->count(),
            $response->viewData('jobs')->total(),
        );

        foreach ($response->viewData('jobs') as $job) {
            $this->assertSame('ready', $job->status);
        }
    }

    public function test_an_unknown_status_filter_falls_back_to_everything(): void
    {
        // A bookmarked filter should degrade to "all", not to an error page.
        $this->actingAs($this->owner())
            ->get('/console/jobs?status=not-a-status')
            ->assertOk()
            ->assertViewHas('status', '');
    }

    public function test_the_jobs_list_searches_by_reference(): void
    {
        $job = $this->job('quoted');

        $response = $this->actingAs($this->owner())->get('/console/jobs?q='.$job->reference)->assertOk();

        $this->assertSame(1, $response->viewData('jobs')->total());
    }

    public function test_a_job_page_shows_the_job_and_its_history(): void
    {
        $job = $this->job('quoted');

        $response = $this->actingAs($this->owner())
            ->get('/console/jobs/'.$job->reference)
            ->assertOk();

        $this->assertSame($job->id, $response->viewData('job')->id);
        $this->assertStringContainsString($job->user->name, $response->getContent());
    }

    public function test_another_shops_job_is_not_found(): void
    {
        $other = Job::where('shop_id', '!=', $this->shop()->id)->firstOrFail();

        $this->actingAs($this->owner())
            ->get('/console/jobs/'.$other->reference)
            ->assertNotFound();
    }

    public function test_a_legal_status_change_moves_the_job(): void
    {
        $job = $this->job('approved');

        $this->actingAs($this->owner())
            ->post('/console/jobs/'.$job->reference.'/status', ['status' => 'in_production'])
            ->assertRedirect('/console/jobs/'.$job->reference);

        $this->assertSame('in_production', $job->fresh()->status);
    }

    public function test_an_illegal_status_change_is_refused_by_the_workflow(): void
    {
        $job = $this->job('in_production');

        // `collected` is only reachable from `ready`: the board must not let a
        // job skip the shelf.
        $this->actingAs($this->owner())
            ->post('/console/jobs/'.$job->reference.'/status', ['status' => 'collected'])
            ->assertSessionHasErrors('status');

        $this->assertSame('in_production', $job->fresh()->status);
    }

    // --------------------------------------------------------------- board

    public function test_the_board_has_one_column_per_workflow_status(): void
    {
        $response = $this->actingAs($this->owner())->get('/console/production-board')->assertOk();

        $columns = $response->viewData('columns');

        $this->assertSame(JobWorkflow::BOARD, array_column($columns, 'key'));
    }

    public function test_dropping_a_job_on_the_board_moves_it(): void
    {
        $job = $this->job('approved');

        $this->actingAs($this->owner())
            ->post('/console/production-board/'.$job->reference, ['status' => 'in_production'])
            ->assertRedirect();

        $this->assertSame('in_production', $job->fresh()->status);
    }

    public function test_an_illegal_drop_comes_back_as_an_error_not_a_500(): void
    {
        $job = $this->job('ready');

        $this->actingAs($this->owner())
            ->post('/console/production-board/'.$job->reference, ['status' => 'quoted'])
            ->assertSessionHasErrors();

        $this->assertSame('ready', $job->fresh()->status);
    }

    /**
     * The board is a picture of one shop's day. A foreign reference on it would
     * be a job the owner cannot act on and must not see.
     */
    public function test_the_board_only_shows_this_shops_jobs(): void
    {
        $own = Job::where('shop_id', $this->shop()->id)
            ->whereIn('status', JobWorkflow::BOARD)
            ->firstOrFail();

        $other = Job::where('shop_id', '!=', $this->shop()->id)
            ->whereIn('status', JobWorkflow::BOARD)
            ->firstOrFail();

        $this->actingAs($this->owner())
            ->get('/console/production-board')
            ->assertOk()
            ->assertSee($own->reference)
            ->assertDontSee($other->reference);
    }

    // --------------------------------------------------------- price matrix

    public function test_the_price_matrix_preview_is_the_real_quote(): void
    {
        $response = $this->actingAs($this->owner())->get('/console/price-matrix')->assertOk();

        $service = $response->viewData('active');
        $quote = $response->viewData('quote');

        $expected = (new PriceCalculator)->quote($this->shop(), [
            'service_id' => $service->id,
            'width_in' => 36,
            'height_in' => 48,
            'qty' => 3,
            'options' => [],
        ]);

        // Anything that recomputed the price here would drift from checkout.
        $this->assertSame($expected['total'], $quote['total']);
        $this->assertSame($expected['tier'], $quote['tier']);
    }

    public function test_the_price_matrix_honours_a_sample_spec(): void
    {
        $service = $this->shop()->services()->firstOrFail();

        $response = $this->actingAs($this->owner())
            ->get('/console/price-matrix?service='.$service->id.'&width=24&height=24&qty=1')
            ->assertOk();

        $this->assertSame(24, $response->viewData('width'));
        $this->assertSame(1, $response->viewData('qty'));
    }

    /**
     * A spec the engine refuses is a message, not a 500 — the preview is a form
     * somebody is halfway through filling in.
     */
    public function test_the_preview_never_throws_when_the_engine_rejects_the_spec(): void
    {
        $service = $this->shop()->services()->create([
            'name' => 'Tiny stickers',
            'slug' => 'tiny-stickers-'.uniqid(),
            'unit' => 'pc',
            'sort' => 99,
            'active' => true,
        ]);

        $service->tiers()->create([
            'label' => 'Up to 10 in',
            'min_width' => 0,
            'max_width' => 10,
            'unit_price' => 1000,
            'sort' => 0,
        ]);

        $response = $this->actingAs($this->owner())
            ->get('/console/price-matrix?service='.$service->id.'&width=500&height=500&qty=1')
            ->assertOk();

        // The controller keeps the refusal in its own slot rather than inside the
        // quote: a quote is what the customer sees, and a half-built one is not
        // a quote.
        $this->assertNull($response->viewData('quote'));
        $this->assertNotEmpty($response->viewData('error'));
    }

    public function test_the_matrix_only_offers_this_shops_services(): void
    {
        $services = $this->actingAs($this->owner())
            ->get('/console/price-matrix')
            ->assertOk()
            ->viewData('services');

        $this->assertNotEmpty($services);
        $this->assertSame(
            [$this->shop()->id],
            $services->pluck('shop_id')->unique()->values()->all(),
        );
    }

    // ------------------------------------------------------------- reports

    public function test_reports_totals_come_from_payments(): void
    {
        $response = $this->actingAs($this->owner())->get('/console/reports')->assertOk();

        $expected = (int) $this->shop()->payments()->where('payments.status', 'paid')->sum('payments.amount');

        $this->assertSame($expected, $response->viewData('totals')['gross']);
        $this->assertSame(
            $expected
                - (int) $this->shop()->payments()->where('payments.status', 'paid')->sum('payments.commission')
                - (int) $this->shop()->payments()->where('payments.status', 'paid')->sum('payments.processing_fee'),
            $response->viewData('totals')['net'],
        );
    }

    public function test_the_payout_export_streams_a_csv(): void
    {
        $response = $this->actingAs($this->owner())->get('/console/reports/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    // ------------------------------------------------------------- billing

    public function test_billing_lists_the_plans_and_marks_the_current_one(): void
    {
        $response = $this->actingAs($this->owner())->get('/console/billing')->assertOk();

        $plans = $response->viewData('plans');

        $this->assertCount(Plan::count(), $plans);
        $this->assertCount(1, collect($plans)->where('current', true));
    }

    public function test_switching_plan_changes_the_shop(): void
    {
        $target = Plan::where('code', '!=', $this->shop()->plan)->firstOrFail();

        $this->actingAs($this->owner())
            ->post('/console/billing', ['code' => $target->code])
            ->assertRedirect();

        $this->assertSame($target->code, $this->shop()->fresh()->plan);
    }

    public function test_an_unknown_plan_code_is_an_error_not_a_crash(): void
    {
        $this->actingAs($this->owner())
            ->post('/console/billing', ['code' => 'no-such-plan'])
            ->assertSessionHasErrors('code');
    }

    // ------------------------------------------------------------ services

    public function test_services_are_listed_with_their_tier_counts(): void
    {
        $response = $this->actingAs($this->owner())->get('/console/services')->assertOk();

        $this->assertSame($this->shop()->services()->count(), $response->viewData('services')->count());
    }

    public function test_a_service_can_be_created_with_tiers_and_add_ons(): void
    {
        $this->actingAs($this->owner())
            ->post('/console/services', [
                'name' => 'Vinyl banner',
                'slug' => 'vinyl-banner',
                'unit' => 'sqft',
                'blurb' => 'Outdoor, hemmed.',
                'sort' => 5,
                'active' => 1,
                'tiers' => [
                    ['label' => 'Small', 'min_width' => 0, 'max_width' => 48, 'unit_price' => '12.50', 'sort' => 0],
                    ['label' => 'Large', 'min_width' => 48, 'max_width' => '', 'unit_price' => '9.75', 'sort' => 1],
                ],
                'options' => [
                    ['name' => 'Hemming', 'unit' => 'ft', 'price' => '5.00', 'sort' => 0],
                ],
            ])
            ->assertRedirect();

        $service = $this->shop()->services()->where('slug', 'vinyl-banner')->firstOrFail();

        $this->assertSame('Vinyl banner', $service->name);
        $this->assertCount(2, $service->tiers);
        $this->assertCount(1, $service->options);

        // Pesos in the form, centavos in the database.
        $this->assertSame(1250, $service->tiers->firstWhere('label', 'Small')->unit_price);
        $this->assertNull($service->tiers->firstWhere('label', 'Large')->max_width);
    }

    public function test_editing_a_service_replaces_its_tiers(): void
    {
        $service = $this->shop()->services()->with('tiers')->firstOrFail();
        $tier = $service->tiers->first();

        $this->actingAs($this->owner())
            ->put('/console/services/'.$service->id, [
                'name' => $service->name,
                'slug' => $service->slug,
                'unit' => $service->unit,
                'sort' => $service->sort,
                'active' => 1,
                'tiers' => [
                    ['id' => $tier->id, 'label' => 'Renamed', 'min_width' => 0, 'max_width' => 36, 'unit_price' => '20.00', 'sort' => 0],
                ],
                'options' => [],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $service->refresh();

        $this->assertCount(1, $service->tiers);
        $this->assertSame('Renamed', $service->tiers->first()->label);
        $this->assertSame(2000, $service->tiers->first()->unit_price);
    }

    public function test_a_duplicate_slug_in_the_same_shop_is_refused(): void
    {
        $existing = $this->shop()->services()->firstOrFail();

        $this->actingAs($this->owner())
            ->post('/console/services', [
                'name' => 'Copy',
                'slug' => $existing->slug,
                'unit' => 'sqft',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_another_shops_service_cannot_be_edited(): void
    {
        $other = Service::where('shop_id', '!=', $this->shop()->id)->firstOrFail();

        $this->actingAs($this->owner())
            ->get('/console/services/'.$other->id.'/edit')
            ->assertNotFound();
    }

    // ----------------------------------------------------------- discounts

    public function test_discount_bands_are_listed(): void
    {
        $response = $this->actingAs($this->owner())->get('/console/quantity-discounts')->assertOk();

        $this->assertSame(
            $this->shop()->quantityDiscounts()->count(),
            $response->viewData('bands')->count(),
        );
    }

    public function test_a_band_can_be_added_edited_and_removed(): void
    {
        $this->actingAs($this->owner())
            ->post('/console/quantity-discounts', ['min_qty' => 250, 'percent' => 15])
            ->assertRedirect('/console/quantity-discounts');

        $band = $this->shop()->quantityDiscounts()->where('min_qty', 250)->firstOrFail();
        $this->assertSame(15, $band->percent);

        $this->actingAs($this->owner())
            ->put('/console/quantity-discounts/'.$band->id, ['min_qty' => 250, 'percent' => 20])
            ->assertRedirect();

        $this->assertSame(20, $band->fresh()->percent);

        $this->actingAs($this->owner())
            ->delete('/console/quantity-discounts/'.$band->id)
            ->assertRedirect();

        $this->assertNull(QuantityDiscount::find($band->id));
    }

    public function test_another_shops_band_cannot_be_removed(): void
    {
        $other = QuantityDiscount::where('shop_id', '!=', $this->shop()->id)->firstOrFail();

        $this->actingAs($this->owner())
            ->delete('/console/quantity-discounts/'.$other->id)
            ->assertNotFound();

        $this->assertNotNull(QuantityDiscount::find($other->id));
    }

    // --------------------------------------------------------- permissions

    public function test_the_money_screens_refuse_a_shop_staff_account(): void
    {
        $staff = $this->staff();

        foreach (['/console/price-matrix', '/console/services', '/console/billing', '/console/quantity-discounts'] as $url) {
            $this->actingAs($staff)->get($url)->assertForbidden();
        }
    }

    public function test_a_shop_staff_account_can_still_work_the_queue(): void
    {
        $staff = $this->staff();

        foreach (['/console', '/console/jobs', '/console/production-board'] as $url) {
            $this->actingAs($staff)->get($url)->assertOk();
        }
    }

    public function test_a_customer_cannot_reach_any_console_screen(): void
    {
        $customer = User::where('email', 'jomar@tarpalink.ph')->firstOrFail();

        foreach (['/console/jobs', '/console/production-board', '/console/reports'] as $url) {
            $this->actingAs($customer)->get($url)->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_the_login_from_every_screen(): void
    {
        foreach (['/console/jobs', '/console/production-board', '/console/reports', '/console/billing'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    // ------------------------------------------------------ shop switching

    /** Give the owner a second active shop and return it. */
    private function secondShop(): Shop
    {
        $second = Shop::where('slug', 'halftone-hub')->firstOrFail();

        $this->owner()->shops()->syncWithoutDetaching([$second->id => ['role' => 'owner']]);

        return $second;
    }

    public function test_an_owner_of_two_shops_can_switch_between_them(): void
    {
        $second = $this->secondShop();

        $this->actingAs($this->owner())
            ->post('/console/shop', ['shop_id' => $second->id, 'return_to' => '/console/jobs'])
            ->assertRedirect('/console/jobs');

        $this->assertSame($second->id, session('shop_id'));

        // And the next page is that shop's, not the first one's.
        $response = $this->actingAs($this->owner())->get('/console')->assertOk();
        $this->assertSame($second->name, $response->viewData('shell')['brand']);
    }

    public function test_the_switcher_only_appears_when_there_is_a_choice(): void
    {
        $single = $this->actingAs($this->owner())->get('/console')->assertOk();
        $this->assertCount(1, $single->viewData('shell')['shops']);

        $this->secondShop();

        $many = $this->actingAs($this->owner())->get('/console')->assertOk();
        $this->assertCount(2, $many->viewData('shell')['shops']);
    }

    public function test_a_shop_that_is_not_yours_cannot_be_switched_to(): void
    {
        // A posted shop id is user input like any other; the session is a
        // preference, never a permission.
        $foreign = Shop::whereNotIn('id', $this->owner()->shops()->pluck('shops.id'))->firstOrFail();

        $this->actingAs($this->owner())
            ->post('/console/shop', ['shop_id' => $foreign->id])
            ->assertSessionHasErrors('shop_id');

        $this->assertNotSame($foreign->id, session('shop_id'));
    }

    public function test_a_suspended_shop_cannot_be_switched_to(): void
    {
        $second = $this->secondShop();
        $second->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($this->owner())
            ->post('/console/shop', ['shop_id' => $second->id])
            ->assertSessionHasErrors('shop_id');
    }

    public function test_the_return_path_cannot_be_used_as_an_open_redirect(): void
    {
        $second = $this->secondShop();

        foreach (['https://evil.example/x', '//evil.example/x', '/somewhere-else'] as $to) {
            $this->actingAs($this->owner())
                ->post('/console/shop', ['shop_id' => $second->id, 'return_to' => $to])
                ->assertRedirect(route('console.dashboard'));
        }
    }

    /**
     * The brand changing is not the same thing as the data changing. Every list
     * in the console is scoped to `currentShop()`, so switching has to move the
     * rows too — otherwise the header says one shop and the table shows another.
     */
    public function test_switching_changes_which_jobs_the_list_shows(): void
    {
        $second = $this->secondShop();

        $theirs = Job::where('shop_id', $second->id)->firstOrFail();
        $ours = Job::where('shop_id', $this->shop()->id)->firstOrFail();

        $this->actingAs($this->owner())
            ->get('/console/jobs')
            ->assertOk()
            ->assertSee($ours->reference)
            ->assertDontSee($theirs->reference);

        $this->actingAs($this->owner())
            ->post('/console/shop', ['shop_id' => $second->id])
            ->assertRedirect();

        $this->actingAs($this->owner())
            ->get('/console/jobs')
            ->assertOk()
            ->assertSee($theirs->reference)
            ->assertDontSee($ours->reference);
    }

    /**
     * A session pointing at a suspended shop must fall back rather than lock the
     * owner out — the shop is refused at the door, so without a fallback a shop
     * that stopped trading would take the whole console with it.
     */
    public function test_a_session_pointing_at_a_suspended_shop_falls_back_to_an_active_one(): void
    {
        $second = $this->secondShop();

        $this->shop()->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($this->owner())
            ->withSession(['shop_id' => $this->shop()->id])
            ->get('/console')
            ->assertOk();

        $this->assertSame($second->id, $this->owner()->fresh()->currentShop()?->id);
    }
}
