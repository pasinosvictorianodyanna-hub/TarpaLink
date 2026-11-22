<?php

namespace App\Mail;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your shop is approved — choose a password."
 *
 * The account is created with a password nobody knows, so this link is the only
 * way in. It is a password-reset token rather than a credential, which means it
 * expires like one and cannot be replayed after the owner has used it.
 *
 * Deliberately not queued, like every other message here: the queue driver is
 * `database` with no worker guaranteed, so a queued invite would look exactly
 * like an approval that silently did nothing.
 */
class ShopInvited extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Shop $shop,
        public User $user,
        public string $link,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->shop->name.' is on TarpaLink — set your password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.shop-invited',
            with: [
                'shopName' => $this->shop->name,
                'ownerName' => $this->user->name,
                'link' => $this->link,
            ],
        );
    }
}
