<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One provider's saved credentials.
 *
 * The whole blob is encrypted with `APP_KEY`, so a database dump is not a set
 * of live keys. `encrypted:array` rather than a column per field: the fields
 * differ by provider and there are only ever a handful of rows.
 */
class Integration extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'encrypted:array'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
