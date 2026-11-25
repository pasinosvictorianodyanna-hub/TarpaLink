<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\Job;
use App\Models\Proof;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Mockery;
use Tests\TestCase;

/**
 * The two private disks, and what happens when one of them cannot be reached.
 *
 * Every other test in the suite runs on a local or faked disk, so the deployment
 * can break in a way the whole suite stays green about: it did, when a disk was
 * set to `s3` and the Flysystem S3 adapter was never required in composer.json.
 * That produced a 500 on both uploads and downloads and no test saw it. The
 * first test builds the real driver with fake credentials — no network needed —
 * so a missing package cannot slip through again.
 *
 * The other two hold the other half of the fix: both disks say `throw => false`,
 * which turns a failed write into `false`. Recording that `false` either made a
 * TypeError out of the proof and a 500 out of the page, or recorded an artwork
 * row pointing at a file nobody could open. Both forms now refuse the request
 * as a validation error on the file field, which the forms already show.
 */
class StorageDiskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function customer(): User
    {
        return User::where('email', 'jomar@tarpalink.ph')->firstOrFail();
    }

    private function owner(): User
    {
        return User::where('email', 'kevin@inkwell.ph')->firstOrFail();
    }

    private function shop(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    private function quoted(): Job
    {
        return Job::where('user_id', $this->customer()->id)
            ->where('shop_id', $this->shop()->id)
            ->where('status', 'quoted')
            ->firstOrFail();
    }

    /** A disk that refuses every write, the way a bucket that is down does. */
    private function refusingDisk(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->andReturn(false);

        Storage::shouldReceive('disk')->andReturn($disk);
    }

    public function test_the_s3_driver_builds_for_both_private_disks(): void
    {
        $settings = [
            'driver' => 's3',
            'key' => 'fake-key',
            'secret' => 'fake-secret',
            'region' => 'auto',
            'bucket' => 'a-bucket',
            // R2 needs an explicit endpoint and path-style addressing. Fake
            // values are enough: building a client makes no request.
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => false,
        ];

        foreach ([Artwork::DISK, Proof::DISK] as $name) {
            config()->set("filesystems.disks.{$name}", $settings);
            // The manager memoises disks, and the seeder may already have
            // resolved the local one. Drop the cached instance before asking
            // for the configured driver.
            Storage::forgetDisk($name);

            $adapter = Storage::disk($name)->getAdapter();

            $this->assertInstanceOf(
                AwsS3V3Adapter::class,
                $adapter,
                "{$name} must be able to build the S3 driver on the deployment"
            );
        }
    }

    public function test_a_design_that_cannot_be_stored_is_refused_and_not_recorded(): void
    {
        $job = $this->quoted();
        $this->refusingDisk();

        $this->actingAs($this->customer())
            ->from(route('jobs.show', $job))
            ->post(route('jobs.artwork.store', $job), [
                'file' => UploadedFile::fake()->create('poster.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $job->fresh()->artworks()->count());
        $this->assertSame(0, $job->fresh()->activities()->whereNotNull('note')->count());
    }

    public function test_a_proof_that_cannot_be_stored_is_refused_and_not_recorded(): void
    {
        $job = $this->quoted();
        $this->refusingDisk();

        $this->actingAs($this->owner())
            ->from(route('console.jobs.show', $job))
            ->post(route('console.jobs.proof', $job), [
                'file' => UploadedFile::fake()->create('proof.png', 20, 'image/png'),
            ])
            ->assertRedirect(route('console.jobs.show', $job))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $job->fresh()->proofs()->count());
        // The status must not move: no proof file means nothing was approved.
        $this->assertSame('quoted', $job->fresh()->status);
    }
}
