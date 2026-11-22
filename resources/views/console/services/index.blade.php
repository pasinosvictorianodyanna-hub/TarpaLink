@extends('layouts.console')

@section('page')
    <div class="fil-card">
        <div class="ftbl-wrap">
            <table class="ftbl">
                <thead>
                    <tr><th>Service</th><th>Priced</th><th>Tiers</th><th>Add-ons</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($services as $service)
                        <tr>
                            <td>
                                <a class="ftbl-link t" href="{{ route('console.services.edit', $service) }}">{{ $service->name }}</a>
                                <div class="sub">/{{ $service->slug }}</div>
                            </td>
                            <td style="color:#6B7280">{{ $service->unit === 'sqft' ? 'per square foot' : 'per piece' }}</td>
                            <td style="font-variant-numeric:tabular-nums">
                                @if ($service->tiers_count === 0)
                                    <span class="fb fb-r">none — not quotable</span>
                                @else
                                    {{ $service->tiers_count }}
                                @endif
                            </td>
                            <td style="font-variant-numeric:tabular-nums">{{ $service->options_count }}</td>
                            <td>
                                @if ($service->active)
                                    <span class="fb fb-g">live</span>
                                @else
                                    <span class="fb fb-n">hidden</span>
                                @endif
                            </td>
                            <td class="r"><a class="fb fb-y" href="{{ route('console.services.edit', $service) }}">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="color:#6B7280">No services yet. A shop with no services cannot take an order.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
