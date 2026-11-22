<?php

namespace App\Mail;

use App\Models\Job;
use App\Models\Proof;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your proof is ready" — the notification the proof step was missing.
 *
 * Deliberately not queued. The queue driver is `database` in this deployment and
 * there is no worker guaranteed to be running, so a queued mail would sit in a
 * table forever and look exactly like a bug. `Notifier` swallows failures, so
 * sending inline cannot break the upload it belongs to.
 */
class ProofSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Job $job,
        public Proof $proof,
        public string $customerName,
        public string $shopName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your proof for '.$this->job->reference.' is ready to approve',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.proof-sent',
            with: [
                'note' => $this->proof->note,
                'version' => $this->proof->version,
            ],
        );
    }
}
