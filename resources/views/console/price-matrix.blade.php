@extends('layouts.console')

@section('page')
    @if ($services->isEmpty())
        <div class="fil-card"><div style="padding:20px 16px;font-size:12.5px;color:#6B7280">This shop has no services yet.</div></div>
    @else
        <div style="display:flex;gap:7px;flex-wrap:wrap;margin-bottom:16px">
            @foreach ($services as $service)
                <a
                    class="fbtn {{ $active?->id === $service->id ? 'fbtn-primary' : 'fbtn-ghost' }}"
                    href="{{ route('console.prices', ['service' => $service->id, 'width' => $width, 'height' => $height, 'qty' => $qty]) }}"
                >{{ $service->name }}</a>
            @endforeach
        </div>

        <div class="fil-cols two">
            <div style="display:grid;gap:16px">
                <div class="fil-card">
                    <div class="fil-toolbar">
                        <b style="font-size:12.5px">Size tiers</b>
                        <span style="margin-left:auto;font-size:11px;color:#6B7280">{{ $active->unit === 'sqft' ? 'rate per sq ft' : 'rate per piece' }}</span>
                    </div>

                    <div class="ftbl-wrap">
                        <table class="ftbl">
                            <thead><tr><th>Tier</th><th>Size range</th><th class="r">Rate</th></tr></thead>
                            <tbody>
                                @forelse ($active->tiers->sortBy('sort') as $tier)
                                    <tr>
                                        <td style="font-weight:500">{{ $tier->label }}</td>
                                        <td style="color:#6B7280;font-variant-numeric:tabular-nums">
                                            {{ (float) $tier->min_width }}–{{ $tier->max_width ? (float) $tier->max_width : '∞' }} in wide
                                        </td>
                                        <td class="r" style="font-variant-numeric:tabular-nums">
                                            {{ \App\Support\Money::format($tier->unit_price) }}
                                            <span style="font-size:11px;color:#6B7280">/{{ $active->unit === 'sqft' ? 'sq ft' : 'pc' }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" style="color:#B42318">No tiers set — customers cannot be quoted this service.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="fil-card">
                    <div class="fil-toolbar">
                        <b style="font-size:12.5px">Finishing add-ons</b>
                        <span style="margin-left:auto;font-size:11px;color:#6B7280">{{ $active->options->count() }} options</span>
                    </div>

                    @if ($active->options->isEmpty())
                        <div style="padding:16px;font-size:12.5px;color:#6B7280">No add-ons. Customers can only choose size and quantity.</div>
                    @else
                        <div class="ftbl-wrap">
                            <table class="ftbl">
                                <thead><tr><th>Option</th><th>Charged</th><th class="r">Price</th></tr></thead>
                                <tbody>
                                    @foreach ($active->options->sortBy('sort') as $option)
                                        <tr>
                                            <td style="font-weight:500">{{ $option->name }}</td>
                                            <td style="color:#6B7280">per {{ $option->unit === 'sqft' ? 'sq ft' : ($option->unit === 'ft' ? 'foot' : 'piece') }}</td>
                                            <td class="r" style="font-variant-numeric:tabular-nums">{{ \App\Support\Money::format($option->price) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div style="font-size:12px;color:#6B7280">
                    Rates are edited in
                    <a href="{{ route('console.services.edit', $active) }}" style="text-decoration:underline;text-underline-offset:2px;font-weight:600;color:#111827">Services → {{ $active->name }}</a>.
                </div>
            </div>

            <div class="fil-card">
                <div class="fil-toolbar">
                    <b style="font-size:12.5px">Customer sees</b>
                    <span style="margin-left:auto;font-size:11px;color:#6B7280">live preview</span>
                </div>

                <form method="GET" action="{{ route('console.prices') }}" style="padding:12px 16px;display:flex;gap:8px;flex-wrap:wrap;border-bottom:1px solid #F3F4F6">
                    <input type="hidden" name="service" value="{{ $active->id }}">

                    @foreach ([['width', 'Width (in)', $width], ['height', 'Height (in)', $height], ['qty', 'Qty', $qty]] as [$field, $label, $value])
                        <label style="font-size:11px;color:#6B7280">
                            {{ $label }}
                            <input
                                type="number" min="1" name="{{ $field }}" value="{{ $value }}"
                                style="display:block;width:82px;height:32px;margin-top:3px;padding:0 8px;border:1px solid #E5E7EB;border-radius:7px;font:inherit;font-size:12px"
                            >
                        </label>
                    @endforeach

                    <button class="fbtn fbtn-ghost" type="submit" style="align-self:flex-end">Reprice</button>
                </form>

                @if ($error !== null)
                    <div style="padding:14px 16px;font-size:12.5px;color:#B42318">{{ $error }}</div>
                @elseif ($quote !== null)
                    <div class="ftbl-wrap">
                        <table class="ftbl">
                            <tbody>
                                @foreach ($quote['lines'] as $line)
                                    <tr>
                                        <td>
                                            <div style="font-weight:500">{{ $line['label'] }}</div>
                                            <div style="font-size:11px;color:#6B7280">
                                                {{ $line['qty'] }} × {{ \App\Support\Money::format($line['unit_price']) }}
                                            </div>
                                        </td>
                                        <td class="r" style="font-variant-numeric:tabular-nums">{{ \App\Support\Money::format($line['amount']) }}</td>
                                    </tr>
                                @endforeach

                                @if (($quote['discount'] ?? 0) > 0)
                                    <tr>
                                        <td style="color:#067647">Bulk discount</td>
                                        <td class="r" style="color:#067647;font-variant-numeric:tabular-nums">−{{ \App\Support\Money::format($quote['discount']) }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:baseline;padding:12px 16px;border-top:1px solid #F3F4F6">
                        <span style="font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;color:#6B7280">Total</span>
                        <strong style="font-size:18px;font-variant-numeric:tabular-nums">{{ \App\Support\Money::format($quote['total']) }}</strong>
                    </div>

                    <div style="padding:0 16px 16px;font-size:11.5px;color:#6B7280">
                        {{ $quote['tier'] }} · matrix v{{ $quote['matrix_version'] }} — the same breakdown the customer sees before checkout.
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
