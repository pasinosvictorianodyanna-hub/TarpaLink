<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Services\Billing as BillingService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceptance criteria for issue #7.
 *
 * The point of these tests is that the ledger is arithmetic, not a rendering
 * choice: gross minus commission must equal net on every row, and the month
 * total must equal what a plain SUM() says.
 */
class ReportsTest extends TestCase
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

    private function inkwell(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    public function test_the_reports_page_renders_the_ledger(): void
    {
        $this->actingAs($this->owner())
            ->get('/console/reports')
            ->assertSuccessful()
            ->assertSee('Payout ledger')
            ->assertSee('Top services by revenue')
            ->assertSee('Revenue, last 8 weeks');
    }

    public function test_every_ledger_row_balances(): void
    {
        $rows = $this->actingAs($this->owner())
            ->get('/console/reports')
            ->assertOk()
            ->viewData('ledger');

        $this->assertNotEmpty($rows);

        foreach ($rows as $payment) {
            $this->assertSame(
                $payment->amount - $payment->commission - $payment->processing_fee,
                $payment->net(),
                "Row {$payment->id} does not balance"
            );
        }
    }

    /**
     * The decision behind #81: the shop bears the processor's fee, so the net is
     * gross − commission − fee. A real ₱217.23 job earns ₱10.86 of commission
     * against ₱20.43 of fee — the shop is paid the difference, not the gross
     * minus commission.
     */
    public function test_the_processor_fee_comes_out_of_the_payout(): void
    {
        $payment = Payment::create([
            'job_id' => $this->inkwell()->jobs()->firstOrFail()->id,
            'provider' => 'paymongo',
            'intent_id' => 'pi_fee_arithmetic',
            'amount' => 21723,
            'commission' => 1086,
            'processing_fee' => 2043,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->assertSame(18594, $payment->net());
        $this->assertSame('₱185.94', Money::format($payment->net()));
    }

    /** A margin that has gone negative is a state, not a typo. */
    public function test_a_negative_figure_puts_the_sign_before_the_symbol(): void
    {
        $this->assertSame('−₱9.57', Money::signed(-957));
        $this->assertSame('₱9.57', Money::signed(957));
    }

    public function test_the_totals_agree_with_a_plain_sum_over_payments(): void
    {
        $shop = $this->inkwell();

        $response = $this->actingAs($this->owner())->get('/console/reports')->assertOk();
        $totals = $response->viewData('totals');

        // `moved()`, not `status = 'paid'`: the ledger keeps a refunded payment
        // because its net is negative, and a total that quietly dropped it would
        // disagree with the rows on the page.
        $mine = fn () => Payment::whereIn('job_id', $shop->jobs()->select('id'))->moved();

        $expectedGross = (int) $mine()->sum('amount');
        $expectedRefunded = (int) $mine()->sum('refunded_amount');
        $expectedCommission = (int) $mine()->sum('commission');
        $expectedCommissionRefunded = (int) $mine()->sum('commission_refunded');
        $expectedFees = (int) $mine()->sum('processing_fee');

        $this->assertSame($expectedGross, $totals['gross']);
        $this->assertSame($expectedRefunded, $totals['refunded']);
        $this->assertSame($expectedCommission, $totals['commission']);
        $this->assertSame($expectedCommissionRefunded, $totals['commission_refunded']);
        $this->assertSame($expectedFees, $totals['fees']);

        // The processor's fee is deducted before the shop is paid, so the net is
        // gross − refunds − commission + reversed commission − fee. Leaving any
        // of those out overstates what the shop gets.
        $this->assertSame(
            $expectedGross - $expectedRefunded - $expectedCommission + $expectedCommissionRefunded - $expectedFees,
            $totals['net'],
        );

        // Revenue, unlike the ledger, excludes refunded money: a job that was
        // given back was never revenue.
        $monthSum = (int) Payment::whereIn('job_id', $shop->jobs()->select('id'))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $this->assertSame($monthSum, $response->viewData('month'));
    }

    public function test_a_plan_change_does_not_rewrite_past_payouts(): void
    {
        $shop = $this->inkwell();
        $before = $shop->payments()->pluck('payments.commission', 'payments.id')->all();

        app(BillingService::class)->subscribe($shop, Plan::where('code', 'pro')->firstOrFail());

        $after = $shop->fresh()->payments()->pluck('payments.commission', 'payments.id')->all();

        $this->assertSame($before, $after, 'Commission snapshots were rewritten by a plan change');
        $this->assertSame(150, $shop->fresh()->commission_rate);
    }

    public function test_the_ledger_only_shows_this_shops_payments(): void
    {
        $ledger = $this->actingAs($this->owner())
            ->get('/console/reports')
            ->assertOk()
            ->viewData('ledger');

        $ownJobIds = $this->inkwell()->jobs()->pluck('id')->all();

        foreach ($ledger as $payment) {
            $this->assertContains($payment->job_id, $ownJobIds);
        }

        $otherShopPayment = Payment::whereNotIn('job_id', $ownJobIds)->firstOrFail();

        $this->assertNotContains($otherShopPayment->id, $ledger->pluck('id')->all());
    }

    public function test_payouts_export_as_csv(): void
    {
        $response = $this->actingAs($this->owner())->get('/console/reports/export');

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();

        $this->assertStringContainsString('Gross,Refunded,Commission,Fee,Net', $body);
        $this->assertStringContainsString('TL-', $body);
    }

    public function test_a_customer_cannot_export_the_ledger(): void
    {
        $customer = User::where('email', 'jomar@tarpalink.ph')->firstOrFail();

        $this->actingAs($customer)
            ->get('/console/reports/export')
            ->assertForbidden();
    }
}
