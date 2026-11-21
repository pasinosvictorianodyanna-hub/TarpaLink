<x-filament-panels::page>
    @include('filament.shop.partials.theme')

    <style>
        .rp-wrap { margin-top: 4px; }
        .rp-stats {
            display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px; margin-bottom: 16px;
        }
        @media (max-width: 900px) { .rp-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

        .rp-card { margin-bottom: 16px; }
        .rp-split { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.45fr); gap: 16px; }
        @media (max-width: 1100px) { .rp-split { grid-template-columns: 1fr; } }
    </style>

    <div class="tl-wrap rp-wrap">
        @php($t = $this->totals())

        <div class="rp-stats">
            <div class="tl-stat">
                <span class="lbl">Gross taken</span>
                <span class="val">{{ $this->money($t['gross']) }}</span>
                <div class="sub">{{ number_format($t['jobs']) }} paid jobs</div>
            </div>
            <div class="tl-stat">
                <span class="lbl">Platform commission</span>
                <span class="val">{{ $this->money($t['commission']) }}</span>
                <div class="sub">
                    @if ($t['gross'] > 0)
                        {{ rtrim(rtrim(number_format($t['commission'] / $t['gross'] * 100, 2), '0'), '.') }}% effective
                    @else
                        no revenue yet
                    @endif
                </div>
            </div>
            <div class="tl-stat">
                <span class="lbl">Net to shop</span>
                <span class="val">{{ $this->money($t['net']) }}</span>
                <div class="sub">gross minus commission</div>
            </div>
            <div class="tl-stat is-held">
                <span class="lbl">Held for you</span>
                <span class="val">{{ $this->money($t['awaiting']) }}</span>
                <div class="sub">paid, not yet released</div>
            </div>
        </div>

        <div class="tl-card rp-card">
            <header>
                <h3>Revenue, last 8 weeks</h3>
                <span class="meta">This month: <strong>{{ $this->money($this->monthTotal()) }}</strong></span>
            </header>

            <div class="tl-chart">
                @foreach ($this->weekly() as $week)
                    <div class="tl-col {{ $week['current'] ? 'is-current' : '' }}">
                        <div class="tl-track">
                            <div class="tl-bar" style="height: {{ max($week['pct'], 2) }}%"></div>
                        </div>
                        <div class="tl-amt">{{ $week['gross'] > 0 ? \App\Support\Money::short($week['gross']) : '—' }}</div>
                        <div class="tl-lbl">{{ $week['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rp-split">
            <div class="tl-card">
                <header><h3>Top services by revenue</h3></header>

                @php($services = $this->topServices())

                @if ($services->isEmpty())
                    <div class="tl-empty">No settled revenue yet.</div>
                @else
                    <table class="tl-tbl">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th class="num">Jobs</th>
                                <th class="num">Gross</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($services as $service)
                                <tr>
                                    <td>{{ $service->name }}</td>
                                    <td class="num">{{ number_format($service->jobs) }}</td>
                                    <td class="num">{{ $this->money((int) $service->gross) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="tl-card">
                <header>
                    <h3>Payout ledger</h3>
                    <span class="meta">gross − commission = net</span>
                </header>

                @php($ledger = $this->ledger())

                @if ($ledger->isEmpty())
                    <div class="tl-empty">Nothing has been paid out yet.</div>
                @else
                    <div class="tl-scroll">
                        <table class="tl-tbl">
                            <thead>
                                <tr>
                                    <th>Paid</th>
                                    <th>Job</th>
                                    <th class="num">Gross</th>
                                    <th class="num">Fee</th>
                                    <th class="num">Net</th>
                                    <th>Payout</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ledger as $payment)
                                    <tr>
                                        <td>{{ $payment->paid_at?->format('d M Y') ?? '—' }}</td>
                                        <td class="mono">{{ $payment->job?->reference ?? '—' }}</td>
                                        <td class="num">{{ $this->money($payment->amount) }}</td>
                                        <td class="num" style="color:var(--tl-muted)">−{{ $this->money($payment->commission) }}</td>
                                        <td class="num"><strong>{{ $this->money($payment->net()) }}</strong></td>
                                        <td>
                                            @if ($payment->isSettled())
                                                <span class="tl-pill paid">Released</span>
                                                <span style="font-size:11px;color:var(--tl-muted);margin-left:6px">{{ $payment->payout_date?->format('d M') }}</span>
                                            @else
                                                <span class="tl-pill pending">Held</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
