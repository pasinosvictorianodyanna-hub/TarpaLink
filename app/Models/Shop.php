<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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
            'offers_delivery' => 'boolean',
            'offers_design' => 'boolean',
            'accepts_gcash' => 'boolean',
            'turnaround_hours' => 'integer',
            'commission_rate' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
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

    /**
     * Every payment this shop has taken, reached through its jobs.
     *
     * Reports and the payout ledger both need this, and neither should have to
     * remember to join jobs and filter by shop_id by hand.
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Job::class);
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

    /** The administrator who let this shop in — kept for the audit trail. */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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

    /**
     * The point distances are measured from: Quezon City hall.
     *
     * The hero's location pill defaults to Quezon City, so "4.2 km" on a card
     * means "4.2 km from the city you are browsing", not from the customer's GPS.
     * Until the directory takes a real origin, this is the only defensible one.
     */
    public const ORIGIN_LAT = 14.6760;

    public const ORIGIN_LNG = 121.0437;

    /**
     * Straight-line kilometres from the marketplace origin, or null when the
     * shop has no coordinates.
     *
     * Haversine rather than a flat approximation: at Metro Manila's latitude the
     * difference is small but it costs one line, and a wrong distance is exactly
     * the kind of detail a customer notices.
     */
    public function distanceKm(): ?float
    {
        if ($this->lat === null || $this->lng === null) {
            return null;
        }

        $earthKm = 6371.0;
        $dLat = deg2rad($this->lat - self::ORIGIN_LAT);
        $dLng = deg2rad($this->lng - self::ORIGIN_LNG);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad(self::ORIGIN_LAT)) * cos(deg2rad($this->lat)) * sin($dLng / 2) ** 2;

        return round($earthKm * 2 * asin(min(1.0, sqrt($a))), 1);
    }

    /** "4.2 km", or the city when there is no coordinate to measure. */
    public function distanceLabel(): string
    {
        $km = $this->distanceKm();

        return $km === null ? $this->city : number_format($km, 1).' km';
    }

    /** The unit as a person reads it: `sqft` is "sq ft", not "sqft". */
    public function cheapestUnitLabel(): string
    {
        return $this->cheapestUnit() === 'sqft' ? 'sq ft' : 'pc';
    }

    /** "Same day", "24 hours", "2–3 days" — the buckets the filter rail offers. */
    public function turnaroundLabel(): string
    {
        return match (true) {
            $this->turnaround_hours <= 8 => 'Same day',
            $this->turnaround_hours <= 24 => '24 hours',
            default => '2–3 days',
        };
    }

    /**
     * The badges on a shop card. Only what the shop actually claims: a badge
     * that is true of every shop is decoration, and decoration lies.
     *
     * Ordered rarest-claim-first, because the card shows one badge and the first
     * one has to be the thing that distinguishes this shop from the next. Bulk
     * pricing sits last: every shop in the catalogue offers a quantity discount,
     * so leading with it would put the same badge on every card.
     *
     * @return array<int, array{label: string, tone: string}>
     */
    public function badges(): array
    {
        return array_values(array_filter([
            $this->accepts_rush ? ['label' => '24h rush', 'tone' => 'g'] : null,
            $this->offers_design ? ['label' => 'Design help', 'tone' => 'm'] : null,
            $this->offers_delivery ? ['label' => 'Delivery', 'tone' => 'n'] : null,
            $this->quantityDiscounts->isNotEmpty() ? ['label' => 'Bulk orders', 'tone' => 'c'] : null,
        ]));
    }
}
