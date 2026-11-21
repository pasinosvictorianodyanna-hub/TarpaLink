<?php

namespace App\Filament\Admin\Pages;

use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The platform overview.
 *
 * Filament's stock dashboard gives widgets and nothing else; the design asks for
 * an "Export report" action in the header, and the people who ask for that
 * action want the numbers behind the tiles, not the tiles.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Platform overview';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export report')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportPayments()),
        ];
    }

    /**
     * Every captured payment on the platform, one row each.
     *
     * Streamed rather than assembled in memory: this is the one export that grows
     * with the whole marketplace, not with one shop.
     */
    public function exportPayments(): StreamedResponse
    {
        $payments = Payment::query()
            ->with(['job.shop', 'job.user', 'job.service'])
            ->where('status', 'paid')
            ->orderBy('paid_at')
            ->cursor();

        $filename = 'tarpalink-platform-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($payments): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Captured', 'Shop', 'Job', 'Service', 'Customer',
                'Gross (PHP)', 'Commission (PHP)', 'Net (PHP)', 'Payout',
            ]);

            foreach ($payments as $payment) {
                fputcsv($out, [
                    $payment->paid_at?->format('Y-m-d') ?? '',
                    $payment->job?->shop?->name ?? '',
                    $payment->job?->reference ?? '',
                    $payment->job?->service?->name ?? '',
                    $payment->job?->user?->name ?? '',
                    number_format($payment->amount / 100, 2, '.', ''),
                    number_format($payment->commission / 100, 2, '.', ''),
                    number_format($payment->net() / 100, 2, '.', ''),
                    $payment->payout_status,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
