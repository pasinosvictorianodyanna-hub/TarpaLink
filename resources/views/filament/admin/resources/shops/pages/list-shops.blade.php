{{-- The tl-* tokens are shared by both panels; the partial lives under shop/ because that panel needed them first. --}}
@include('filament.shop.partials.theme')

<x-filament-panels::page>
    <div class="tl-wrap" style="display:grid;gap:14px">

        <div class="tl-card" style="padding:14px 16px">
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="q"
                    placeholder="Search shop, owner or city"
                    style="flex:1;min-width:220px;height:36px;padding:0 12px;border:1px solid var(--tl-rule);border-radius:999px;background:#fff;font-size:12.5px;color:var(--tl-ink)"
                >

                <select wire:model.live="status"
                        style="height:36px;padding:0 10px;border:1px solid var(--tl-rule);border-radius:999px;background:#fff;font-size:12px">
                    @foreach ($this->statusOptions() as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select wire:model.live="city"
                        style="height:36px;padding:0 10px;border:1px solid var(--tl-rule);border-radius:999px;background:#fff;font-size:12px">
                    <option value="">All cities</option>
                    @foreach ($this->cityOptions() as $city)
                        <option value="{{ $city }}">{{ $city }}</option>
                    @endforeach
                </select>

                @if ($this->hasFilters())
                    <button type="button" wire:click="clearFilters"
                            style="height:36px;padding:0 12px;border:1px solid var(--tl-rule);border-radius:999px;background:none;font-size:12px;font-weight:600;cursor:pointer">
                        Clear
                    </button>
                @endif

                <span class="meta" style="margin-left:auto">{{ $this->total() }} {{ $this->total() === 1 ? 'shop' : 'shops' }}</span>
            </div>
        </div>

        <div class="tl-card">
            @php($shops = $this->shops())

            @if ($shops->isEmpty())
                <div class="tl-empty">No shops match these filters.</div>
            @else
                <table class="tl-tbl">
                    <thead>
                        <tr>
                            <th>Shop</th>
                            <th>Owner</th>
                            <th>City</th>
                            <th style="text-align:right">Services</th>
                            <th style="text-align:right">Jobs</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($shops as $shop)
                            <tr>
                                <td>
                                    <a href="{{ \App\Filament\Admin\Resources\Shops\ShopResource::getUrl('view', ['record' => $shop]) }}"
                                       style="font-weight:600;text-decoration:underline;text-underline-offset:2px">{{ $shop->name }}</a>
                                    <div style="font-size:11px;color:var(--tl-muted)">{{ $shop->slug }}</div>
                                </td>

                                <td>
                                    @php($owner = $this->ownerOf($shop))
                                    <div style="font-weight:500">{{ $owner?->name ?? '—' }}</div>
                                    <div style="font-size:11px;color:var(--tl-muted)">{{ $owner?->email }}</div>
                                </td>

                                <td>{{ $shop->city }}</td>
                                <td class="tl-amt">{{ $shop->services_count }}</td>
                                <td class="tl-amt">{{ $shop->jobs_count }}</td>

                                <td>
                                    <span class="tl-pill {{ $this->pillFor($shop->status) }}">{{ ucfirst($shop->status) }}</span>
                                </td>

                                <td style="text-align:right;white-space:nowrap">
                                    @if ($shop->status === 'pending')
                                        <button
                                            type="button"
                                            wire:click="approve({{ $shop->id }})"
                                            wire:loading.attr="disabled"
                                            style="height:28px;padding:0 12px;border-radius:999px;border:1px solid var(--tl-good);background:var(--tl-good);color:#fff;font-size:11.5px;font-weight:600;cursor:pointer"
                                        >Approve</button>
                                    @else
                                        <a href="{{ \App\Filament\Admin\Resources\Shops\ShopResource::getUrl('view', ['record' => $shop]) }}"
                                           style="font-size:11.5px;font-weight:600;text-decoration:underline;text-underline-offset:2px">View</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div style="padding:12px 16px;border-top:1px solid var(--tl-rule)">
                    {{ $shops->links() }}
                </div>
            @endif
        </div>

        <div style="font-size:12px;color:var(--tl-muted)">
            Rejecting or suspending a shop needs a reason the owner will read, so those actions live on the shop's page.
        </div>
    </div>
</x-filament-panels::page>
