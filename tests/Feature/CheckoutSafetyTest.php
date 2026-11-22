<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The two ways checkout used to lose the customer's trust:
 *
 *   - a failed payment intent left a job and a payment behind, then a 500;
 *   - a double-click created two jobs and charged twice.
 *
 * Both are about writes that must be all-or-nothing, so they are tested against
 * the database rather than against the redirect.
 */
class CheckoutSafetyTest extends TestCase
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

    /** @return array<string, mixed> */
    private function order(): array
    {
        $service = Shop::where('slug', 'inkwell-press')->firstOrFail()
            ->services()->where('slug', 'tarpaulin')->firstOrFail();

        return [
            'service_id' => $service->id,
            'width_in' => 48,
            'height_in' => 96,
            'qty' => 2,
            'payment_method' => 'gcash',
        ];
    }

    public function test_a_failed_payment_intent_leaves_no_job_and_no_payment(): void
    {
        config()->set('services.paymongo.secret', 'sk_test_not_a_real_key');
        Http::fake(['*' => Http::response(['error' => 'processor down'], 503)]);

        $before = Job::count();
        $payments = Payment::count();

        $response = $this->actingAs($this->customer())
            ->from('/shops/inkwell-press/checkout')
            ->post('/shops/inkwell-press/checkout', $this->order());

        $response
            ->assertRedirect('/shops/inkwell-press/checkout')
            ->assertSessionHasErrors('checkout');

        $this->assertSame($before, Job::count(), 'A failed intent must not leave a job behind');
        $this->assertSame($payments, Payment::count(), 'A failed intent must not leave a payment behind');
    }

    public function test_the_checkout_error_is_actually_rendered(): void
    {
        config()->set('services.paymongo.secret', 'sk_test_not_a_real_key');
        Http::fake(['*' => Http::response(['error' => 'processor down'], 503)]);

        // Follow the redirect: an error flashed to a bag the page never renders
        // looks to the customer like the button did nothing.
        $response = $this->actingAs($this->customer())
            ->post('/shops/inkwell-press/checkout', $this->order());

        $page = $this->followingRedirects()
            ->get('/shops/inkwell-press/checkout')
            ->assertOk();

        $page->assertSee('Nothing was charged');
        $this->assertNotNull($response);
    }

    public function test_a_double_submission_creates_only_one_job(): void
    {
        $before = Job::count();
        $paymentBefore = Payment::count();
        $me = $this->actingAs($this->customer());
        $order = $this->order();

        $me->post('/shops/inkwell-press/checkout', $order)->assertRedirect();
        $me->from('/shops/inkwell-press/checkout')
            ->post('/shops/inkwell-press/checkout', $order)
            ->assertSessionHasErrors('checkout');

        $this->assertSame($before + 1, Job::count(), 'A double-click created two jobs');
        $this->assertSame($paymentBefore + 1, Payment::count(), 'A double-click created two payments');
    }

    public function test_a_different_order_right_afterwards_is_still_allowed(): void
    {
        $before = Job::count();
        $me = $this->actingAs($this->customer());

        $me->post('/shops/inkwell-press/checkout', $this->order())->assertRedirect();

        $other = $this->order();
        $other['qty'] = 5;
        $other['payment_method'] = 'cod';

        $me->post('/shops/inkwell-press/checkout', $other)->assertRedirect();

        $this->assertSame($before + 2, Job::count(), 'The idempotency key must be per-order, not per-customer');
    }
}
