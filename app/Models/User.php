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

    private ?Shop $activeShopCache = null;

    private bool $activeShopResolved = false;

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

    /**
     * Panel access. Both panels share the single `web` guard, so the only thing
     * separating an admin from a shop owner is this method — not a second guard,
     * and not a second users table.
     *
     * The shop panel additionally requires an *active* shop: a pending or
     * suspended shop must not hand its owner a working panel. See #3 and #8.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isAdmin(),
            'shop' => $this->isShopOwner() && $this->activeShop() !== null,
            default => false,
        };
    }

    /**
     * The shop this user operates. One owner can be attached to several shops
     * through the pivot; the panel works on the first one.
     *
     * Deliberately status-agnostic — resource scoping uses this. Whether the
     * owner may be in the panel at all is canAccessPanel()'s question, and that
     * one is answered by activeShop().
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

    /**
     * The first shop this user operates that is actually active.
     *
     * Null means "no panel for you": pending applications and suspended shops
     * both land here, which is what makes rejection stick.
     */
    public function activeShop(): ?Shop
    {
        if ($this->activeShopResolved) {
            return $this->activeShopCache;
        }

        $this->activeShopResolved = true;

        return $this->activeShopCache = $this->shops()
            ->where('shops.status', 'active')
            ->first();
    }

    /**
     * Does this user operate the given shop?
     *
     * Used by policies that hold a shop id but not a Shop instance — proofs,
     * job lines, activity rows. Deliberately status-aware: a suspended shop is
     * not somewhere you still have authority.
     */
    public function belongsToShop(?int $shopId): bool
    {
        return $shopId !== null && $this->activeShop()?->id === $shopId;
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
