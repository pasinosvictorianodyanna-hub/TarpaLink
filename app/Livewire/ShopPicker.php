<?php

namespace App\Livewire;

use App\Models\Shop;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Which shop the panel is looking at.
 *
 * The choice lives in the session and is re-checked on every read by
 * `User::currentShop()`, so this component's only job is to make the choice.
 *
 * It still reloads the page after switching. That is not laziness: every widget,
 * table and figure on the page is scoped to the current shop, and patching them
 * all in place is exactly what a reload does — with more ways to leave one shop's
 * numbers rendered under another shop's name. What changed is the plumbing: this
 * is a Livewire component like the rest of the panel, so there is no hand-written
 * form, no manual CSRF token, and a refused switch explains itself instead of
 * landing on a 403 page.
 */
class ShopPicker extends Component
{
    public ?int $shopId = null;

    /** Where to go back to after switching, captured while rendering the page. */
    public string $returnTo = '';

    public function mount(): void
    {
        $this->shopId = auth()->user()?->currentShopId();
        $this->returnTo = request()->fullUrl();
    }

    public function updatedShopId(): void
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        // One query answers both halves: is this shop theirs, and is it trading?
        // The session is a preference, never a permission — a posted shop id is
        // user input like any other.
        $target = $user->shops()
            ->whereKey($this->shopId)
            ->where('shops.status', 'active')
            ->first();

        if ($target === null) {
            $this->shopId = $user->currentShopId();

            Notification::make()
                ->title('You cannot switch to that shop')
                ->body('It is either not yours or not trading.')
                ->danger()
                ->send();

            return;
        }

        session()->put('shop_id', $target->id);

        $this->redirect($this->safeReturnTo());
    }

    /**
     * `returnTo` round-trips through the browser, so it is user input by the time
     * it comes back. Only a URL on this host is followed — otherwise this would
     * be an open redirect dressed up as a convenience.
     */
    private function safeReturnTo(): string
    {
        $host = parse_url($this->returnTo, PHP_URL_HOST);

        if ($host !== null && $host === request()->getHost()) {
            return $this->returnTo;
        }

        return url('/shop');
    }

    public function render(): View
    {
        return view('livewire.shop-picker', [
            'pickable' => $this->pickable(),
        ]);
    }

    /** @return Collection<int, Shop> */
    private function pickable(): Collection
    {
        $user = auth()->user();

        // Only trading shops: the panel refuses a suspended one at the door, so
        // listing it would be an option that bounces the moment it is picked.
        return $user?->shops()
            ->where('shops.status', 'active')
            ->orderBy('shops.name')
            ->get() ?? collect();
    }
}
