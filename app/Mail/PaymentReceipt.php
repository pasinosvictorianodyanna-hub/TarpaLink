<?php

namespace App\Mail;

use App\Models\Job;
use App\Models\Payment;
use App\Support\ReplyAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The receipt for a settled payment.
 *
 * Sent from the webhook job, after the payment row is already `paid`. That order
 * matters: the money is recorded first and the mail is a courtesy, so a mail
 * failure can never be mistaken for a payment failure — and the job's retry sees
 * `status === 'paid'`, returns early, and cannot send a second receipt.
 */
class PaymentReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Job $job,
        public Payment $payment,
        public string $customerName,
        public string $shopName,
    ) {
    }

    public function envelope(): Envelope
    {
        $replyTo = ReplyAddress::for($this->job);

        return new Envelope(
            subject: 'Receipt for '.$this->job->reference,
            replyTo: $replyTo === null ? [] : [$replyTo],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.receipt');
    }
}
