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
 * The customer's decision, sent back to the shop.
 *
 * A rejected proof is the more urgent of the two: the job is still sitting in
 * `awaiting_proof` and nobody will move it until somebody looks. Before this,
 * the only way a shop learned about it was by opening the queue.
 */
class ProofDecided extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Job $job,
        public bool $approved,
        public string $customerName,
        public string $ownerName,
        public ?string $note = null,
    ) {
    }

    public function envelope(): Envelope
    {
        // The shop answers a rejection by asking what to change, so this one is
        // tagged as well — a reply files itself on the job instead of arriving
        // as a loose email nobody can tie back to anything.
        $replyTo = ReplyAddress::for($this->job);

        return new Envelope(
            subject: ($this->approved ? 'Proof approved — ' : 'Changes requested — ').$this->job->reference,
            replyTo: $replyTo === null ? [] : [$replyTo],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.proof-decided',
            with: [
                // The console, not the panel: this mail goes to the shop, and
                // the panel's job screen no longer exists. A dead link in an
                // approval email is the one click that matters.
                'jobUrl' => route('console.jobs.show', $this->job),
            ],
        );
    }
}
