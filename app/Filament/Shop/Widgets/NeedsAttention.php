<?php

namespace App\Filament\Shop\Widgets;

use App\Filament\Shop\Concerns\ScopesToShop;
use App\Filament\Shop\Resources\Jobs\JobResource;
use App\Models\Job;
use App\Models\JobActivity;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Jobs that are stuck, and for how long.
 *
 * Every row is a job where the next move belongs to somebody else — the customer
 * has not approved a proof, or has not paid, or has not picked up. The one
 * exception is a paid job with no proof on it yet: that one is the shop's own
 * fault, and it is listed for exactly that reason.
 * The thresholds are deliberately conservative: chasing a quote that is two hours
 * old trains the owner to ignore the list, which makes the list worthless.
 *
 * A job that is already past its pickup date outranks all of them, because the
 * shop has broken a promise rather than merely waiting on one.
 */
class NeedsAttention extends Widget
{
    use ScopesToShop;

    /** Hours of silence before a sent proof counts as stuck. */
    private const PROOF_HOURS = 6;

    /** Hours before an unconfirmed quote counts as stuck. */
    private const QUOTE_HOURS = 24;

    /** Hours a finished job may sit on the shelf before it is chased. */
    private const PICKUP_HOURS = 48;

    /** How many rows the card shows before it stops being a glance. */
    private const LIMIT = 6;

    protected static ?int $sort = 4;

    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.shop.widgets.needs-attention';

    /**
     * The rows the card shows, longest wait first.
     *
     * @return array<int, array{job: Job, issue: string, waiting: string, since: Carbon, url: string}>
     */
    public function rows(): array
    {
        $shop = $this->shop();

        if (! $shop) {
            return [];
        }

        $jobs = $shop->jobs()
            ->with(['user', 'service.options', 'proofs', 'payment'])
            ->whereNotIn('status', ['collected', 'cancelled'])
            ->get();

        $rows = [];

        foreach ($jobs as $job) {
            $reason = $this->classify($job);

            if ($reason === null) {
                continue;
            }

            $rows[] = [
                'job' => $job,
                'issue' => $reason['issue'],
                'waiting' => $this->waited($reason['since']),
                'since' => $reason['since'],
                'url' => JobResource::getUrl('view', ['record' => $job]),
            ];
        }

        // Longest wait first: the oldest row is the one most likely to turn into
        // a complaint, and it is the one the owner should open first.
        usort($rows, fn (array $a, array $b) => $a['since']->getTimestamp() <=> $b['since']->getTimestamp());

        return array_slice($rows, 0, self::LIMIT);
    }

    protected function getViewData(): array
    {
        return ['rows' => $this->rows()];
    }

    /**
     * Why this job needs a human, or null when it is simply in progress.
     *
     * @return array{issue: string, since: Carbon}|null
     */
    private function classify(Job $job): ?array
    {
        $now = now();

        if ($job->due_at && $job->due_at->lt($now) && in_array($job->status, ['approved', 'in_production', 'ready'], true)) {
            return ['issue' => 'Past its pickup date', 'since' => $job->due_at];
        }

        if ($job->status === 'awaiting_proof') {
            // The clock starts when the proof went out, not when the job was
            // created — a shop that sat on the artwork for two days should not
            // be told the customer is the one stalling.
            $sent = $job->latestProof()?->created_at ?? $job->updated_at;

            return $sent && $sent->lt($now->copy()->subHours(self::PROOF_HOURS))
                ? ['issue' => "Proof sent, customer hasn't approved", 'since' => $sent]
                : null;
        }

        if ($job->status === 'quoted') {
            // `quoted` means the order is in and the money is not — there is no
            // "ordering" left for the customer to do. Who the shop is waiting on
            // depends on the payment, and the two cases deserve different words:
            // unpaid is the customer's move, paid-with-no-proof is the shop's.
            if ($job->isPaid()) {
                return $job->created_at->lt($now->copy()->subHours(self::PROOF_HOURS))
                    ? ['issue' => 'Paid, proof not sent yet', 'since' => $job->created_at]
                    : null;
            }

            return $job->created_at->lt($now->copy()->subHours(self::QUOTE_HOURS))
                ? ['issue' => 'Unpaid, payment not confirmed', 'since' => $job->created_at]
                : null;
        }

        if ($job->status === 'ready' && $job->updated_at->lt($now->copy()->subHours(self::PICKUP_HOURS))) {
            return ['issue' => 'Ready for pickup, not collected', 'since' => $job->updated_at];
        }

        return null;
    }

    /** "14 hrs", "2 days" — the same units the shop talks in, not a date. */
    private function waited(Carbon $since): string
    {
        $hours = (int) $since->diffInHours(now());

        if ($hours < 1) {
            return 'just now';
        }

        if ($hours < 24) {
            return $hours.' '.($hours === 1 ? 'hr' : 'hrs');
        }

        $days = (int) $since->diffInDays(now());

        return $days.' '.($days === 1 ? 'day' : 'days');
    }

    /**
     * Chase the customer about a stuck job.
     *
     * Two things happen, and the order matters: the nudge is written to the
     * job's activity trail first, so it is recorded even if the mail server is
     * down. The email is best-effort — with MAIL_MAILER=log it lands in the log,
     * and the owner is told that plainly rather than being told it was sent.
     */
    public function nudge(int $jobId): void
    {
        $shop = $this->shop();

        abort_if($shop === null, 403);

        $job = $shop->jobs()->with('user')->findOrFail($jobId);

        $job->activities()->create([
            'user_id' => auth()->id(),
            'kind' => JobActivity::NOTE,
            'from_status' => null,
            'to_status' => null,
            'note' => 'Shop nudged the customer',
        ]);

        $sent = false;

        if (filled($job->user?->email)) {
            try {
                Mail::raw(
                    "Hi {$job->user->name},\n\n"
                    ."Your job {$job->reference} is waiting on you at {$shop->name}.\n\n"
                    .'Open it here: '.route('jobs.show', $job)."\n",
                    fn ($message) => $message
                        ->to($job->user->email)
                        ->subject("About your job {$job->reference}"),
                );

                $sent = true;
            } catch (Throwable $e) {
                report($e);
            }
        }

        Notification::make()
            ->title($sent ? 'Nudge emailed to '.$job->user->name : 'Nudge recorded on '.$job->reference)
            ->body($sent ? null : 'Mail is not configured on this deployment, so nothing was sent — the job now shows the nudge instead.')
            ->status($sent ? 'success' : 'warning')
            ->send();
    }
}
