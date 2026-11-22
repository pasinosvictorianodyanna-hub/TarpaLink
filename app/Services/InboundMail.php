<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobActivity;
use App\Models\User;
use App\Support\Credentials;
use App\Support\ReplyAddress;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A reply that came back to a job.
 *
 * Outbound mail names its job in the Reply-To address (see `ReplyAddress`), so
 * an inbound provider only has to forward the parsed message here and the words
 * land on the right job instead of in a mailbox nobody opens.
 *
 * Two rules shape everything below.
 *
 * **Mail never moves a job.** An inbound message is unauthenticated — anyone who
 * learns a reference number can send one — so it can append a note to the
 * history and nothing more. "Approved, go ahead" in an email is a note that the
 * shop reads; approval itself stays behind the signed-in link on the job page.
 * Wiring mail to the workflow would mean a forged sender could cancel a job.
 *
 * **A retry must not read as a second message.** Every provider retries on a
 * timeout, and two identical replies in a customer's history is a bug that looks
 * like a person. `source_ref` is the fingerprint that stops it.
 *
 * The payload shape is normalised rather than mandated: Mailgun, Postmark and a
 * plain relay all spell the same six fields differently, and refusing two of the
 * three would make this a demo instead of an integration.
 */
final class InboundMail
{
    /** Ceiling on what a single reply may add to the database. */
    public const MAX_BODY = 20000;

    /**
     * Is this request really from the inbound provider?
     *
     * HMAC over the exact bytes received, compared in constant time. Deliberately
     * not "compare a token in the body": the body is the thing being trusted, so
     * the proof has to cover it.
     *
     * No configured secret means inbound mail is off, and off means refuse. An
     * unsigned endpoint that appends to a job's history is an open invitation to
     * fill a shop's records with anything.
     */
    public static function verify(string $raw, ?string $signature): bool
    {
        $secret = (string) Credentials::get('inbound', 'secret');

        if ($secret === '' || blank($signature)) {
            return false;
        }

        $signature = strtolower(trim((string) $signature));

        // `sha256=<hex>` is what most providers send; a bare hex digest is what
        // a hand-rolled relay sends. Accept both rather than making the operator
        // discover the difference through 401s.
        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        return hash_equals(hash_hmac('sha256', $raw, $secret), $signature);
    }

    /**
     * Record a reply against the job it belongs to.
     *
     * Returns the row it wrote, or null when nothing was written — no reference
     * in the message, no such job, or a message already on file. The caller does
     * not need to tell those apart: all three are a 202 with `matched: false`,
     * because a provider that gets a 4xx will retry a message that can never
     * succeed.
     */
    public static function accept(array $payload): ?JobActivity
    {
        $to = self::first($payload, ['recipient', 'To', 'to', 'envelope_to']);
        $from = self::email(self::first($payload, ['sender', 'From', 'from', 'envelope_from']));
        $subject = self::first($payload, ['subject', 'Subject']);
        $body = self::body($payload);

        $job = self::jobFor($to, $subject);

        if ($job === null) {
            Log::info('Inbound mail matched no job.', ['to' => $to, 'subject' => $subject]);

            return null;
        }

        $fingerprint = self::fingerprint($payload, $from, $subject, $body);

        if (JobActivity::where('source_ref', $fingerprint)->exists()) {
            Log::info('Inbound mail was a duplicate.', ['job' => $job->reference, 'ref' => $fingerprint]);

            return null;
        }

        $sender = self::sender($job, $from);

        return $job->activities()->create([
            'user_id' => $sender?->id,
            'kind' => JobActivity::NOTE,
            'source' => JobActivity::EMAIL,
            'source_ref' => $fingerprint,
            'from_status' => null,
            'to_status' => null,
            'note' => self::summary($sender, $from),
            'body' => $body === '' ? null : $body,
        ]);
    }

    /**
     * The job this message is about.
     *
     * The plus-tag is authoritative: it is what we put in the Reply-To, and it
     * survives the round trip. The subject is a fallback for messages that were
     * forwarded, or sent from a client that rewrote the recipient — the
     * reference is already in every subject line we send, so it is usually
     * still there to find.
     */
    private static function jobFor(string $to, string $subject): ?Job
    {
        $reference = ReplyAddress::reference($to);

        if ($reference === null && $subject !== ''
            && preg_match('/('.Job::REFERENCE_PATTERN.')/i', $subject, $matches)) {
            $reference = strtoupper($matches[1]);
        }

        return $reference === null ? null : Job::where('reference', $reference)->first();
    }

    /**
     * Who wrote it, if we know them.
     *
     * Only the job's own customer or someone on the shop's staff is attributed.
     * A stranger who guessed a reference gets their words recorded with no name
     * attached, which is the difference between "the customer said" and "an
     * email arrived saying".
     */
    private static function sender(Job $job, string $from): ?User
    {
        if ($from === '') {
            return null;
        }

        // Case-insensitive because mail headers are not normalised: the same
        // person writes Maria@ and maria@ within a week.
        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($from)])->first();

        if ($user === null) {
            return null;
        }

        if ($user->id === $job->user_id) {
            return $user;
        }

        return $job->shop?->staff()->where('users.id', $user->id)->exists() ? $user : null;
    }

    private static function summary(?User $sender, string $from): string
    {
        $who = $sender?->name ?? ($from !== '' ? $from : null);

        return Str::limit($who === null ? 'Reply by email' : 'Reply by email from '.$who, 250);
    }

    /**
     * A stable key for "this exact message".
     *
     * The provider's Message-Id is the right key when it is present. When it is
     * not, hash the message's own content: a retry resends the same bytes, so
     * the same hash comes out and the second copy is recognised.
     */
    private static function fingerprint(array $payload, string $from, string $subject, string $body): string
    {
        $messageId = self::first($payload, ['Message-Id', 'message_id', 'MessageID', 'messageId', 'message-id']);

        return sha1($messageId !== '' ? $messageId : $from.'|'.$subject.'|'.$body);
    }

    /**
     * The readable text of the message.
     *
     * Plain text when the provider parsed it for us, otherwise the HTML part
     * with the markup taken off. Both are capped: an inbound endpoint that will
     * store an unbounded body is a way to fill a disk from the outside.
     */
    private static function body(array $payload): string
    {
        $text = self::first($payload, ['body-plain', 'body_plain', 'TextBody', 'text', 'stripped-text', 'body']);

        if ($text === '') {
            $html = self::first($payload, ['body-html', 'body_html', 'HtmlBody', 'html']);
            $text = $html === '' ? '' : self::plain($html);
        }

        // `?:` rather than a default argument: `INBOUND_MAX_BODY=` in a .env
        // file yields null, and a zero limit would store every reply as an
        // empty string.
        $limit = (int) config('services.inbound.max_body') ?: self::MAX_BODY;

        return Str::limit(trim($text), $limit, '…');
    }

    /** Enough of a tag-strip that a reply reads as prose, not as a page source. */
    private static function plain(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</(p|div|tr|li|h[1-6]|blockquote)>#i', "\n", $html) ?? $html;

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Block markup leaves a trail of blank lines and trailing spaces.
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /** First non-empty string among several spellings of the same field. */
    private static function first(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /** `"Maria <maria@example.com>"` → `maria@example.com`. */
    private static function email(string $value): string
    {
        if (preg_match('/<([^>]+)>/', $value, $matches)) {
            return trim($matches[1]);
        }

        return $value;
    }
}
