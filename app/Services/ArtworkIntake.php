<?php

namespace App\Services;

use App\Mail\ArtworkSent;
use App\Models\Artwork;
use App\Models\Job;
use App\Models\JobActivity;
use App\Models\User;
use App\Support\Notifier;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;

/**
 * Taking delivery of the file a customer wants printed.
 *
 * The upload is the point where the order becomes something a shop can actually
 * make, so it is a service rather than a few lines in a controller: the activity
 * note, the replacement count and the notification are the same rules whichever
 * screen calls it, and a test should not have to fake a request to check them.
 *
 * Nothing here changes the job's status. `quoted` already means "ordered, not
 * yet on the press", and a job with artwork and a job without are both quoted
 * until the shop sends a proof — inventing an `artwork_received` status would
 * add a state to every switch in the system to say something the file list
 * already says.
 */
final class ArtworkIntake
{
    public function receive(Job $job, UploadedFile $file, ?User $by = null): Artwork
    {
        // The original name, type and size are recorded because the stored path
        // is a random key: without them the shop sees `xJ2f9a…` and cannot tell
        // the logo from the poster.
        $artwork = $job->artworks()->create([
            'uploaded_by' => $by?->id,
            // `Uploads::store()` refuses a write that failed rather than
            // returning the `false` the disk hands back, because a row here
            // would tell the customer their design had arrived when there is
            // nothing on the bucket for the shop to print.
            'file_path' => Uploads::store($file, 'artwork', Artwork::DISK),
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType() ?: 'application/octet-stream',
            'bytes' => (int) $file->getSize(),
        ]);

        $replacement = $job->artworks()->count() > 1;

        $job->activities()->create([
            'user_id' => $by?->id,
            'kind' => JobActivity::NOTE,
            'note' => ($replacement ? 'Replacement artwork sent: ' : 'Artwork sent: ')
                .$artwork->original_name.' ('.$artwork->sizeLabel().')',
        ]);

        $this->tellTheShop($job, $artwork, $by);

        return $artwork;
    }

    /**
     * The shop has no reason to refresh the queue, so it is told.
     *
     * Owners first, then any staff member — the same fallback the proof
     * notification uses, so a shop with no owner row is still reachable.
     */
    private function tellTheShop(Job $job, Artwork $artwork, ?User $by): void
    {
        $shop = $job->shop;

        if ($shop === null) {
            return;
        }

        $recipient = $shop->owners()->first() ?? $shop->staff()->first();

        if ($recipient === null) {
            return;
        }

        Notifier::send($recipient->email, new ArtworkSent(
            job: $job->fresh(),
            artwork: $artwork,
            customerName: $by?->name ?? $job->contact_name ?? 'The customer',
            shopName: $shop->name,
        ));
    }
}
