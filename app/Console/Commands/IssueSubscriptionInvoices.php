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

        if ($issued === []) {
            $this->info('Nothing due — every active paid subscription is already billed for this period.');

            return self::SUCCESS;
        }

        foreach ($issued as $invoice) {
            $this->line(sprintf(
                '  %s  %s  %s',
                $invoice->number,
                $invoice->shop?->name ?? 'shop '.$invoice->shop_id,
                Money::format($invoice->amount),
            ));
        }

        $this->info(count($issued).' invoice(s) issued.');

        return self::SUCCESS;
    }
}
