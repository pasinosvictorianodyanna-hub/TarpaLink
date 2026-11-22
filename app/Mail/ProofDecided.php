<?php

namespace App\Mail;

use App\Models\Job;
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
        return new Envelope(
            subject: ($this->approved ? 'Proof approved — ' : 'Changes requested — ').$this->job->reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.proof-decided',
            with: [
                'jobUrl' => route('filament.shop.resources.jobs.view', ['record' => $this->job]),
            ],
        );
    }
}
