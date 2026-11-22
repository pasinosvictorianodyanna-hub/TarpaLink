<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Proof;
use App\Models\User;
use App\Services\ProofExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The proof step used to exist only as a status on the board: no upload, no
 * artwork, no approval. These tests hold the exchange to the two things that
 * matter — the versioned file is what the customer actually saw, and only that
 * customer can decide whether it goes on press.
 */
class ProofExchangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * A quoted Inkwell job belonging to the seeded customer jomar.
     *
     * `quoted → awaiting_proof` is the workflow's proof step. References are
     * generated randomly by the seeder, so find one by its relationships.
     */
    private function job(): Job
    {
        return Job::where('status', 'quoted')
            ->where('user_id', $this->customer()->id)
            ->whereHas('shop', fn ($q) => $q->where('slug', 'inkwell-press'))
            ->firstOrFail();
    }

    private function customer(): User
    {
        return User::where('email', 'jomar@tarpalink.ph')->firstOrFail();
    }

    private function send(Job $job): Proof
    {
        return app(ProofExchange::class)->send($job, 'proofs/v1-test.png', 'Check the spelling.');
    }

    public function test_sending_a_proof_moves_the_job_to_awaiting_proof(): void
    {
        $job = $this->job();

        $proof = $this->send($job);

        $this->assertSame(1, $proof->version);
        $this->assertSame('sent', $proof->status);
        $this->assertSame('proofs/v1-test.png', $proof->file_path);
        $this->assertSame('awaiting_proof', $job->fresh()->status);
        $this->assertTrue(
            $job->activities()->where('to_status', 'awaiting_proof')->exists(),
            'The proof send should be recorded as a workflow transition'
        );
    }

    public function test_a_rejected_proof_can_be_replaced_by_a_new_version_without_another_transition(): void
    {
        $job = $this->job();
        $first = $this->send($job);
        $first->update(['status' => 'rejected']);

        $second = app(ProofExchange::class)->send($job, 'proofs/v2-test.png');

        $this->assertSame(2, $second->version);
        $this->assertSame('sent', $second->status);
        $this->assertSame('awaiting_proof', $job->fresh()->status);
        $this->assertSame(1, $first->fresh()->version, 'The rejected proof must stay in history');
    }

    public function test_the_customer_can_approve_and_the_job_goes_back_to_approved(): void
    {
        $job = $this->job();
        $proof = $this->send($job);

        $this->actingAs($this->customer())
            ->post(route('jobs.proof.approve', [$job, $proof]))
            ->assertRedirect();

        $this->assertSame('approved', $proof->fresh()->status);
        $this->assertNotNull($proof->fresh()->approved_at);
        $this->assertSame('approved', $job->fresh()->status);
    }

    public function test_another_customer_cannot_approve_someones_proof(): void
    {
        $job = $this->job();
        $proof = $this->send($job);
        $other = User::where('email', 'renz@example.com')->firstOrFail();

        $this->actingAs($other)
            ->post(route('jobs.proof.approve', [$job, $proof]))
            ->assertNotFound();

        $this->assertSame('sent', $proof->fresh()->status);
        $this->assertSame('awaiting_proof', $job->fresh()->status);
    }

    public function test_rejecting_a_proof_leaves_the_job_waiting_for_a_new_version(): void
    {
        $job = $this->job();
        $proof = $this->send($job);

        $this->actingAs($this->customer())
            ->post(route('jobs.proof.reject', [$job, $proof]), ['note' => 'Wrong size on the mockup.'])
            ->assertRedirect();

        $this->assertSame('rejected', $proof->fresh()->status);
        $this->assertSame('Wrong size on the mockup.', $proof->fresh()->note);
        $this->assertSame('awaiting_proof', $job->fresh()->status);
    }

    public function test_the_job_page_offers_the_decision_to_the_customer_who_owns_it(): void
    {
        $job = $this->job();
        $this->send($job);

        $this->actingAs($this->customer())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Approve proof')
            ->assertSee('Request changes')
            ->assertSee(route('jobs.proof.file', [$job, $job->fresh()->latestProof()]), false);
    }

    public function test_the_artwork_is_served_only_to_the_job_parties(): void
    {
        Storage::disk(Proof::DISK)->put('proofs/v1-test.png', 'fake-artwork');

        $job = $this->job();
        $proof = $this->send($job);
        $url = route('jobs.proof.file', [$job, $proof]);

        $this->actingAs($this->customer())->get($url)->assertOk();

        $owner = User::where('email', 'kevin@inkwell.ph')->firstOrFail();
        $this->actingAs($owner)->get($url)->assertOk();

        $other = User::where('email', 'renz@example.com')->firstOrFail();
        $this->actingAs($other)->get($url)->assertNotFound();

        Storage::disk(Proof::DISK)->delete('proofs/v1-test.png');
    }

    /**
     * The disk is a configuration choice, not a fact about the code.
     *
     * Proofs used to be pinned to `local`, which meant moving artwork to object
     * storage was a code change. This proves the read path follows the disk the
     * configuration names — so setting PROOF_DISK_DRIVER=s3 really does move the
     * files, rather than leaving the app looking in the old place.
     */
    public function test_proof_artwork_is_read_from_whichever_disk_the_configuration_names(): void
    {
        $root = storage_path('framework/testing/proofs-'.uniqid());
        File::ensureDirectoryExists($root);

        config(['filesystems.disks.proofs.root' => $root]);
        Storage::forgetDisk(Proof::DISK);

        Storage::disk(Proof::DISK)->put('proofs/moved.png', 'fake-artwork');

        $job = $this->job();
        $proof = app(ProofExchange::class)->send($job, 'proofs/moved.png');

        $this->actingAs($this->customer())
            ->get(route('jobs.proof.file', [$job, $proof]))
            ->assertOk();

        // And nothing was written to the disk the code used to hardcode.
        $this->assertFileDoesNotExist(storage_path('app/private/proofs/moved.png'));

        File::deleteDirectory($root);
    }

    public function test_a_proof_row_whose_file_is_missing_returns_404_rather_than_500(): void
    {
        $job = $this->job();

        // The seeded proofs point at a sample file that does not exist on disk.
        $proof = app(ProofExchange::class)->send($job, 'proofs/never-uploaded.png');

        $this->actingAs($this->customer())
            ->get(route('jobs.proof.file', [$job, $proof]))
            ->assertNotFound();
    }
}
