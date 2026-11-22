<?php

namespace App\Filament\Admin\Pages;

use App\Models\Payment;
use App\Models\Proof;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnitEnum;

/**
 * Every outside service the platform depends on, and whether it is actually
 * configured — not whether it is documented.
 *
 * This screen exists because the failure modes here are silent: an unset
 * webhook secret rejects every callback, and a queue with no worker looks
 * exactly like a queue with nothing to do.
 */
class Integrations extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Integrations';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Integrations';

    protected string $view = 'filament.admin.pages.integrations';

    /** @return array<int, array<string, mixed>> */
    public function cards(): array
    {
        return [
            $this->paymongoCard(),
            $this->mailCard(),
            $this->queueCard(),
            $this->storageCard(),
        ];
    }

    private function paymongoCard(): array
    {
        $public = (string) config('services.paymongo.public_key');
        $secret = (string) config('services.paymongo.secret_key');
        $webhook = (string) config('services.paymongo.webhook_secret');

        $lastPaid = Payment::where('status', 'paid')->latest('paid_at')->first();

        return [
            'key' => 'paymongo',
            'name' => 'PayMongo',
            'role' => 'Payments',
            'ok' => $secret !== '' && $webhook !== '',
            'badge' => $secret === '' ? 'Not configured' : (str_starts_with($secret, 'sk_live') ? 'Live' : 'Sandbox'),
            'badgeTone' => $secret === '' ? 'danger' : (str_starts_with($secret, 'sk_live') ? 'warning' : 'success'),
            'blurb' => 'Creates a payment intent at checkout and receives the webhook that marks a job paid.',
            'facts' => [
                'Mode' => $secret === '' ? '—' : (str_starts_with($secret, 'sk_live') ? 'live keys' : 'test keys'),
                'Public key' => $public === '' ? '—' : substr($public, 0, 8).'••••'.substr($public, -4),
                'Webhook' => '/webhooks/paymongo',
                'Webhook secret' => $webhook === '' ? 'missing — every callback is rejected' : 'set',
                'Last settled' => $lastPaid?->paid_at?->diffForHumans() ?? 'never',
            ],
            'fix' => $webhook === '' ? 'Set PAYMONGO_WEBHOOK_SECRET; without it the endpoint refuses everything.' : null,
        ];
    }

    private function mailCard(): array
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');

        return [
            'key' => 'mail',
            'name' => 'Mail',
            'role' => 'Transactional email',
            'ok' => $mailer !== 'log',
            'badge' => $mailer === 'log' ? 'Logging only' : ucfirst($mailer),
            'badgeTone' => $mailer === 'log' ? 'warning' : 'success',
            'blurb' => 'Sends proof-ready notices, status changes and receipts.',
            'facts' => [
                'Mailer' => $mailer,
                'From' => $from !== '' ? $from : '—',
                'Queue' => config('queue.default'),
            ],
            'fix' => $mailer === 'log'
                ? 'MAIL_MAILER=log writes mail to the log instead of sending it. Configure SMTP before customers rely on it.'
                : null,
        ];
    }

    private function queueCard(): array
    {
        $connection = (string) config('queue.default');

        $pending = null;
        $failed = null;

        try {
            $pending = DB::table('queue_jobs')->count();
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            // Table missing means migrations have not run against this database.
        }

        return [
            'key' => 'queue',
            'name' => 'Queue',
            'role' => 'Webhook settlement',
            'ok' => $connection !== 'sync' && $pending !== null,
            'badge' => $connection === 'sync' ? 'Synchronous' : ucfirst($connection),
            'badgeTone' => $connection === 'sync' ? 'danger' : 'success',
            'blurb' => 'PayMongo callbacks are queued. With no worker running, payments never settle.',
            'facts' => [
                'Connection' => $connection,
                'Waiting' => $pending === null ? 'unknown — table missing' : (string) $pending,
                'Failed' => $failed === null ? 'unknown' : (string) $failed,
                'Worker' => 'run `php artisan queue:work` in a separate process',
            ],
            'fix' => $connection === 'sync'
                ? 'QUEUE_CONNECTION=sync blocks the webhook response. Use a real queue with a worker.'
                : null,
        ];
    }

    private function storageCard(): array
    {
        // Probes the disk artwork actually lives on, not the default one. They
        // were the same until proofs got a disk of their own, and a card that
        // reports on a disk nobody uploads to is worse than no card.
        $disk = Proof::DISK;

        $writable = false;

        try {
            $probe = 'healthcheck/'.uniqid().'.txt';
            Storage::disk($disk)->put($probe, 'ok');
            $writable = Storage::disk($disk)->exists($probe);
            Storage::disk($disk)->delete($probe);
        } catch (Throwable) {
            $writable = false;
        }

        $driver = (string) config('filesystems.disks.'.$disk.'.driver');

        return [
            'key' => 'storage',
            'name' => 'Storage',
            'role' => 'Proof artwork',
            'ok' => $writable,
            'badge' => $writable ? 'Writable' : 'Not writable',
            'badgeTone' => $writable ? 'success' : 'danger',
            'blurb' => 'Proof files and uploaded artwork. A local disk lives inside the container and is lost on rebuild.',
            'facts' => [
                'Disk' => $disk,
                'Driver' => $driver,
                'Writable' => $writable ? 'yes' : 'no',
            ],
            'fix' => $driver === 'local'
                ? 'PROOF_DISK_DRIVER=local loses artwork on every rebuild. Set PROOF_DISK_DRIVER=s3 and AWS_BUCKET before accepting real proofs.'
                : null,
        ];
    }

    /** @return array<int, array{label: string, ok: bool}> */
    public function health(): array
    {
        $checks = [];

        try {
            DB::connection()->getPdo();
            $checks[] = ['label' => 'Database reachable', 'ok' => true];
        } catch (Throwable) {
            $checks[] = ['label' => 'Database reachable', 'ok' => false];
        }

        $checks[] = ['label' => 'Webhook secret set', 'ok' => (string) config('services.paymongo.webhook_secret') !== ''];
        $checks[] = ['label' => 'Queue is not synchronous', 'ok' => config('queue.default') !== 'sync'];
        $checks[] = ['label' => 'Storage writable', 'ok' => $this->storageCard()['ok']];

        return $checks;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('healthCheck')
                ->label('Run health check')
                ->icon('heroicon-m-heart')
                ->action(function (): void {
                    $failed = collect($this->health())->reject(fn (array $c): bool => $c['ok']);

                    Notification::make()
                        ->title($failed->isEmpty()
                            ? 'All checks passed'
                            : $failed->count().' '.str('check')->plural($failed->count()).' failed')
                        ->body($failed->pluck('label')->implode(', ') ?: null)
                        ->status($failed->isEmpty() ? 'success' : 'danger')
                        ->send();
                }),
        ];
    }
}
