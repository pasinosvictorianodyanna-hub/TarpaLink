<?php

namespace App\Console\Commands;

use App\Services\Billing;
use App\Support\Money;
use Illuminate\Console\Command;

/**
 * Issue this month's subscription invoices.
 *
 * Scheduled daily rather than monthly. A monthly schedule has one chance to run:
 * if the machine is asleep on the first of the month, every shop goes unbilled
 * and nothing says so. Running daily is safe because `issueDueInvoices()` asks
 * "does this period already have an invoice" rather than "has it been a month" —
 * so thirty runs produce one invoice and the thirty-first produces none.
 */
class IssueSubscriptionInvoices extends Command
{
    protected $signature = 'billing:issue';

    protected $description = 'Issue an invoice for every active paid subscription in its current period';

    public function handle(Billing $billing): int
    {
        $issued = $billing->issueDueInvoices();
        $unbilled = $billing->unbilledShops();

        foreach ($issued as $invoice) {
            $this->line(sprintf(
                '  %s  %s  %s',
                $invoice->number,
                $invoice->shop?->name ?? 'shop '.$invoice->shop_id,
                Money::format($invoice->amount),
            ));
        }

        if ($issued !== []) {
            $this->info(count($issued).' invoice(s) issued.');
        }

        // An empty result has two meanings and they are not the same: "every
        // subscription is already billed for this period" and "there are no
        // subscriptions to bill". The old message collapsed them, so a database
        // where three shops sat on paid plans with nothing in `subscriptions`
        // reported a clean run every night.
        if ($unbilled->isNotEmpty()) {
            $this->warn($unbilled->count().' shop(s) are on a paid plan with no active subscription — nothing will ever bill them:');

            foreach ($unbilled as $shop) {
                $this->line(sprintf('  %-22s plan=%s  no subscription', $shop->name, $shop->plan));
            }

            $this->warn('Re-save the plan on the shop in the admin panel, or switch plan from the shop console, to create one.');
        }

        if ($issued === [] && $unbilled->isEmpty()) {
            $this->info('Nothing due — every active paid subscription is already billed for this period.');
        }

        return self::SUCCESS;
    }
}
