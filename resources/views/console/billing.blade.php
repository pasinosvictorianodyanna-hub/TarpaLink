@extends('layouts.console')

@section('page')
    <div class="fil-card" style="margin-bottom:16px;border-left:4px solid #111827">
        <div style="display:flex;flex-wrap:wrap;gap:28px;align-items:baseline;padding:16px 18px">
            @php
                $facts = [
                    ['Current plan', $current?->name ?? 'None'],
                    ['Commission', $current?->commissionLabel() ?? '—'],
                    ['Monthly fee', $current ? ($current->isFree() ? 'Free' : \App\Support\Money::format($current->price)) : '—'],
                    ['Applies to', 'the next payout only'],
                ];
            @endphp

            @foreach ($facts as [$label, $value])
                <div>
                    <div style="font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;color:#6B7280;font-weight:600">{{ $label }}</div>
                    <div style="font-size:15px;font-weight:600;margin-top:2px">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px">
        @foreach ($plans as $row)
            @php($plan = $row['plan'])

            <div class="fil-card" style="padding:16px;display:flex;flex-direction:column;{{ $row['current'] ? 'border-color:#111827;border-width:2px' : '' }}">
                <h3 style="margin:0 0 2px;font-size:15px">{{ $plan->name }}</h3>

                <div style="font-size:24px;font-weight:700;letter-spacing:-.02em">
                    {{ $plan->isFree() ? 'Free' : \App\Support\Money::format($plan->price) }}
                    @unless ($plan->isFree())<small style="font-size:12px;font-weight:500;color:#6B7280">/mo</small>@endunless
                </div>

                <div style="font-size:12.5px;color:#6B7280;margin:2px 0 10px">{{ $plan->commissionLabel() }} of each completed job</div>

                @if ($plan->features)
                    <ul style="margin:0 0 12px;padding:0;list-style:none;font-size:12.5px">
                        @foreach ($plan->features as $feature)
                            <li style="padding:3px 0 3px 16px;position:relative">
                                <span style="position:absolute;left:4px;color:#067647;font-weight:800">·</span>{{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div style="font-size:11.5px;color:#6B7280;background:#F9FAFB;border-radius:6px;padding:7px 9px;margin-bottom:12px">{{ $row['note'] }}</div>

                @if ($row['current'])
                    <button class="fbtn fbtn-ghost" type="button" disabled style="margin-top:auto;justify-content:center;opacity:.6;cursor:default">Current plan</button>
                @else
                    <form method="POST" action="{{ route('console.billing.choose') }}" style="margin-top:auto">
                        @csrf
                        <input type="hidden" name="code" value="{{ $plan->code }}">
                        <button class="fbtn fbtn-primary" type="submit" style="width:100%;justify-content:center">Switch to {{ $plan->name }}</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    <div class="fil-card">
        <div class="fil-toolbar">
            <b style="font-size:12.5px">Invoices</b>
            @if ($outstanding > 0)
                <span style="margin-left:auto;font-size:11px;color:#6B7280">
                    Outstanding:
                    <strong style="color:{{ $overdue > 0 ? '#B42318' : '#111827' }}">{{ \App\Support\Money::format($outstanding) }}</strong>
                    @if ($overdue > 0)
                        · {{ $overdue }} overdue
                    @endif
                </span>
            @endif
        </div>

        @if ($invoices->isEmpty())
            <div style="padding:16px;font-size:12.5px;color:#6B7280">
                @if ($current?->isFree())
                    No invoices yet. The {{ $current->name }} plan has no monthly fee.
                @else
                    No billing invoices issued yet. Switching or renewing a paid plan records an invoice here.
                @endif
            </div>
        @else
            <div class="ftbl-wrap">
                <table class="ftbl">
                    <thead><tr><th>Number</th><th>Period</th><th>Due</th><th class="r">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="t">{{ $invoice->number }}</td>
                                <td>{{ $invoice->periodLabel() }}</td>
                                <td style="white-space:nowrap">{{ $invoice->due_at?->format('d M Y') ?? '—' }}</td>
                                <td class="r" style="font-variant-numeric:tabular-nums">{{ \App\Support\Money::format($invoice->amount) }}</td>
                                <td>
                                    @if ($invoice->isOverdue())
                                        <span class="fb fb-r">Overdue {{ $invoice->daysOverdue() }}d</span>
                                    @elseif ($invoice->isPaid())
                                        <span class="fb fb-g">Paid</span>
                                        <span style="font-size:11px;color:#6B7280;margin-left:5px">{{ $invoice->paid_at?->format('d M') }}</span>
                                    @elseif ($invoice->status === \App\Models\Invoice::VOID)
                                        <span class="fb fb-n">Void</span>
                                    @else
                                        <span class="fb fb-y">Open</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
