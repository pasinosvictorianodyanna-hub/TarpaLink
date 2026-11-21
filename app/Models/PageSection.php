<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageSection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    /** Content for a section, merged over a default so a missing key never breaks the view. */
    public static function content(string $key, array $defaults = []): array
    {
        $row = static::where('key', $key)->where('status', 'published')->first();

        return array_merge($defaults, $row?->content ?? []);
    }

    public static function visible(string $key): bool
    {
        return static::where('key', $key)->where('status', 'published')->exists();
    }
}
