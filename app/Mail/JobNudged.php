<?php

namespace App\Mail;

use App\Models\Job;
use App\Support\ReplyAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your job is waiting on you" — the reminder behind the Needs attention list.
 *
 * A nudge that cannot be answered is just a reminder to feel guilty, so the
 * Reply-To names the job and the body links straight to it. Deliberately not
 * queued, for the same reason as every other message here: the queue driver is
 * `database` and no worker is guaranteed, so a queued mail looks like a bug.
 */
class JobNudged extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Job $job,
        public string $customerName,
    ) {
    }

    public function envelope(): Envelope
    {
        $replyTo = ReplyAddress::for($this->job);

        return new Envelope(
            subject: 'About your job '.$this->job->reference,
            replyTo: $replyTo === null ? [] : [$replyTo],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.job-nudged',
            with: ['shopName' => $this->job->shop?->name ?? 'Your shop'],
        );
    }
}
