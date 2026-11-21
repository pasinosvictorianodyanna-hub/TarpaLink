@include('filament.shop.partials.theme')

<x-filament-panels::page>
    <div class="tl-wrap" style="display:grid;gap:14px">

        {{-- Service tabs --}}
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @foreach ($this->services() as $service)
                <button
                    type="button"
                    wire:click="selectService({{ $service->id }})"
                    style="height:34px;padding:0 14px;border-radius:999px;font-size:12px;font-weight:600;cursor:pointer;
                        border:1px solid {{ $this->active()?->id === $service->id ? 'var(--tl-ink)' : 'var(--tl-rule)' }};
                        background:{{ $this->active()?->id === $service->id ? 'var(--tl-ink)' : '#fff' }};
                        color:{{ $this->active()?->id === $service->id ? '#fff' : 'var(--tl-ink)' }}"
                >{{ $service->name }}</button>
            @endforeach
        </div>

        @php($service = $this->active())

        @if (! $service)
            <div class="tl-card"><div class="tl-empty">This shop has no services yet.</div></div>
        @else
            <div style="display:grid;grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr);gap:14px;align-items:start">

                {{-- Matrix --}}
                <div style="display:grid;gap:14px">
                    <div class="tl-card">
                        <header>
                            <h3>Size tiers</h3>
                            <span class="meta">{{ $service->unit === 'sqft' ? 'rate per sq ft' : 'rate per piece' }}</span>
                        </header>

                        <table class="tl-tbl">
                            <thead>
                                <tr>
                                    <th>Tier</th>
                                    <th>Size range</th>
                                    <th style="text-align:right">Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($service->tiers->sortBy('sort') as $tier)
                                    <tr>
                                        <td style="font-weight:500">{{ $tier->label }}</td>
                                        <td style="color:var(--tl-muted);font-variant-numeric:tabular-nums">
                                            {{ (float) $tier->min_width }}–{{ $tier->max_width ? (float) $tier->max_width : '∞' }} in wide
                                        </td>
                                        <td class="tl-amt">
                                            {{ \App\Support\Money::format($tier->unit_price) }}
                                            <span style="font-size:11px;color:var(--tl-muted)">/{{ $service->unit === 'sqft' ? 'sq ft' : 'pc' }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3"><div class="tl-empty">No tiers set — customers cannot be quoted this service.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="tl-card">
                        <header>
                            <h3>Finishing add-ons</h3>
                            <span class="meta">{{ $service->options->count() }} options</span>
                        </header>

                        @if ($service->options->isEmpty())
                            <div class="tl-empty">No add-ons. Customers can only choose size and quantity.</div>
                        @else
                            <table class="tl-tbl">
                                <thead>
                                    <tr>
                                        <th>Option</th>
                                        <th>Charged</th>
                                        <th style="text-align:right">Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($service->options->sortBy('sort') as $option)
                                        <tr>
                                            <td style="font-weight:500">{{ $option->name }}</td>
                                            <td style="color:var(--tl-muted)">per {{ $option->unit === 'sqft' ? 'sq ft' : ($option->unit === 'ft' ? 'foot' : 'piece') }}</td>
                                            <td class="tl-amt">{{ \App\Support\Money::format($option->price) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>

                    <div style="font-size:12px;color:var(--tl-muted)">
                        Editing a rate lives in
                        <a
                            href="{{ \App\Filament\Shop\Resources\Services\ServiceResource::getUrl('edit', ['record' => $service]) }}"
                            style="text-decoration:underline;text-underline-offset:2px;font-weight:600;color:var(--tl-ink)"
                        >Services → {{ $service->name }}</a>.
                    </div>
                </div>

                {{-- Live preview --}}
                <div class="tl-card">
                    <header>
                        <h3>Customer sees</h3>
                        <span class="meta">live preview</span>
                    </header>

                    <div style="padding:0 16px 12px;display:flex;gap:8px;flex-wrap:wrap">
                        <label style="font-size:11px;color:var(--tl-muted)">
                            Width (in)
                            <input type="number" min="1" wire:model.live.debounce.400ms="width"
                                   style="display:block;width:82px;height:32px;margin-top:3px;padding:0 8px;border:1px solid var(--tl-rule);border-radius:8px;font-size:12px">
                        </label>
                        <label style="font-size:11px;color:var(--tl-muted)">
                            Height (in)
                            <input type="number" min="1" wire:model.live.debounce.400ms="height"
                                   style="display:block;width:82px;height:32px;margin-top:3px;padding:0 8px;border:1px solid var(--tl-rule);border-radius:8px;font-size:12px">
                        </label>
                        <label style="font-size:11px;color:var(--tl-muted)">
                            Qty
                            <input type="number" min="1" wire:model.live.debounce.400ms="qty"
                                   style="display:block;width:70px;height:32px;margin-top:3px;padding:0 8px;border:1px solid var(--tl-rule);border-radius:8px;font-size:12px">
                        </label>
                    </div>

                    @php($quote = $this->preview())

                    @if (isset($quote['error']))
                        <div style="padding:0 16px 16px;color:var(--tl-bad);font-size:12.5px">{{ $quote['error'] }}</div>
                    @else
                        <table class="tl-tbl">
                            <tbody>
                                @foreach ($quote['lines'] as $line)
                                    <tr>
                                        <td>
                                            <div style="font-weight:500">{{ $line['label'] }}</div>
                                            <div style="font-size:11px;color:var(--tl-muted)">
                                                {{ $line['qty'] }} × {{ \App\Support\Money::format($line['unit_price']) }}
                                            </div>
                                        </td>
                                        <td class="tl-amt">{{ \App\Support\Money::format($line['amount']) }}</td>
                                    </tr>
                                @endforeach

                                @if (($quote['discount'] ?? 0) > 0)
                                    <tr>
                                        <td style="color:var(--tl-good)">Bulk discount</td>
                                        <td class="tl-amt" style="color:var(--tl-good)">−{{ \App\Support\Money::format($quote['discount']) }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>

                        <div style="display:flex;justify-content:space-between;align-items:baseline;padding:12px 16px;border-top:1px solid var(--tl-rule)">
                            <span style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--tl-muted)">Total</span>
                            <strong style="font-size:1.35rem;font-variant-numeric:tabular-nums">{{ \App\Support\Money::format($quote['total']) }}</strong>
                        </div>

                        <div style="padding:0 16px 16px;font-size:11.5px;color:var(--tl-muted)">
                            {{ $quote['tier'] }} · matrix v{{ $quote['matrix_version'] }} — the same breakdown the customer sees before checkout.
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
