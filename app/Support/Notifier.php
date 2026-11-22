<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The one place a notification leaves the system.
 *
 * Mail is best-effort by design. A proof that was uploaded is uploaded whether
 * or not the mail server is reachable, and a payment that settled is settled
 * whether or not a receipt went out. Letting an SMTP timeout throw would turn a
 * working workflow into a failed request, and worse, would do it inside a queued
 * webhook job where the retry would re-run the money logic for a mail problem.
 *
 * So failures are reported and swallowed, and callers get a plain boolean they
 * can use to adjust what they tell the user — the same posture the shop's
 * "nudge" already took.
 */
final class Notifier
{
    public static function send(?string $to, Mailable $mail): bool
    {
        if (blank($to)) {
            return false;
        }

        try {
            Mail::to($to)->send($mail);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
