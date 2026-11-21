<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription tier.
 *
 * The platform charges shops two ways at once: a flat monthly fee and a
 * percentage of each job. The percentage is the part that matters to a shop's
 * economics, so both numbers live here and the crossover math is derived, never
 * typed in twice.
 */
class Plan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'monthly_fee' => 'integer',
            'commission_rate' => 'integer',
            'sort' => 'integer',
        ];
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

    public function feeLabel(): string
    {
        return $this->monthly_fee === 0
            ? 'Free'
            : '₱'.number_format($this->monthly_fee / 100, 0);
    }

    /** What a shop on this plan pays in a month with the given GMV (centavos). */
    public function monthlyCost(int $gmv): int
    {
        return $this->monthly_fee + (int) round($gmv * $this->commission_rate / 10000);
    }

    /**
     * The GMV at which this plan starts beating $other, in centavos.
     *
     * Null when it never does — either $other is cheaper on both axes, or the two
     * plans are identical. Growth vs Starter: 599.00 / 2% = ₱29,950.
     */
    public function crossoverAgainst(Plan $other): ?int
    {
        $rateGap = $other->commission_rate - $this->commission_rate;

        if ($rateGap <= 0) {
            return null;
        }

        $feeGap = $this->monthly_fee - $other->monthly_fee;

        if ($feeGap <= 0) {
            return 0;
        }

        return (int) round($feeGap * 10000 / $rateGap);
    }
}
