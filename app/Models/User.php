<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Resolved once per request; the pivot query is not free. */
    private ?Shop $resolvedShop = null;

    private bool $shopResolved = false;

    /** @var array<int, int>|null Every shop this user is attached to, cached per request. */
    private ?array $resolvedShopIds = null;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'city',
        'role',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class)->latest();
    }

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_users')->withPivot('role');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isShopOwner(): bool
    {
        return $this->role === 'shop_owner';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Every shop this user is attached to, as ids.
     *
     * Policies ask this on nearly every check, so it is resolved once per
     * request rather than per row — otherwise a 20-row table costs 20 queries
     * just to decide whether to show an edit button.
     *
     * @return array<int, int>
     */
    public function shopIds(): array
    {
        return $this->resolvedShopIds ??= $this->shops()->pluck('shops.id')->all();
    }

    public function belongsToShop(int|Shop|null $shop): bool
    {
        if ($shop === null) {
            return false;
        }

        return in_array($shop instanceof Shop ? $shop->id : $shop, $this->shopIds(), true);
    }

    /** Owner of this particular shop — not merely staff. */
    public function isOwnerOf(int|Shop|null $shop): bool
    {
        if (! $this->belongsToShop($shop)) {
            return false;
        }

        $id = $shop instanceof Shop ? $shop->id : $shop;

        return $this->shops()
            ->whereKey($id)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    /**
     * Panel access. Both panels share the single `web` guard, so the only thing
     * separating an admin from a shop owner is this method — not a second guard,
     * and not a second users table.
     *
     * A shop owner whose shop is pending or suspended gets nothing: admission is
     * checked here rather than inside each page, so a new panel page can never
     * forget to check it.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isAdmin(),
            'shop' => $this->isShopOwner() && $this->currentShop()?->isActive() === true,
            default => false,
        };
    }

    /**
     * The shop this user operates. One owner can be attached to several shops
     * through the pivot; the panel works on the first one.
     */
    public function currentShop(): ?Shop
    {
        if (! $this->shopResolved) {
            $this->resolvedShop = $this->shops()->orderBy('shops.id')->first();
            $this->shopResolved = true;
        }

        return $this->resolvedShop;
    }

    public function currentShopId(): ?int
    {
        return $this->currentShop()?->id;
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
