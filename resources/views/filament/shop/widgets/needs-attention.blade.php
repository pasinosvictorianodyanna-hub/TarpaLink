@include('filament.shop.partials.theme')

<x-filament-widgets::widget>
    <div class="tl-wrap">
        <div class="tl-card">
            <header>
                <h3>Needs attention</h3>
                <span class="meta">{{ count($rows) }} {{ count($rows) === 1 ? 'job' : 'jobs' }}</span>
            </header>

            @if ($rows === [])
                <div class="tl-empty">Nothing is stuck — every job is moving or waiting on you.</div>
            @else
                <table class="tl-tbl">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Customer</th>
                            <th>Issue</th>
                            <th>Waiting</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="mono">{{ $row['job']->reference }}</td>
                                <td>{{ $row['job']->user?->name ?? '—' }}</td>
                                <td>{{ $row['issue'] }}</td>
                                <td style="color:var(--tl-wait);font-variant-numeric:tabular-nums">{{ $row['waiting'] }}</td>
                                <td class="num">
                                    <a
                                        href="{{ $row['url'] }}"
                                        style="font-size:11.5px;font-weight:600;color:var(--tl-ink);text-decoration:underline;text-underline-offset:2px;white-space:nowrap"
                                    >Open job</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
