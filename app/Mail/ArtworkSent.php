<?php

namespace App\Mail;

use App\Models\Artwork;
use App\Models\Job;
use App\Support\ReplyAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "The customer sent their design" — the message that tells a shop it can start.
 *
 * Without it the file arrives silently: the job is already in the queue, nothing
 * about its status changes when artwork lands, and the shop's only clue would be
 * opening every job to check. Deliberately not queued, for the same reason as
 * every other message here — the queue driver is `database` and no worker is
 * guaranteed, so a queued mail looks like a bug. `Notifier` swallows failures,
 * so a dead mail server cannot fail the upload.
 */
class ArtworkSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Job $job,
        public Artwork $artwork,
        public string $customerName,
        public string $shopName,
    ) {}

    public function envelope(): Envelope
    {
        // A file is exactly the sort of thing a shop answers with "can you send
        // it as a PDF?" — so the reply goes to the job, not to a mailbox nobody
        // reads.
        $replyTo = ReplyAddress::for($this->job);

        return new Envelope(
            subject: $this->customerName.' sent the artwork for '.$this->job->reference,
            replyTo: $replyTo === null ? [] : [$replyTo],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.artwork-sent',
            with: [
                'fileName' => $this->artwork->shortName(60),
                'fileSize' => $this->artwork->sizeLabel(),
            ],
        );
    }
}
