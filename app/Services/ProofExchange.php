<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Proof;
use App\Models\User;

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

        return $proof;
    }
}
