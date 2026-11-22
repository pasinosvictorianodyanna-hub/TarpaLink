<?php

namespace App\Services;

use App\Mail\JobNudged;
use App\Models\Job;
use App\Models\JobActivity;
use App\Models\User;
use App\Support\Notifier;

/**
 * Chasing a customer about a job that is waiting on them.
 *
 * The record and the email are two separate promises, and the order matters: the
 * nudge is written to the job's activity trail first, so it exists even when the
 * mail server is unreachable. The email is best-effort through `Notifier`, and
 * the caller gets a plain boolean so it can tell the owner which of the two
 * actually happened instead of claiming a send that never left.
 *
 * This lived inside a Filament widget. It is here because the widget is gone and
 * the console needed the same behaviour — and because a rule about what a nudge
 * records should not be reachable only through one screen.
 */
final class JobNudge
{
    /**
     * Record the nudge, then try to email it.
     *
     * Returns true only when the mail was actually handed to the mailer.
     */
    public function send(Job $job, ?User $by = null): bool
    {
        $job->activities()->create([
            'user_id' => $by?->id ?? auth()->id(),
            'kind' => JobActivity::NOTE,
            'from_status' => null,
            'to_status' => null,
            'note' => 'Shop nudged the customer',
        ]);

        $customer = $job->user;

        if (blank($customer?->email)) {
            return false;
        }

        return Notifier::send($customer->email, new JobNudged($job, $customer->name));
    }
}
