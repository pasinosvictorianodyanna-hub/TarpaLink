<?php

namespace App\Filament\Admin\Pages;

use App\Models\Payment;
use App\Models\Proof;
use App\Models\User;
use App\Support\Credentials;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
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
 *
 * Two of the four cards are editable, and the line is deliberate. PayMongo keys
 * and the inbound mailbox are *credentials*: they rotate, they differ per
 * deployment, and being able to change one without a redeploy is the difference
 * between fixing an outage in a minute and fixing it in an hour. Mail, queue and
 * storage are *deployment shape* — an SMTP host, a queue driver, a disk — and a
 * form that lets someone switch the queue to `sync` from a browser is a form
 * that can silently stop payments settling. Those stay in the environment, where
 * changing them requires a deploy and a restart.
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
            $this->inboundCard(),
            $this->mailCard(),
            $this->queueCard(),
            $this->storageCard(),
        ];
    }

    private function paymongoCard(): array
    {
        // These used to read `services.paymongo.public_key` and
        // `services.paymongo.secret_key`. The config keys are `public` and
        // `secret`, so both were always null and the card reported "Not
        // configured" on a deployment that was taking payments.
        $public = Credentials::get('paymongo', 'public');
        $secret = Credentials::get('paymongo', 'secret');
        $webhook = Credentials::get('paymongo', 'webhook_secret');

        $lastPaid = Payment::where('status', 'paid')->latest('paid_at')->first();

        return [
            'key' => 'paymongo',
            'name' => 'PayMongo',
            'role' => 'Payments',
            'ok' => $secret !== null && $webhook !== null,
            'badge' => $secret === null ? 'Not configured' : (str_starts_with($secret, 'sk_live') ? 'Live' : 'Sandbox'),
            'badgeTone' => $secret === null ? 'danger' : (str_starts_with($secret, 'sk_live') ? 'warning' : 'success'),
            'blurb' => 'Creates a payment intent at checkout and receives the webhook that marks a job paid.',
            'facts' => [
                'Mode' => $secret === null ? '—' : (str_starts_with($secret, 'sk_live') ? 'live keys' : 'test keys'),
                'Public key' => $public === null ? '—' : $this->mask($public),
                'Webhook' => '/webhooks/paymongo',
                'Webhook secret' => $webhook === null ? 'missing — every callback is rejected' : 'set',
                'Last settled' => $lastPaid?->paid_at?->diffForHumans() ?? 'never',
            ],
            'source' => $this->sourceLabel('paymongo', ['public', 'secret', 'webhook_secret']),
            'editable' => 'configurePayMongo',
            'fix' => $webhook === null
                ? 'Save a webhook secret below, or set PAYMONGO_WEBHOOK_SECRET; without it the endpoint refuses everything.'
                : null,
        ];
    }

    private function inboundCard(): array
    {
        $address = Credentials::get('inbound', 'address');
        $secret = Credentials::get('inbound', 'secret');

        $filed = DB::table('job_activities')->where('source', 'email')->count();

        return [
            'key' => 'inbound',
            'name' => 'Inbound mail',
            'role' => 'Customer replies',
            'ok' => $address !== null && $secret !== null,
            'badge' => $address === null ? 'Off' : ($secret === null ? 'Unsigned' : 'Listening'),
            'badgeTone' => $address === null || $secret === null ? 'warning' : 'success',
            'blurb' => 'Notifications carry a reply address tagged with the job reference, so a customer who answers is filed on their job.',
            'facts' => [
                'Mailbox' => $address ?? '—',
                'Webhook' => '/webhooks/inbound',
                'Signing secret' => $secret === null ? 'missing — every delivery is rejected' : 'set',
                'Replies filed' => (string) $filed,
            ],
            'source' => $this->sourceLabel('inbound', ['address', 'secret']),
            'editable' => 'configureInbound',
            'fix' => $address === null
                ? 'With no mailbox set, notifications go out with no Reply-To at all — a reply address nobody reads is worse than none.'
                : ($secret === null ? 'Set a signing secret, or the inbound webhook rejects every delivery.' : null),
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
            'source' => 'from the environment',
            'editable' => null,
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
            'source' => 'from the environment',
            'editable' => null,
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
            'source' => 'from the environment',
            'editable' => null,
            'fix' => $driver === 'local'
                ? 'PROOF_DISK_DRIVER=local loses artwork on every rebuild. Set PROOF_DISK_DRIVER=s3 and AWS_BUCKET before accepting real proofs.'
                : null,
        ];
    }

    /**
     * "Saved here" or "from .env" — because "set" is not enough information.
     *
     * An administrator looking at a key that is set, in a deployment where they
     * just saved it, needs to know whether their save is what is in force or
     * whether an environment value is shadowing it. Those look identical from
     * the outside and fail in completely different ways.
     *
     * @param  array<int, string>  $keys
     */
    private function sourceLabel(string $provider, array $keys): string
    {
        $sources = collect($keys)->map(fn (string $key): string => Credentials::source($provider, $key));

        if ($sources->contains(Credentials::FROM_DATABASE)) {
            return $sources->contains(Credentials::FROM_ENVIRONMENT)
                ? 'saved here, partly from .env'
                : 'saved here';
        }

        return $sources->contains(Credentials::FROM_ENVIRONMENT)
            ? 'from .env'
            : 'not configured';
    }

    /** `pk_test_51H8x…` — enough to recognise the key, not enough to use it. */
    private function mask(string $value): string
    {
        return mb_strlen($value) <= 12
            ? str_repeat('•', mb_strlen($value))
            : mb_substr($value, 0, 8).'••••'.mb_substr($value, -4);
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

        $checks[] = ['label' => 'PayMongo webhook secret set', 'ok' => Credentials::get('paymongo', 'webhook_secret') !== null];
        $checks[] = ['label' => 'Inbound mail signing secret set', 'ok' => Credentials::get('inbound', 'secret') !== null];
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

    /**
     * The PayMongo keys, editable from the screen that reports on them.
     *
     * Mounted from the card rather than listed in the header, so the button sits
     * beside the thing it changes instead of floating at the top of the page.
     * Filament resolves a mounted action by looking for a method of the same
     * name, which is why this is public and returns an `Action`.
     *
     * The form is filled from what is *saved*, never from `Credentials::get()`:
     * an environment secret must not be copied into a page's HTML just because
     * an administrator opened the modal.
     */
    public function configurePayMongoAction(): Action
    {
        return Action::make('configurePayMongo')
            ->label('Edit keys')
            ->modalHeading('PayMongo keys')
            ->modalDescription('Saved values take effect on the next request — no redeploy. Leave a field empty to fall back to whatever .env says.')
            ->modalSubmitActionLabel('Save keys')
            ->schema([
                TextInput::make('public')
                    ->label('Public key')
                    ->placeholder('pk_test_…')
                    ->autocomplete(false),

                TextInput::make('secret')
                    ->label('Secret key')
                    ->password()
                    ->revealable()
                    ->autocomplete(false)
                    ->helperText('Leaving this empty puts the platform back in simulated mode.'),

                TextInput::make('webhook_secret')
                    ->label('Webhook secret')
                    ->password()
                    ->revealable()
                    ->autocomplete(false)
                    ->helperText('PayMongo signs each callback with this. Without it every event is rejected.'),
            ])
            ->fillForm(fn (): array => $this->saved('paymongo', ['public', 'secret', 'webhook_secret']))
            ->action(function (array $data): void {
                Credentials::put('paymongo', $data, $this->editor());

                Notification::make()
                    ->title('PayMongo keys saved')
                    ->body($this->sourceLabel('paymongo', ['public', 'secret', 'webhook_secret']))
                    ->success()
                    ->send();
            });
    }

    public function configureInboundAction(): Action
    {
        return Action::make('configureInbound')
            ->label('Edit mailbox')
            ->modalHeading('Inbound mail')
            ->modalDescription('Notifications put reply+<job reference>@ on this mailbox, so a customer hitting Reply files their answer on the right job. Leave a field empty to fall back to .env.')
            ->modalSubmitActionLabel('Save mailbox')
            ->schema([
                TextInput::make('address')
                    ->label('Mailbox')
                    ->placeholder('reply@inbound.example.com')
                    ->email()
                    ->autocomplete(false)
                    ->helperText('The address your mail provider forwards to /webhooks/inbound.'),

                TextInput::make('secret')
                    ->label('Signing secret')
                    ->password()
                    ->revealable()
                    ->autocomplete(false)
                    ->helperText('Shared with the provider. Without it the webhook rejects every delivery.'),
            ])
            ->fillForm(fn (): array => $this->saved('inbound', ['address', 'secret']))
            ->action(function (array $data): void {
                Credentials::put('inbound', $data, $this->editor());

                Notification::make()
                    ->title('Inbound mail saved')
                    ->body($this->sourceLabel('inbound', ['address', 'secret']))
                    ->success()
                    ->send();
            });
    }

    /**
     * Saved values, keyed for the form, with absent keys present as null.
     *
     * @param  array<int, string>  $keys
     * @return array<string, ?string>
     */
    private function saved(string $provider, array $keys): array
    {
        $stored = Credentials::stored($provider);

        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => $stored[$key] ?? null])
            ->all();
    }

    /** The administrator making the change, for the audit column. */
    private function editor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
