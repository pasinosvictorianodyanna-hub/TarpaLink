@include('filament.shop.partials.theme')

{{-- The wrapper is what turns `$columnSpan` into a grid class: a custom widget
     view that renders a bare div is laid out as one column no matter what the
     class declares. --}}
<x-filament-widgets::widget>
    <div class="tl-wrap">
        <div class="tl-card">
            <header>
                <h3>Due today</h3>
                <span class="meta">{{ $count }} {{ $count === 1 ? 'job' : 'jobs' }}</span>
            </header>

            @if ($jobs->isEmpty())
                <div class="tl-empty">Nothing has to leave the shop today.</div>
            @else
                <table class="tl-tbl">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Customer</th>
                            <th>Spec</th>
                            <th>Status</th>
                            <th class="num">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($jobs as $job)
                            <tr>
                                <td class="mono">{{ $job->reference }}</td>
                                <td>{{ $job->user?->name ?? '—' }}</td>
                                <td>
                                    <div>{{ $job->service?->name }} {{ $job->sizeLabel() }}</div>
                                    <div style="font-size:11px;color:var(--tl-muted)">{{ $job->specSummary() }}</div>
                                </td>
                                <td><span class="tl-pill {{ $job->statusTone() }}">{{ $job->statusLabel() }}</span></td>
                                <td class="num">{{ \App\Support\Money::short($job->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
