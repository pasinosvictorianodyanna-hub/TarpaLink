<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Proof extends Model
{
    /**
     * Where proof artwork lives.
     *
     * Named here rather than written out at each call site so the read path and
     * the write path cannot end up pointing at different disks — a mistake that
     * would present as "the proof was uploaded but cannot be opened", with the
     * file sitting somewhere nobody thinks to look.
     */
    public const DISK = 'proofs';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** The artwork is on a private disk; this is the only sanctioned way to ask. */
    public function exists(): bool
    {
        return Storage::disk(self::DISK)->exists($this->file_path);
    }
}
