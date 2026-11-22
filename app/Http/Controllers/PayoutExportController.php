<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The payout ledger as a file the shop can hand to its bookkeeper.
 *
 * Deliberately a plain controller rather than a Livewire action: a CSV of a few
 * thousand rows should stream, not be assembled in memory inside a component
 * that is also rendering a page.
 */
class PayoutExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $shop = $request->user()?->currentShop();

        abort_if($shop === null, 403);

        $payments = $shop->payments()
            // Each row prints the service name through its job. Without this
            // the export issued one query per row while streaming the CSV.
            ->with('job.service')
            ->where('payments.status', 'paid')
            ->orderBy('payments.paid_at')
            ->get();

        $filename = sprintf('tarpalink-payouts-%s-%s.csv', $shop->slug, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($payments): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Paid at', 'Job', 'Service', 'Gross', 'Commission', 'Net', 'Payout status', 'Payout date']);

            foreach ($payments as $payment) {
                fputcsv($out, [
                    $payment->paid_at?->format('Y-m-d') ?? '',
                    $payment->job?->reference ?? '',
                    $payment->job?->service?->name ?? '',
                    number_format($payment->amount / 100, 2, '.', ''),
                    number_format($payment->commission / 100, 2, '.', ''),
                    number_format($payment->net() / 100, 2, '.', ''),
                    $payment->payout_status,
                    $payment->payout_date?->format('Y-m-d') ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
