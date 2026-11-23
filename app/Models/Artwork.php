<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A design file the customer sent the shop to print.
 *
 * The counterpart of `Proof`, and deliberately a separate table: a proof is the
 * shop saying "this is what I am about to print", an artwork is the customer
 * saying "this is what I want printed". They have different authors, different
 * permissions and different lifetimes, and a single table with a direction
 * column would have to be filtered on every read.
 */
class Artwork extends Model
{
    /**
     * Where customer artwork lives.
     *
     * A private disk for the same reason proofs use one: this is somebody's
     * design, often unreleased, and a guessable URL is not an access check.
     * Every read goes through a route that asks the policy first.
     */
    public const DISK = 'artwork';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['bytes' => 'integer'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** The artwork is on a private disk; this is the only sanctioned way to ask. */
    public function exists(): bool
    {
        return Storage::disk(self::DISK)->exists($this->file_path);
    }

    /** "1.4 MB" — what a customer recognises, not a byte count. */
    public function sizeLabel(): string
    {
        $bytes = max(0, (int) $this->bytes);

        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0).' KB',
            default => $bytes.' B',
        };
    }

    /** A filename that fits on one line without hiding the extension. */
    public function shortName(int $limit = 42): string
    {
        $name = $this->original_name;

        if (mb_strlen($name) <= $limit) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $stem = pathinfo($name, PATHINFO_FILENAME);
        $keep = max(6, $limit - mb_strlen($extension) - 2);

        return mb_substr($stem, 0, $keep).'….'.$extension;
    }
}
