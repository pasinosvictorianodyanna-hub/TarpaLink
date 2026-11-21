<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shop extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'lat' => 'float',
            'lng' => 'float',
            'accepts_rush' => 'boolean',
            'commission_rate' => 'integer',
        ];
    }

    /** Take rate as a percentage: 300 bp → 3.0. */
    public function commissionPct(): float
    {
        return $this->commission_rate / 100;
    }

    /** Human label for the panel: 300 → "3%", 150 → "1.5%". */
    public function commissionLabel(): string
    {
        return rtrim(rtrim(number_format($this->commissionPct(), 2, '.', ''), '0'), '.').'%';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('sort');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function quantityDiscounts(): HasMany
    {
        return $this->hasMany(QuantityDiscount::class);
    }

    /**
     * The plan row behind the `plan` code column.
     *
     * Deliberately not named `plan()`: `$shop->plan` is the code string, and a
     * relation with the same name would silently swap the attribute for a model
     * the moment anything eager-loaded it.
     */
    public function planDefinition(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan', 'code');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->latest();
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest();
    }

    public function currentPlan(): ?Plan
    {
        return $this->planDefinition;
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shop_users')->withPivot('role');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Entry price shown on the directory card.
     *
     * Deliberately scoped to the shop's primary (first) service so the number and
     * the unit always belong to the same service — mixing a flyer rate with a
     * per-square-foot label is how directories end up lying.
     */
    public function cheapestRate(): ?int
    {
        return $this->services->first()?->tiers->min('unit_price');
    }

    public function cheapestUnit(): string
    {
        return $this->services->first()?->unit ?? 'pc';
    }

    /** Cover gradient for the shop card, driven by the accent chosen in the panel. */
    public function coverGradient(): string
    {
        return match ($this->accent) {
            'magenta' => 'linear-gradient(140deg,#E8265E,#8C0F36)',
            'cyan' => 'linear-gradient(140deg,#0092E4,#064A73)',
            'yellow' => 'linear-gradient(140deg,#E09B00,#7C5200)',
            default => 'linear-gradient(140deg,#0C0F0E,#2C3A36)',
        };
    }
}
