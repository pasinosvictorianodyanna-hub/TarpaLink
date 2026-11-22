<?php

namespace App\Support;

use App\Models\Job;
use Illuminate\Mail\Mailables\Address;

/**
 * The address a reply should go to, and the reference read back out of it.
 *
 * Before this, every notification went out from the application address with no
 * Reply-To, so a customer who hit "Reply" and typed an answer was writing to a
 * mailbox nobody read. That is worse than not offering the option: the customer
 * believes they have told the shop something, and the shop's only evidence is
 * silence.
 *
 * The reference travels in a plus-tag — `reply+TL-48213@inbound.example` —
 * because that is the one part of an address every mail provider preserves
 * through a reply. A subject line gets rewritten by clients that prefix "Re:"
 * or translate it; a custom header is dropped by most clients when they compose
 * a reply. The local part is copied verbatim.
 *
 * When inbound mail is not configured the whole thing degrades to null and the
 * mailables send no Reply-To at all, so replies go to the sender rather than
 * into a black hole.
 */
final class ReplyAddress
{
    /** The configured inbound mailbox, or null when inbound mail is off. */
    public static function address(): ?string
    {
        $address = trim((string) Credentials::get('inbound', 'address'));

        // A value with no `@` cannot be tagged and cannot receive anything, so
        // treat it as unset rather than emitting a malformed header.
        if ($address === '' || ! str_contains($address, '@')) {
            return null;
        }

        return $address;
    }

    public static function enabled(): bool
    {
        return self::address() !== null;
    }

    /**
     * `reply+TL-48213@inbound.example`, or null when there is no mailbox to
     * point at. The caller filters the null out of the envelope.
     */
    public static function for(Job|string|null $job): ?Address
    {
        $base = self::address();
        $reference = $job instanceof Job ? $job->reference : $job;

        if ($base === null || blank($reference)) {
            return null;
        }

        [$local, $domain] = explode('@', $base, 2);

        return new Address($local.'+'.$reference.'@'.$domain);
    }

    /**
     * Pull a job reference out of a recipient address.
     *
     * Accepts a bare address or the `Name <addr@host>` form, because inbound
     * providers pass whichever they were given. Returns null rather than a
     * guess when there is no tag — the caller falls back to the subject.
     */
    public static function reference(?string $recipient): ?string
    {
        $recipient = trim((string) $recipient);

        if ($recipient === '') {
            return null;
        }

        if (preg_match('/<([^>]*)>/', $recipient, $angled)) {
            $recipient = $angled[1];
        }

        if (! preg_match('/\+('.Job::REFERENCE_PATTERN.')@/i', $recipient, $matches)) {
            return null;
        }

        return strtoupper($matches[1]);
    }
}
