@extends('layouts.console')

@section('page')
    @php($t = $totals)

    <div class="fil-stats">
        <div class="fil-stat">
            <div class="l">Gross taken</div>
            <div class="v">{{ \App\Support\Money::short($t['gross']) }}</div>
            <div class="d">{{ number_format($t['jobs']) }} paid {{ $t['jobs'] === 1 ? 'job' : 'jobs' }}</div>
        </div>
        <div class="fil-stat">
            <div class="l">Platform commission</div>
            <div class="v">{{ \App\Support\Money::short($t['commission'] - $t['commission_refunded']) }}</div>
            <div class="d">
                @if ($t['gross'] > 0)
                    {{ rtrim(rtrim(number_format(($t['commission'] - $t['commission_refunded']) / $t['gross'] * 100, 2), '0'), '.') }}% effective
                    @if ($t['commission_refunded'] > 0)
                        · {{ \App\Support\Money::short($t['commission_refunded']) }} reversed
                    @endif
                @else
                    no revenue yet
                @endif
            </div>
        </div>
        <div class="fil-stat">
            <div class="l">Net to shop</div>
            <div class="v">{{ \App\Support\Money::short($t['net']) }}</div>
            <div class="d">
                @if ($t['refunded'] > 0)
                    after {{ \App\Support\Money::short($t['refunded']) }} refunded
                @else
                    gross minus commission and fees
                @endif
            </div>
        </div>
        <div class="fil-stat">
            <div class="l">Held for you</div>
            <div class="v">{{ \App\Support\Money::short($t['awaiting']) }}</div>
            <div class="d">net, not yet released</div>
        </div>
    </div>

    <div class="fil-card" style="margin-bottom:16px">
        <div class="fil-toolbar">
            <b style="font-size:12.5px">Revenue, last 8 weeks</b>
            <span style="margin-left:auto;font-size:11px;color:#6B7280">This month: <strong style="color:#111827">{{ \App\Support\Money::format($month) }}</strong></span>
        </div>

        <div style="padding:14px 16px">
            <div class="bars" style="height:110px">
                @foreach ($weekly as $week)
                    <div class="b {{ $week['current'] ? 'hi' : '' }}" style="height: {{ max($week['pct'], 2) }}%"></div>
                @endforeach
            </div>
            <div class="bar-x">
                @foreach ($weekly as $week)
                    <span>{{ $week['label'] }}</span>
                @endforeach
            </div>
        </div>
    </div>

    <div class="fil-cols two">
        <div class="fil-card">
            <div class="fil-toolbar"><b style="font-size:12.5px">Top services by revenue</b></div>

            @if ($services->isEmpty())
                <div style="padding:16px;font-size:12.5px;color:#6B7280">No settled revenue yet.</div>
            @else
                <div class="ftbl-wrap">
                    <table class="ftbl">
                        <thead><tr><th>Service</th><th class="r">Jobs</th><th class="r">Gross</th></tr></thead>
                        <tbody>
                            @foreach ($services as $service)
                                <tr>
                                    <td>{{ $service->name }}</td>
                                    <td class="r" style="font-variant-numeric:tabular-nums">{{ number_format($service->jobs) }}</td>
                                    <td class="r" style="font-variant-numeric:tabular-nums">{{ \App\Support\Money::format((int) $service->gross) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="fil-card">
            <div class="fil-toolbar">
                <b style="font-size:12.5px">Payout ledger</b>
                <span style="margin-left:auto;font-size:11px;color:#6B7280">gross − refunded − commission − fee = net</span>
            </div>

            @if ($ledger->isEmpty())
                <div style="padding:16px;font-size:12.5px;color:#6B7280">Nothing has been paid out yet.</div>
            @else
                <div class="ftbl-wrap">
                    <table class="ftbl">
                        <thead>
                            <tr>
                                <th>Paid</th>
                                <th>Job</th>
                                <th class="r">Gross</th>
                                <th class="r">Refunded</th>
                                <th class="r">Commission</th>
                                <th class="r">Fee</th>
                                <th class="r">Net</th>
                                <th>Payout</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ledger as $payment)
                                <tr>
                                    <td style="white-space:nowrap">{{ $payment->paid_at?->format('d M Y') ?? '—' }}</td>
                                    <td class="t">{{ $payment->job?->reference ?? '—' }}</td>
                                    <td class="r" style="font-variant-numeric:tabular-nums">{{ \App\Support\Money::format($payment->amount) }}</td>
                                    <td class="r" style="font-variant-numeric:tabular-nums">
                                        @if ($payment->hasRefund())
                                            <span style="color:#B42318">−{{ \App\Support\Money::format($payment->refunded_amount) }}</span>
                                        @else
                                            <span style="color:#9CA3AF">—</span>
                                        @endif
                                    </td>
                                    @php($kept = $payment->commission - $payment->commission_refunded)
                                    <td class="r" style="color:#6B7280;font-variant-numeric:tabular-nums">
                                        {{-- A fully refunded payment reverses the whole
                                             commission; "−₱0.00" reads as a typo. --}}
                                        {{ $kept > 0 ? '−'.\App\Support\Money::format($kept) : '—' }}
                                    </td>
                                    <td class="r" style="color:#6B7280;font-variant-numeric:tabular-nums">
                                        {{ $payment->processing_fee > 0 ? '−'.\App\Support\Money::format($payment->processing_fee) : '—' }}
                                    </td>
                                    <td class="r" style="font-variant-numeric:tabular-nums">
                                        <strong>{{ \App\Support\Money::signed($payment->net()) }}</strong>
                                    </td>
                                    <td style="white-space:nowrap">
                                        @if ($payment->isFullyRefunded())
                                            <span class="fb fb-r">Refunded</span>
                                        @elseif ($payment->isSettled())
                                            <span class="fb fb-g">Released</span>
                                            <span style="font-size:11px;color:#6B7280;margin-left:5px">{{ $payment->payout_date?->format('d M') }}</span>
                                        @else
                                            <span class="fb fb-y">Held</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="fil-foot">
                    <span>Most recent {{ $ledger->count() }} payments — the CSV carries the full ledger.</span>
                    <a class="fbtn fbtn-ghost" href="{{ route('console.reports.export') }}">Export CSV</a>
                </div>
            @endif
        </div>
    </div>
@endsection
