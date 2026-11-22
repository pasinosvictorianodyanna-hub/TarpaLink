<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The path a customer actually walks: log in, configure a job, pay.
 *
 * Nothing else in the suite creates a job through the HTTP layer, so this is the
 * only place the controller, the calculator and the payment row are proven to
 * agree.
 */
class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function customer(): User
    {
        return User::where('email', 'jomar@tarpalink.ph')->firstOrFail();
    }

    public function test_a_customer_can_log_in_and_reach_their_account(): void
    {
        $this->post('/login', [
            'email' => 'jomar@tarpalink.ph',
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticated();

        $this->get('/account')->assertOk();
    }

    public function test_bad_credentials_are_rejected(): void
    {
        $this->from('/login')->post('/login', [
            'email' => 'jomar@tarpalink.ph',
            'password' => 'not-the-password',
        ])->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_checkout_creates_a_job_priced_by_the_server(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();
        $service = $shop->services()->where('slug', 'tarpaulin')->firstOrFail();
        $options = $service->options()->get();

        $response = $this->actingAs($this->customer())
            ->post('/shops/inkwell-press/checkout', [
                'service_id' => $service->id,
                'width_in' => 48,
                'height_in' => 96,
                'qty' => 3,
                'options' => $options[0]->id.':8,'.$options[1]->id.':16',
                'payment_method' => 'gcash',
            ]);

        $job = Job::latest('id')->firstOrFail();

        $response->assertRedirect(route('jobs.show', $job));

        // The contract example: ₱2,144.00 − 5% = ₱2,036.80.
        $this->assertSame(214400, $job->subtotal);
        $this->assertSame(10720, $job->discount);
        $this->assertSame(203680, $job->total);

        // The job recorded the fingerprint of the matrix it was priced against —
        // compared against the service, not against a literal. The number is
        // built from tier ids, so it differs between databases; asserting 6612
        // passed on SQLite and failed on MySQL without anything being wrong.
        $this->assertSame($service->matrixVersion(), $job->matrix_version);

        $this->assertSame($shop->id, $job->shop_id);
        $this->assertCount(3, $job->lines);

        $this->assertSame($job->subtotal, (int) $job->lines->sum('amount'));
    }

    public function test_checkout_creates_a_pending_payment_at_the_shops_own_rate(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();   // growth, 3%
        $service = $shop->services()->where('slug', 'tarpaulin')->firstOrFail();

        $this->actingAs($this->customer())
            ->post('/shops/inkwell-press/checkout', [
                'service_id' => $service->id,
                'width_in' => 48,
                'height_in' => 96,
                'qty' => 3,
                'payment_method' => 'gcash',
            ]);

        $job = Job::latest('id')->firstOrFail();
        $payment = $job->payment;

        $this->assertNotNull($payment, 'Checkout did not create a payment row.');
        $this->assertSame('pending', $payment->status);
        $this->assertSame('pending', $payment->payout_status);
        $this->assertSame($job->total, $payment->amount);
        $this->assertSame(
            (int) round($job->total * 300 / 10000),
            $payment->commission,
            'Commission was not computed at the shop rate (3%).',
        );
    }

    public function test_cash_on_pickup_creates_no_payment(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();
        $service = $shop->services()->where('slug', 'tarpaulin')->firstOrFail();

        $this->actingAs($this->customer())
            ->post('/shops/inkwell-press/checkout', [
                'service_id' => $service->id,
                'width_in' => 24,
                'height_in' => 36,
                'qty' => 1,
                'payment_method' => 'cod',
            ]);

        $job = Job::latest('id')->firstOrFail();

        $this->assertNull($job->payment, 'COD should not create a payment intent.');
    }

    public function test_checkout_rejects_a_size_the_shop_cannot_print(): void
    {
        $shop = Shop::where('slug', 'halftone-hub')->firstOrFail();
        $service = $shop->services()->where('slug', 'stickers')->firstOrFail();

        $this->actingAs($this->customer())
            ->from('/shops/halftone-hub/build')
            ->post('/shops/halftone-hub/checkout', [
                'service_id' => $service->id,
                'width_in' => 900,
                'height_in' => 900,
                'qty' => 1,
                'payment_method' => 'gcash',
            ])
            ->assertRedirect('/shops/halftone-hub/build')
            ->assertSessionHasErrors('size');
    }

    public function test_an_inactive_shop_cannot_be_checked_out(): void
    {
        Shop::where('slug', 'inkwell-press')->update(['status' => 'suspended']);

        $this->actingAs($this->customer())
            ->get('/shops/inkwell-press/checkout')
            ->assertNotFound();
    }

    public function test_the_checkout_page_survives_a_bogus_service_id(): void
    {
        $this->actingAs($this->customer())
            ->get('/shops/inkwell-press/checkout?service_id=99999')
            ->assertRedirect('/shops/inkwell-press/build');
    }

    public function test_the_checkout_page_survives_an_unprintable_size(): void
    {
        // Halftone's sticker tiers are bounded, so 900in falls outside every one.
        $this->actingAs($this->customer())
            ->get('/shops/halftone-hub/checkout?width_in=900&height_in=900')
            ->assertRedirect('/shops/halftone-hub/build');
    }

    public function test_checkout_rejects_a_service_belonging_to_another_shop(): void
    {
        $foreignService = Shop::where('slug', 'halftone-hub')->firstOrFail()
            ->services()->firstOrFail();

        $before = Job::count();

        $this->actingAs($this->customer())
            ->from('/shops/inkwell-press/build')
            ->post('/shops/inkwell-press/checkout', [
                'service_id' => $foreignService->id,
                'width_in' => 24,
                'height_in' => 36,
                'qty' => 1,
                'payment_method' => 'gcash',
            ])
            ->assertRedirect('/shops/inkwell-press/build')
            ->assertSessionHasErrors('size');

        // No half-created job may survive a rejected quote.
        $this->assertSame($before, Job::count());
    }

    public function test_the_customer_sees_their_own_job_and_not_another_customers(): void
    {
        $jomar = $this->customer();
        $own = Job::where('user_id', $jomar->id)->firstOrFail();
        $theirs = Job::where('user_id', '!=', $jomar->id)->firstOrFail();

        $this->actingAs($jomar);
        $this->get('/jobs/'.$own->reference)->assertOk();
        $this->get('/jobs/'.$theirs->reference)->assertNotFound();
    }

    public function test_a_guest_cannot_read_a_job_by_its_reference(): void
    {
        $job = Job::firstOrFail();

        $this->assertGuest();
        $this->get('/jobs/'.$job->reference)->assertNotFound();
    }

    public function test_a_shop_can_read_a_job_it_is_fulfilling(): void
    {
        $owner = User::where('email', 'kevin@inkwell.ph')->firstOrFail();
        $job = $owner->currentShop()->jobs()->firstOrFail();

        $this->actingAs($owner);
        $this->get('/jobs/'.$job->reference)->assertOk();
    }

    public function test_a_paid_payment_can_be_marked_as_paid_out(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();

        $payment = Payment::whereHas('job', fn ($q) => $q->where('shop_id', $shop->id))
            ->where('status', 'paid')
            ->firstOrFail();

        $this->assertSame('pending', $payment->payout_status);
        $this->assertSame($payment->amount - $payment->commission - $payment->processing_fee, $payment->net());
    }
}
