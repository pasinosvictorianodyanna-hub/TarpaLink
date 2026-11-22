<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing as BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Acceptance criteria for issue #83.
 *
 * `subscribe()` issued one invoice when a shop changed plan, and then nothing
 * ever issued another and nothing ever collected the first. So the product had
 * subscription revenue on paper and none in the bank, and an "open" invoice from
 * two years ago was still sitting there looking collectable.
 *
 * The property that matters most is idempotency: the command runs every day, so
 * a second run in the same period must issue nothing.
 */
class BillingInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function inkwell(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    private function growth(): Plan
    {
        return Plan::where('code', 'growth')->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('email', 'sofia@tarpalink.ph')->firstOrFail();
    }

    /** A shop on a paid plan, with a subscription that started on a known day. */
    private function subscribed(Shop $shop, Plan $plan, Carbon $startedAt): Subscription
    {
        return Subscription::create([
            'shop_id' => $shop->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => $startedAt,
        ]);
    }

    public function test_an_active_paid_subscription_is_invoiced_once_per_period(): void
    {
        $shop = $this->inkwell();
        $shop->subscriptions()->update(['status' => 'cancelled', 'ends_at' => now()]);
        Invoice::query()->delete();

        $this->subscribed($shop, $this->growth(), now()->subDays(3)->startOfDay());

        $service = app(BillingService::class);

        $first = $service->issueDueInvoices();
        $this->assertCount(1, $first);
        $this->assertSame(59_900, $first[0]->amount);
        $this->assertSame(Invoice::OPEN, $first[0]->status);

        // The same day, and the next day, must issue nothing: the period is
        // already billed. This is what makes a daily schedule safe.
        $this->assertCount(0, $service->issueDueInvoices());
        $this->assertCount(0, $service->issueDueInvoices(now()->addDay()));
    }

    public function test_a_new_period_is_invoiced_when_the_month_rolls_over(): void
    {
        $shop = $this->inkwell();
        $shop->subscriptions()->update(['status' => 'cancelled', 'ends_at' => now()]);
        Invoice::query()->delete();

        $started = Carbon::parse('2026-11-10 09:00:00');
        $this->subscribed($shop, $this->growth(), $started);

        $service = app(BillingService::class);

        $november = $service->issueDueInvoices(Carbon::parse('2026-11-20 10:00:00'));
        $this->assertCount(1, $november);
        $this->assertSame('2026-11-10', $november[0]->period_start->toDateString());
        $this->assertSame('2026-12-09', $november[0]->period_end->toDateString());

        // Still November, still billed.
        $this->assertCount(0, $service->issueDueInvoices(Carbon::parse('2026-11-28 10:00:00')));

        // December starts a new period.
        $december = $service->issueDueInvoices(Carbon::parse('2026-12-12 10:00:00'));
        $this->assertCount(1, $december);
        $this->assertSame('2026-12-10', $december[0]->period_start->toDateString());
        $this->assertNotSame($november[0]->number, $december[0]->number);
    }

    public function test_a_free_plan_and_a_suspended_shop_are_not_invoiced(): void
    {
        $free = Shop::where('plan', 'starter')->where('status', 'active')->firstOrFail();
        $this->subscribed($free, Plan::where('code', 'starter')->firstOrFail(), now()->subMonth());

        $suspended = Shop::where('plan', 'growth')->where('status', 'active')->firstOrFail();
        $suspended->update(['status' => 'suspended']);
        $suspended->subscriptions()->update(['status' => 'cancelled', 'ends_at' => now()]);
        $this->subscribed($suspended, $this->growth(), now()->subMonth());

        Invoice::query()->where('shop_id', $free->id)->delete();
        Invoice::query()->where('shop_id', $suspended->id)->delete();

        $issued = app(BillingService::class)->issueDueInvoices();

        $this->assertSame([], array_values(array_filter(
            $issued,
            fn (Invoice $i) => in_array($i->shop_id, [$free->id, $suspended->id], true),
        )));
    }

    public function test_an_invoice_is_due_a_week_after_its_period_starts(): void
    {
        $shop = $this->inkwell();
        $shop->subscriptions()->update(['status' => 'cancelled', 'ends_at' => now()]);
        Invoice::query()->delete();

        $this->subscribed($shop, $this->growth(), Carbon::parse('2026-11-10 09:00:00'));

        $issued = app(BillingService::class)->issueDueInvoices(Carbon::parse('2026-11-20 10:00:00'));

        $this->assertSame('2026-11-17', $issued[0]->due_at->toDateString());
    }

    public function test_an_open_invoice_past_its_due_date_reads_as_overdue(): void
    {
        $invoice = Invoice::create([
            'shop_id' => $this->inkwell()->id,
            'number' => 'INV-TEST-0001',
            'amount' => 59_900,
            'status' => Invoice::OPEN,
            'issued_at' => now()->subDays(20),
            'due_at' => now()->subDays(13),
        ]);

        $this->assertTrue($invoice->isOverdue());
        $this->assertSame(13, $invoice->daysOverdue());

        // Overdue is computed, never stored: settling it changes the answer with
        // no nightly job in between.
        app(BillingService::class)->recordPayment($invoice);

        $this->assertFalse($invoice->fresh()->isOverdue());
        $this->assertSame(0, $invoice->fresh()->daysOverdue());
    }

    public function test_recording_a_payment_stamps_who_and_refuses_a_second_time(): void
    {
        $invoice = Invoice::create([
            'shop_id' => $this->inkwell()->id,
            'number' => 'INV-TEST-0002',
            'amount' => 59_900,
            'status' => Invoice::OPEN,
            'issued_at' => now(),
            'due_at' => now()->addWeek(),
        ]);

        $admin = $this->admin();
        $paid = app(BillingService::class)->recordPayment($invoice, $admin);

        $this->assertSame(Invoice::PAID, $paid->status);
        $this->assertNotNull($paid->paid_at);
        $this->assertSame($admin->id, $paid->paid_by);

        // Collecting the same bill twice is the one mistake a ledger cannot
        // absorb quietly.
        $this->expectException(ValidationException::class);
        app(BillingService::class)->recordPayment($paid, $admin);
    }

    public function test_a_void_invoice_is_never_collected(): void
    {
        $invoice = Invoice::create([
            'shop_id' => $this->inkwell()->id,
            'number' => 'INV-TEST-0003',
            'amount' => 59_900,
            'status' => Invoice::OPEN,
            'issued_at' => now(),
            'due_at' => now()->addWeek(),
        ]);

        app(BillingService::class)->void($invoice);

        $this->assertSame(Invoice::VOID, $invoice->fresh()->status);

        $this->expectException(ValidationException::class);
        app(BillingService::class)->recordPayment($invoice->fresh());
    }

    public function test_a_voided_period_is_billed_again_on_the_next_run(): void
    {
        $shop = $this->inkwell();
        $shop->subscriptions()->update(['status' => 'cancelled', 'ends_at' => now()]);
        Invoice::query()->delete();

        $this->subscribed($shop, $this->growth(), Carbon::parse('2026-11-10 09:00:00'));
        $service = app(BillingService::class);

        $first = $service->issueDueInvoices(Carbon::parse('2026-11-20 10:00:00'));
        $service->void($first[0]);

        // Void means "this bill was wrong", not "this month is free".
        $again = $service->issueDueInvoices(Carbon::parse('2026-11-21 10:00:00'));

        $this->assertCount(1, $again);
        $this->assertSame('2026-11-10', $again[0]->period_start->toDateString());
    }

    public function test_switching_plan_mid_month_voids_the_superseded_bill(): void
    {
        $shop = $this->inkwell();
        $shop->subscriptions()->update(['status' => 'cancelled', 'ends_at' => now()]);
        Invoice::query()->delete();

        $service = app(BillingService::class);

        $service->subscribe($shop, Plan::where('code', 'growth')->firstOrFail());
        $first = Invoice::where('shop_id', $shop->id)->firstOrFail();
        $this->assertSame(Invoice::OPEN, $first->status);

        // Upgrading the same month must not leave both bills collectable.
        $service->subscribe($shop, Plan::where('code', 'pro')->firstOrFail());

        $this->assertSame(Invoice::VOID, $first->fresh()->status);

        $open = Invoice::where('shop_id', $shop->id)->unpaid()->get();
        $this->assertCount(1, $open);
        $this->assertSame(149_900, $open[0]->amount);
    }

    public function test_the_command_issues_and_reports(): void
    {
        $shop = $this->inkwell();
        $shop->subscriptions()->update(['status' => 'cancelled', 'ends_at' => now()]);
        Invoice::query()->delete();

        $this->subscribed($shop, $this->growth(), now()->subDays(2)->startOfDay());

        $this->artisan('billing:issue')
            ->expectsOutputToContain('1 invoice(s) issued')
            ->assertSuccessful();

        // Running it again the same day is a no-op, not a second bill.
        $this->artisan('billing:issue')
            ->expectsOutputToContain('Nothing due')
            ->assertSuccessful();

        $this->assertSame(1, Invoice::where('shop_id', $shop->id)->count());
    }

    public function test_the_admin_invoice_screen_lists_and_collects(): void
    {
        $invoice = Invoice::create([
            'shop_id' => $this->inkwell()->id,
            'number' => 'INV-TEST-0004',
            'amount' => 59_900,
            'status' => Invoice::OPEN,
            'issued_at' => now(),
            'due_at' => now()->addWeek(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListInvoices::class)
            ->assertCanSeeTableRecords([$invoice])
            ->callTableAction('markPaid', $invoice);

        $this->assertSame(Invoice::PAID, $invoice->fresh()->status);
    }

    public function test_the_shop_billing_page_shows_what_is_outstanding(): void
    {
        $shop = $this->inkwell();
        Invoice::query()->where('shop_id', $shop->id)->delete();

        Invoice::create([
            'shop_id' => $shop->id,
            'number' => 'INV-TEST-0005',
            'amount' => 59_900,
            'status' => Invoice::OPEN,
            'issued_at' => now()->subDays(20),
            'due_at' => now()->subDays(13),
        ]);

        $this->actingAs(User::where('email', 'kevin@inkwell.ph')->firstOrFail())
            ->get('/console/billing')
            ->assertSuccessful()
            ->assertSee('Outstanding')
            ->assertSee('₱599.00')
            ->assertSee('Overdue 13d');
    }
}
