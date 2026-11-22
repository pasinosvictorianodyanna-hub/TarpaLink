<?php

namespace App\Services;

use App\Mail\ProofSent;
use App\Models\Job;
use App\Models\Proof;
use App\Models\User;
use App\Support\Notifier;

/**
 * Sending artwork to a customer.
 *
 * The version number and the status move are rules, not form behaviour: they
 * are decided here so the Filament action, a test and any future screen all get
 * the same answer. Rejecting a proof leaves the job in `awaiting_proof`, so a
 * new upload reopens the same decision with a higher version instead of
 * rewriting what the customer already saw.
 */
final class ProofExchange
{
    public function send(Job $job, string $path, ?string $note = null, ?User $by = null): Proof
    {
        $version = (int) ($job->proofs()->max('version') ?? 0) + 1;

        $proof = $job->proofs()->create([
            'version' => $version,
            'file_path' => $path,
            'status' => 'sent',
            'note' => $note,
        ]);

        // JobWorkflow only permits quoted → awaiting_proof. A rejected proof
        // leaves the job in awaiting_proof, so sending version two is a file
        // change, not another status change.
        if ($job->status === 'quoted') {
            JobWorkflow::transition($job, 'awaiting_proof', $by, 'Proof v'.$version.' sent');
        }

        // Best-effort, and after the record exists: the customer has to find out
        // somehow, and "check the website" is not a notification. A mail failure
        // is reported inside `Notifier` and does not undo the upload.
        $customer = $job->user;

        if ($customer !== null) {
            Notifier::send($customer->email, new ProofSent(
                job: $job->fresh(),
                proof: $proof,
                customerName: $customer->name,
                shopName: $job->shop?->name ?? 'The shop',
            ));
        }

        return $proof;
    }
}
