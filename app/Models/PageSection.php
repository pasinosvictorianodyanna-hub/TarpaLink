<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

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

    /**
     * Where this section shows up on the storefront.
     *
     * The CMS list links each row here, so an editor can check their copy
     * against the real page instead of guessing which URL it feeds.
     */
    public function publicUrl(): string
    {
        if (str_starts_with($this->key, 'page.')) {
            $slug = substr($this->key, strlen('page.'));

            if (Route::has('page')) {
                return route('page', $slug);
            }
        }

        return route('home');
    }
}
