<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The payout ledger as a file the shop can hand to its bookkeeper.
 *
 * Deliberately a plain controller rather than a Livewire action: a CSV of a few
 * thousand rows should stream, not be assembled in memory inside a component
 * that is also rendering a page.
 *
 * The rows are read as flat columns through a cursor rather than as models.
 * That is not a micro-optimisation — a year of payouts is a few thousand models
 * with their relations, and holding all of them to write a text file is how an
 * export becomes the slowest page in the panel. Selecting the seven columns the
 * file actually prints keeps memory flat no matter how long the ledger gets.
 *
 * `cursor()` would normally undo the eager loading and issue a query per row, so
 * the service name is joined here instead of lazy-loaded. One query, no models,
 * constant memory.
 */
class PayoutExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $shop = $request->user()?->currentShop();

        abort_if($shop === null, 403);

        $rows = $shop->payments()
            // `payments()` is a hasManyThrough and already joins `jobs`, so only
            // the service needs fetching.
            ->leftJoin('services', 'services.id', '=', 'jobs.service_id')
            // Refunded payments stay in the file: their net is negative, and a
            // bookkeeper who cannot see why the payout was short will ask.
            ->whereIn('payments.status', ['paid', 'refunded'])
            // `id` breaks ties: without it two payments stamped in the same
            // second can arrive in either order, and a cursor is only stable if
            // the ordering is total.
            ->orderBy('payments.paid_at')
            ->orderBy('payments.id')
            ->select([
                'payments.paid_at',
                'payments.amount',
                'payments.refunded_amount',
                'payments.commission',
                'payments.commission_refunded',
                'payments.processing_fee',
                'payments.payout_status',
                'payments.payout_date',
                'jobs.reference as job_reference',
                'services.name as service_name',
            ])
            ->cursor();

        $filename = sprintf('tarpalink-payouts-%s-%s.csv', $shop->slug, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Paid at', 'Job', 'Service', 'Gross', 'Refunded', 'Commission', 'Fee', 'Net', 'Payout status', 'Payout date']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->paid_at ? substr((string) $row->paid_at, 0, 10) : '',
                    $row->job_reference ?? '',
                    $row->service_name ?? '',
                    number_format($row->amount / 100, 2, '.', ''),
                    number_format($row->refunded_amount / 100, 2, '.', ''),
                    // Net of what was given back: a bookkeeper reconciling this
                    // file needs the commission we actually kept, not the rate
                    // we charged before the refund.
                    number_format(($row->commission - $row->commission_refunded) / 100, 2, '.', ''),
                    number_format($row->processing_fee / 100, 2, '.', ''),
                    number_format(Payment::netFrom(
                        amount: $row->amount,
                        refunded: $row->refunded_amount,
                        commission: $row->commission,
                        commissionRefunded: $row->commission_refunded,
                        fee: $row->processing_fee,
                    ) / 100, 2, '.', ''),
                    $row->payout_status,
                    $row->payout_date ? substr((string) $row->payout_date, 0, 10) : '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
