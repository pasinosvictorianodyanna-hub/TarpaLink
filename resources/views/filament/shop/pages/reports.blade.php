<x-filament-panels::page>
    @php($peak = $this->peakWeek())

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
        <x-filament::section>
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;opacity:.6;">Captured revenue</div>
            <div style="font-size:1.75rem;font-weight:700;margin-top:.25rem;">{{ $this->money($totalRevenue) }}</div>
            <div style="font-size:.8rem;opacity:.6;">{{ $paidJobs }} paid {{ Str::plural('job', $paidJobs) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;opacity:.6;">Platform commission</div>
            <div style="font-size:1.75rem;font-weight:700;margin-top:.25rem;">{{ $this->money($totalCommission) }}</div>
            <div style="font-size:.8rem;opacity:.6;">Deducted from payouts, never added to the customer</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;opacity:.6;">Net earned</div>
            <div style="font-size:1.75rem;font-weight:700;margin-top:.25rem;">{{ $this->money($totalRevenue - $totalCommission) }}</div>
            <div style="font-size:.8rem;opacity:.6;">What the shop keeps</div>
        </x-filament::section>
    </div>

    <x-filament::section heading="Revenue by week" description="Last eight weeks, captured payments only.">
        <div style="display:flex;align-items:flex-end;gap:.75rem;height:200px;padding-top:.5rem;">
            @foreach ($weeks as $week)
                @php($height = max(2, (int) round($week['total'] / $peak * 100)))
                <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%;">
                    <div style="font-size:.7rem;opacity:.65;margin-bottom:.35rem;white-space:nowrap;">
                        {{ $week['total'] > 0 ? $this->money($week['total']) : '—' }}
                    </div>
                    <div
                        style="width:100%;border-radius:6px 6px 0 0;background:#0092E4;height:{{ $height }}%;min-height:3px;"
                        title="{{ $week['label'] }}: {{ $this->money($week['total']) }}"
                    ></div>
                    <div style="font-size:.7rem;opacity:.55;margin-top:.5rem;white-space:nowrap;">{{ $week['label'] }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section heading="Top services by revenue" description="Only jobs that were actually paid for.">
        @if (count($services) === 0)
            <div style="padding:1.5rem 0;opacity:.6;font-size:.9rem;">No paid jobs yet.</div>
        @else
            <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                <thead>
                    <tr style="text-align:left;opacity:.6;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">
                        <th style="padding:.5rem 0;">Service</th>
                        <th style="padding:.5rem 0;text-align:right;">Paid jobs</th>
                        <th style="padding:.5rem 0;text-align:right;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($services as $service)
                        <tr style="border-top:1px solid rgba(128,128,128,.2);">
                            <td style="padding:.6rem 0;font-weight:600;">{{ $service['name'] }}</td>
                            <td style="padding:.6rem 0;text-align:right;">{{ $service['jobs'] }}</td>
                            <td style="padding:.6rem 0;text-align:right;font-weight:600;">{{ $this->money($service['revenue']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
