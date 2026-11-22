{{--
    The shop picker, for an owner who runs more than one shop.

    It renders nothing at all for the ordinary case of one shop, because a
    dropdown with a single option is a question with one answer and it would sit
    in the topbar of every shop owner in the country for no reason.

    A plain form post, not a Livewire call: switching shop changes which rows
    every widget on the page is allowed to see, so a full reload is the honest
    way to do it. Patching half a dashboard in place is how one shop's figures
    end up rendered under another shop's name.

    Rendered through the TOPBAR_END hook in AppServiceProvider, so the admin
    panel — where a user has no shops — never sees it.
--}}
@php
    $shopper = auth()->user();

    // Only trading shops: the panel refuses a suspended one at the door, so
    // listing it here would be an option that bounces the moment it is picked.
    $pickable = $shopper?->shops()->where('shops.status', 'active')->orderBy('shops.name')->get() ?? collect();
@endphp

@if ($pickable->count() > 1)
    <form method="POST" action="{{ route('filament.shop.switch-shop') }}" style="display:flex;align-items:center;margin:0 0 0 .5rem">
        @csrf
        <label for="shop-picker" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap">Switch shop</label>
        <select
            id="shop-picker"
            name="shop_id"
            onchange="this.form.submit()"
            title="Switch shop"
            style="height:2rem;max-width:14rem;padding:0 .5rem;border-radius:.5rem;border:1px solid var(--gray-200);background:#fff;color:var(--gray-700);font-size:.75rem;font-weight:600;cursor:pointer"
        >
            @foreach ($pickable as $option)
                <option value="{{ $option->id }}" @selected($option->id === $shopper->currentShopId())>
                    {{ $option->name }}
                </option>
            @endforeach
        </select>
    </form>
@endif
