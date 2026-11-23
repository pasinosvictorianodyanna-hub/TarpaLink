<?php

namespace App\Http\Controllers;

use App\Models\Artwork;
use App\Models\Job;
use App\Services\ArtworkIntake;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The design file the customer sends the shop to print.
 *
 * The mirror image of `ProofController`: same private-disk rule, opposite
 * direction. A proof is written by the shop and read by the customer; an artwork
 * is written by the customer and read by the shop. Both are served only through
 * these routes, because a guessable URL is not an access check.
 */
class ArtworkController extends Controller
{
    /**
     * What we accept, and why not more.
     *
     * PNG, JPEG, WebP and PDF cover what a tarpaulin or flyer is actually
     * supplied as, and all four can be opened by whoever checks the file before
     * printing. Illustrator and PSD are deliberately absent: they are opaque
     * binaries that no shop on this platform is obliged to own software for, and
     * accepting a format the shop cannot open is worse than refusing it at the
     * door with a message that says what to send instead.
     */
    private const RULES = [
        'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:20480'],
    ];

    public function store(Request $request, Job $job): RedirectResponse
    {
        // Seeing a job and sending its artwork are different rights: a shop may
        // look at the job it is printing, but only the customer who ordered it
        // decides what gets printed.
        abort_unless(Gate::allows('view', $job), 404);
        abort_unless($request->user()?->id === $job->user_id, 403);

        // Past the proof the file is locked — the page promises the customer
        // that "what goes on press is what you signed off", and a file that
        // could change after approval would make that sentence false.
        abort_unless(
            $job->acceptsArtwork(),
            422,
            'The artwork is locked once you approve a proof. Ask the shop for a new proof if something needs changing.',
        );

        $request->validate(self::RULES, [
            'file.mimes' => 'Send a PNG, JPEG, WebP or PDF. Export your design to PDF if you work in another program.',
            'file.max' => 'That file is over 20 MB. Export it at a lower resolution, or send the shop a link in the notes.',
        ]);

        $artwork = app(ArtworkIntake::class)->receive($job, $request->file('file'), $request->user());

        return redirect()
            ->route('jobs.show', $job)
            ->with('status', $artwork->original_name.' sent to '.$job->shop->name.'. They will send a proof before printing.');
    }

    /** The file itself, for the customer who sent it, the shop printing it, or an admin. */
    public function file(Request $request, Job $job, Artwork $artwork): StreamedResponse
    {
        abort_unless(Gate::allows('view', $job), 404);
        abort_unless($artwork->job_id === $job->id, 404);
        abort_unless($artwork->exists(), 404);

        // `response()` with a filename streams it as an attachment — a download
        // rather than a new tab — because the shop is going to open it in its
        // own software, not read it in the browser.
        return Storage::disk(Artwork::DISK)->download(
            $artwork->file_path,
            $artwork->original_name,
        );
    }
}
