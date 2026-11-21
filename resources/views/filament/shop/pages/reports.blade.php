<x-filament-panels::page>
    <style>
        .rp-wrap {
            --rp-paper: #F5F3ED;
            --rp-rule: #E2DED2;
            --rp-ink: #0C0F0E;
            --rp-muted: #6B6F6C;
            --rp-card: #FFFFFF;
            --rp-bar: #0C0F0E;
            --rp-good: #0F7A4F;
            --rp-wait: #B26A00;
        }
        .dark .rp-wrap {
            --rp-paper: #1C1F1E;
            --rp-rule: #2E3331;
            --rp-ink: #F2F0EA;
            --rp-muted: #A8AEAA;
            --rp-card: #242827;
            --rp-bar: #F2F0EA;
            --rp-good: #6EE7A8;
            --rp-wait: #F3C969;
        }

        .rp-stats {
            display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px; margin-bottom: 16px;
        }
        @media (max-width: 900px) { .rp-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

        .rp-stat {
            background: var(--rp-card); border: 1px solid var(--rp-rule);
            border-radius: 10px; padding: 14px 16px;
        }
        .rp-stat .lbl {
            font-size: 10.5px; letter-spacing: .14em; text-transform: uppercase;
            color: var(--rp-muted); font-weight: 600; display: block; margin-bottom: 4px;
        }
        .rp-stat .val { font-size: 22px; font-weight: 700; letter-spacing: -.02em; color: var(--rp-ink); }
        .rp-stat .sub { font-size: 11.5px; color: var(--rp-muted); margin-top: 2px; }
        .rp-stat.is-held .val { color: var(--rp-wait); }

        .rp-card {
            background: var(--rp-card); border: 1px solid var(--rp-rule);
            border-radius: 10px; overflow: hidden; margin-bottom: 16px;
        }
        .rp-card > header {
            display: flex; flex-wrap: wrap; gap: 10px; align-items: baseline;
            justify-content: space-between; padding: 13px 16px;
            border-bottom: 1px solid var(--rp-rule);
        }
        .rp-card h3 { margin: 0; font-size: 13px; font-weight: 650; color: var(--rp-ink); }
        .rp-card header .meta { font-size: 11.5px; color: var(--rp-muted); }

        .rp-chart {
            display: flex; align-items: flex-end; gap: 10px;
            padding: 18px 16px 12px; height: 190px;
        }
        .rp-col { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; align-items: center; height: 100%; }
        .rp-track { flex: 1; width: 100%; display: flex; align-items: flex-end; justify-content: center; }
        .rp-bar {
            width: 68%; max-width: 54px; min-height: 3px;
            background: var(--rp-bar); border-radius: 4px 4px 2px 2px; opacity: .82;
        }
        .rp-col.is-current .rp-bar { opacity: 1; }
        .rp-col.is-current .rp-lbl { color: var(--rp-ink); font-weight: 650; }
        .rp-amt { font-size: 10.5px; color: var(--rp-muted); margin-top: 6px; white-space: nowrap; }
        .rp-lbl { font-size: 10.5px; color: var(--rp-muted); margin-top: 1px; white-space: nowrap; }

        .rp-split { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.45fr); gap: 16px; }
        @media (max-width: 1100px) { .rp-split { grid-template-columns: 1fr; } }

        table.rp-tbl { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        table.rp-tbl th {
            text-align: left; font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase;
            color: var(--rp-muted); font-weight: 600; padding: 9px 16px;
            border-bottom: 1px solid var(--rp-rule);
        }
        table.rp-tbl td { padding: 9px 16px; border-bottom: 1px solid var(--rp-rule); color: var(--rp-ink); }
        table.rp-tbl tr:last-child td { border-bottom: 0; }
        table.rp-tbl td.num, table.rp-tbl th.num { text-align: right; font-variant-numeric: tabular-nums; }
        table.rp-tbl td.mono { font-family: ui-monospace, monospace; font-size: 11.5px; }

        .rp-pill {
            display: inline-block; font-size: 10.5px; font-weight: 650; letter-spacing: .04em;
            text-transform: uppercase; padding: 2px 7px; border-radius: 999px;
            border: 1px solid currentColor;
        }
        .rp-pill.paid { color: var(--rp-good); }
        .rp-pill.pending { color: var(--rp-wait); }

        .rp-empty { padding: 20px 16px; font-size: 12.5px; color: var(--rp-muted); }

        .rp-scroll { max-height: 380px; overflow-y: auto; }
    </style>

    <div class="rp-wrap">
        @php($t = $this->totals())

        <div class="rp-stats">
            <div class="rp-stat">
                <span class="lbl">Gross taken</span>
                <span class="val">{{ $this->money($t['gross']) }}</span>
                <div class="sub">{{ number_format($t['jobs']) }} paid jobs</div>
            </div>
            <div class="rp-stat">
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
            <div class="rp-stat">
                <span class="lbl">Net to shop</span>
                <span class="val">{{ $this->money($t['net']) }}</span>
                <div class="sub">gross minus commission</div>
            </div>
            <div class="rp-stat is-held">
                <span class="lbl">Held for you</span>
                <span class="val">{{ $this->money($t['awaiting']) }}</span>
                <div class="sub">paid, not yet released</div>
            </div>
        </div>

        <div class="rp-card">
            <header>
                <h3>Revenue, last 8 weeks</h3>
                <span class="meta">This month: <strong>{{ $this->money($this->monthTotal()) }}</strong></span>
            </header>

            <div class="rp-chart">
                @foreach ($this->weekly() as $week)
                    <div class="rp-col {{ $week['current'] ? 'is-current' : '' }}">
                        <div class="rp-track">
                            <div class="rp-bar" style="height: {{ max($week['pct'], 2) }}%"></div>
                        </div>
                        <div class="rp-amt">{{ $week['gross'] > 0 ? \App\Support\Money::short($week['gross']) : '—' }}</div>
                        <div class="rp-lbl">{{ $week['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rp-split">
            <div class="rp-card">
                <header><h3>Top services by revenue</h3></header>

                @php($services = $this->topServices())

                @if ($services->isEmpty())
                    <div class="rp-empty">No settled revenue yet.</div>
                @else
                    <table class="rp-tbl">
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

            <div class="rp-card">
                <header>
                    <h3>Payout ledger</h3>
                    <span class="meta">gross − commission = net</span>
                </header>

                @php($ledger = $this->ledger())

                @if ($ledger->isEmpty())
                    <div class="rp-empty">Nothing has been paid out yet.</div>
                @else
                    <div class="rp-scroll">
                        <table class="rp-tbl">
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
                                        <td class="num" style="color:var(--rp-muted)">−{{ $this->money($payment->commission) }}</td>
                                        <td class="num"><strong>{{ $this->money($payment->net()) }}</strong></td>
                                        <td>
                                            @if ($payment->isSettled())
                                                <span class="rp-pill paid">Released</span>
                                                <span style="font-size:11px;color:var(--rp-muted);margin-left:6px">{{ $payment->payout_date?->format('d M') }}</span>
                                            @else
                                                <span class="rp-pill pending">Held</span>
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
