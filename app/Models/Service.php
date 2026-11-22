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

    /**
     * A short, stable fingerprint of the matrix this service prices against.
     *
     * Changes whenever a tier's price or size band changes, so a historical job
     * can always be traced back to the exact price list the customer agreed to.
     *
     * It lives here rather than in `PriceCalculator` because it is a property of
     * the service, not of the quoting: the console shows it on a job, and a test
     * that wants to assert "the job recorded the matrix it was priced against"
     * has to be able to ask the service the same question the calculator asked.
     *
     * **The number is only meaningful within one installation.** It is built
     * from tier ids, which the database assigns, so the same price list produces
     * a different fingerprint on a different database. Never assert on a literal
     * value — assert that it changed, or that the job's copy matches this one.
     */
    public function matrixVersion(): int
    {
        $fingerprint = $this->tiers
            ->map(fn (PriceTier $tier) => $tier->id.':'.$tier->unit_price.':'.$tier->min_width.':'.$tier->max_width)
            ->implode('|');

        return crc32($fingerprint) % 10000;
    }
}
