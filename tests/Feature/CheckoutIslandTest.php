<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use App\Services\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Checkout as a JSON island.
 *
 * The Blade form was not wrong, it was mute: a rejected field reloaded the page
 * and threw away the other two panels, and a slow payment intent looked like a
 * button that did nothing. This covers the contract that replaces it — a 201
 * with somewhere to go, a 422 that names the field, and a 409 when the price
 * moved.
 *
 * The interesting tests are the last two. The island sends a total back up, and
 * the temptation is to trust it. It is never trusted: the server re-prices the
 * order from the shop's matrix every time, and the client's number is only used
 * to *refuse* a charge the customer did not agree to.
 */
class CheckoutIslandTest extends TestCase
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

    private function shop(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function order(array $overrides = []): array
    {
        $service = $this->shop()->services()->where('slug', 'tarpaulin')->firstOrFail();

        return array_merge([
            'service_id' => $service->id,
            'width_in' => 48,
            'height_in' => 96,
            'qty' => 2,
            'payment_method' => 'gcash',
        ], $overrides);
    }

    /** What the server itself would quote for the fixture order, in centavos. */
    private function serverTotal(array $order): int
    {
        $quote = app(PriceCalculator::class)->quote($this->shop(), [
            'service_id' => (int) $order['service_id'],
            'width_in' => (float) $order['width_in'],
            'height_in' => (float) $order['height_in'],
            'qty' => (int) $order['qty'],
            'options' => [],
        ]);

        return $quote['total'];
    }

    // ------------------------------------------------------------ the page

    public function test_the_page_renders_the_island_with_server_priced_props(): void
    {
        $response = $this->actingAs($this->customer())
            ->get('/shops/inkwell-press/checkout?service_id='.$this->order()['service_id']);

        $response->assertOk()->assertSee('data-component="checkout-form"', false);

        // The total the island shows comes from the server, so it is in the
        // props before any JavaScript runs.
        $quote = app(PriceCalculator::class)->quote($this->shop(), [
            'service_id' => $this->order()['service_id'],
            'width_in' => 36.0,
            'height_in' => 48.0,
            'qty' => 1,
            'options' => [],
        ]);

        $response->assertSee('&quot;total&quot;:'.$quote['total'], false);
    }

    public function test_a_guest_sees_the_island_with_a_sign_in_instead_of_a_pay_button(): void
    {
        $response = $this->get('/shops/inkwell-press/checkout');

        $response->assertOk()
            ->assertSee('data-component="checkout-form"', false)
            ->assertSee('&quot;authenticated&quot;:false', false);
    }

    // ----------------------------------------------------------- the order

    public function test_a_json_checkout_returns_the_job_and_somewhere_to_go(): void
    {
        $response = $this->actingAs($this->customer())
            ->postJson('/shops/inkwell-press/checkout', $this->order());

        $response->assertStatus(201)->assertJsonStructure(['job' => ['reference', 'total'], 'redirect']);

        $job = Job::where('reference', $response->json('job.reference'))->firstOrFail();

        $this->assertSame(route('jobs.show', $job), $response->json('redirect'));
        $this->assertSame($this->customer()->id, $job->user_id);
    }

    public function test_a_json_validation_error_names_the_field(): void
    {
        $response = $this->actingAs($this->customer())
            ->postJson('/shops/inkwell-press/checkout', $this->order([
                'contact_email' => 'not-an-email',
            ]));

        // A redirect to a fetch() call is a 302 whose body the client cannot
        // read — which is exactly the "the button does nothing" bug.
        $response->assertStatus(422)->assertJsonValidationErrors('contact_email');

        $this->assertSame(0, Job::where('user_id', $this->customer()->id)
            ->where('created_at', '>=', now()->subMinute())->count());
    }

    public function test_an_unprintable_size_comes_back_as_a_field_error(): void
    {
        // Halftone's sticker matrix stops well below this, so the calculator
        // raises rather than picking a tier and hoping.
        $service = Shop::where('slug', 'halftone-hub')->firstOrFail()
            ->services()->where('slug', 'stickers')->firstOrFail();

        $response = $this->actingAs($this->customer())
            ->postJson('/shops/halftone-hub/checkout', [
                'service_id' => $service->id,
                'width_in' => 900,
                'height_in' => 900,
                'qty' => 1,
                'payment_method' => 'gcash',
            ]);

        $response->assertStatus(422)->assertJsonPath('errors.size.0', fn (string $message) => $message !== '');
    }

    public function test_a_failed_payment_intent_returns_json_not_a_redirect(): void
    {
        config()->set('services.paymongo.secret', 'sk_test_not_a_real_key');
        Http::fake(['*' => Http::response(['error' => 'processor down'], 503)]);

        $jobs = Job::count();
        $payments = Payment::count();

        $response = $this->actingAs($this->customer())
            ->postJson('/shops/inkwell-press/checkout', $this->order());

        $response->assertStatus(500)->assertJsonPath('errors.checkout.0', fn (string $m) => str_contains($m, 'Nothing was charged'));

        // The transaction rolls back, so a failed intent leaves nothing behind —
        // and the JSON client is told, rather than being redirected.
        $this->assertSame($jobs, Job::count());
        $this->assertSame($payments, Payment::count());
    }

    public function test_a_double_submit_returns_a_conflict_not_a_second_job(): void
    {
        $order = $this->order();

        $this->actingAs($this->customer())
            ->postJson('/shops/inkwell-press/checkout', $order)
            ->assertStatus(201);

        $this->actingAs($this->customer())
            ->postJson('/shops/inkwell-press/checkout', $order)
            ->assertStatus(409)
            ->assertJsonPath('errors.checkout.0', fn (string $m) => str_contains($m, 'already being placed'));
    }

    // ----------------------------------------------------------- the price

    public function test_the_total_the_customer_saw_is_the_total_that_is_charged(): void
    {
        $order = $this->order();
        $total = $this->serverTotal($order);

        $response = $this->actingAs($this->customer())
            ->postJson('/shops/inkwell-press/checkout', $order + ['expected_total' => $total]);

        $response->assertStatus(201);

        $this->assertSame($total, Job::where('reference', $response->json('job.reference'))->value('total'));
    }

    public function test_a_price_that_moved_while_the_page_was_open_is_refused(): void
    {
        $order = $this->order();
        $total = $this->serverTotal($order);

        $before = Job::count();

        $response = $this->actingAs($this->customer())
            ->postJson('/shops/inkwell-press/checkout', $order + ['expected_total' => $total - 1]);

        // Charging the new number silently is how somebody ends up paying an
        // amount they never agreed to.
        $response->assertStatus(409)
            ->assertJsonPath('total', $total)
            ->assertJsonPath('errors.total.0', fn (string $m) => str_contains($m, 'price changed'));

        $this->assertSame($before, Job::count());
    }

    public function test_the_client_cannot_name_its_own_price(): void
    {
        $order = $this->order();

        // A tampered client sends a total of one peso. It is not read as a
        // price; at most it fails the `expected_total` check.
        $response = $this->actingAs($this->customer())
            ->postJson('/shops/inkwell-press/checkout', $order + [
                'total' => 100,
                'subtotal' => 100,
                'discount' => 0,
            ]);

        $response->assertStatus(201);

        $job = Job::where('reference', $response->json('job.reference'))->firstOrFail();

        $this->assertSame($this->serverTotal($order), $job->total);
        $this->assertNotSame(100, $job->total);
    }

    public function test_the_island_and_the_fallback_agree_on_the_price(): void
    {
        $order = $this->order();

        // The Blade form posts without `expected_total`. It must still be priced
        // by the server and still redirect — the island is an enhancement, not
        // a replacement.
        $this->actingAs($this->customer())
            ->post('/shops/inkwell-press/checkout', $order)
            ->assertRedirect();

        $job = Job::where('user_id', $this->customer()->id)->latest('id')->firstOrFail();

        $this->assertSame($this->serverTotal($order), $job->total);
    }
}
