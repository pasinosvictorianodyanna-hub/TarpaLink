<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription tier. The rate lives here as the canonical value; `shops`
 * carries a copy of it so a payout never has to join to price a commission.
 */
class Plan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'commission_rate' => 'integer',
            'features' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class, 'plan', 'code');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function commissionPct(): float
    {
        return $this->commission_rate / 100;
    }

    public function commissionLabel(): string
    {
        return rtrim(rtrim(number_format($this->commissionPct(), 2, '.', ''), '0'), '.').'%';
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }

    /**
     * Monthly GMV, in centavos, above which this plan costs less than $lower.
     *
     * Solve  price + gmv × rate  =  lower.price + gmv × lower.rate
     *   →    gmv = (price − lower.price) / (lower.rate − rate)
     *
     * Returns null when this plan can never beat the lower one (its rate is not
     * actually lower), and 0 when it is cheaper on both axes.
     */
    public function crossoverFrom(?Plan $lower): ?int
    {
        if (! $lower) {
            return null;
        }

        $rateDelta = ($lower->commission_rate - $this->commission_rate) / 10000;

        if ($rateDelta <= 0) {
            return null;
        }

        $priceDelta = $this->price - $lower->price;

        return $priceDelta <= 0 ? 0 : (int) ceil($priceDelta / $rateDelta);
    }

    /** The plan immediately below this one, by sort order. */
    public function lowerPlan(): ?Plan
    {
        return static::where('sort', '<', $this->sort)->orderByDesc('sort')->first();
    }
}
