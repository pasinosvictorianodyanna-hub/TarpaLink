<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Putting an upload on the disk, or failing in a way the form can show.
 *
 * Both file disks are configured with `throw => false`, so a write that fails —
 * a bucket that is unreachable, credentials that have expired, a full quota —
 * comes back from `store()` as `false` instead of an exception. Every caller
 * then has to remember to check, and none of them did: `ProofExchange::send()`
 * takes a `string`, so `false` became a TypeError and a 500, while the artwork
 * path wrote the row anyway and left the job page promising a file that does
 * not exist.
 *
 * So the check lives in one place. A failure is raised as a validation error on
 * `file`, which is the field both forms already report on, which turns a 500
 * into "that did not save, try again" without inventing a new error surface.
 *
 * The message deliberately does not name the cause: the customer cannot act on
 * "UnableToWriteFile at artwork/9f2…", and the detail is in the log already.
 */
final class Uploads
{
    /**
     * @param  string  $directory  the prefix inside the disk, e.g. `artwork`
     * @param  string  $disk  a disk name from config/filesystems.php
     */
    public static function store(UploadedFile $file, string $directory, string $disk): string
    {
        $path = $file->store($directory, $disk);

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'file' => 'That file could not be stored just now, so nothing was saved. Try again in a moment.',
            ]);
        }

        return $path;
    }
}
