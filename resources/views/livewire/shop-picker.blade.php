{{--
    The shop picker, for an owner who runs more than one shop.

    It renders nothing for the ordinary case of one shop, because a dropdown with
    a single option is a question with one answer and it would sit in the topbar
    of every shop owner in the country for no reason.

    The wrapper is always present and `display:contents`, because a Livewire
    component must render exactly one root element — an empty component throws.
    `contents` keeps it out of the flex layout, so an owner with one shop sees no
    gap where the picker would be.

    Mounted through the TOPBAR_END hook in AppServiceProvider, so the admin panel
    — where a user has no shops — never sees the control inside it.
--}}
<div wire:key="shop-picker" style="display:contents">
    @if ($pickable->count() > 1)
        <div style="display:flex;align-items:center;margin:0 0 0 .5rem">
            <label for="shop-picker" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap">
                Switch shop
            </label>

            <select
                id="shop-picker"
                wire:model.live="shopId"
                wire:loading.attr="disabled"
                title="Switch shop"
                style="height:2rem;max-width:14rem;padding:0 .5rem;border-radius:.5rem;border:1px solid var(--gray-200);background:#fff;color:var(--gray-700);font-size:.75rem;font-weight:600;cursor:pointer"
            >
                @foreach ($pickable as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>

            <span wire:loading wire:target="shopId" style="margin-left:.4rem;font-size:.7rem;color:var(--gray-500)">
                Switching…
            </span>
        </div>
    @endif
</div>

