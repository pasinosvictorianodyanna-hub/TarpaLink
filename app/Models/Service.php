<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $guarded = [];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(PriceTier::class)->orderBy('sort');
    }

    public function options(): HasMany
    {
        return $this->hasMany(PriceOption::class)->orderBy('sort');
    }

    /** The tier that covers a given size, or null when the size is out of range. */
    public function tierFor(float $width, float $height): ?PriceTier
    {
        return $this->tiers->first(function (PriceTier $tier) use ($width, $height) {
            $long = max($width, $height);

            return $long >= $tier->min_width
                && ($tier->max_width === null || $long <= $tier->max_width);
        });
    }
}
