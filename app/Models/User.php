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

    /** Memoised for the request — the shop panel asks on every resource query. */
    protected ?Shop $activeShopCache = null;

    protected bool $activeShopResolved = false;

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
     * The shop this user operates, resolved from the shop_users pivot.
     *
     * Only active shops count: a pending or suspended shop must not hand its
     * owner a working panel. See docs/ISSUES.md #3 and #8.
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
     * Panel access is decided here, not by a second guard.
     *
     * There is one `web` guard and one users table; a shop owner is an ordinary
     * user row plus a pivot entry.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isAdmin(),
            'shop' => $this->isShopOwner() && $this->activeShop() !== null,
            default => false,
        };
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
