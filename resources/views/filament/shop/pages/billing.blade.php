<x-filament-panels::page>
    <style>
        .tpb-wrap {
            --pb-paper: #F5F3ED;
            --pb-rule: #E2DED2;
            --pb-ink: #0C0F0E;
            --pb-muted: #6B6F6C;
            --pb-card: #FFFFFF;
            --pb-accent: #0C0F0E;
            --pb-good: #0F7A4F;
        }
        .dark .tpb-wrap {
            --pb-paper: #1C1F1E;
            --pb-rule: #2E3331;
            --pb-ink: #F2F0EA;
            --pb-muted: #A8AEAA;
            --pb-card: #242827;
            --pb-accent: #F2F0EA;
            --pb-good: #6EE7A8;
        }

        .pb-current {
            display: flex; flex-wrap: wrap; gap: 28px; align-items: baseline;
            background: var(--pb-card); border: 1px solid var(--pb-rule);
            border-left: 4px solid var(--pb-accent); border-radius: 10px;
            padding: 16px 18px; margin-bottom: 18px;
        }
        .pb-current .lbl {
            font-size: 10.5px; letter-spacing: .14em; text-transform: uppercase;
            color: var(--pb-muted); font-weight: 600; display: block; margin-bottom: 3px;
        }
        .pb-current .val { font-size: 17px; font-weight: 650; color: var(--pb-ink); }

        .pb-grid {
            display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px; margin-bottom: 26px;
        }
        @media (max-width: 900px) { .pb-grid { grid-template-columns: 1fr; } }

        .pb-plan {
            background: var(--pb-card); border: 1px solid var(--pb-rule);
            border-radius: 10px; padding: 16px; display: flex; flex-direction: column;
        }
        .pb-plan.is-current { border-color: var(--pb-accent); border-width: 2px; }
        .pb-plan h3 { margin: 0 0 2px; font-size: 15px; color: var(--pb-ink); }
        .pb-price { font-size: 26px; font-weight: 700; color: var(--pb-ink); letter-spacing: -.02em; }
        .pb-price small { font-size: 12px; font-weight: 500; color: var(--pb-muted); }
        .pb-rate { font-size: 12.5px; color: var(--pb-muted); margin: 2px 0 10px; }
        .pb-feat { margin: 0 0 12px; padding: 0; list-style: none; font-size: 12.5px; color: var(--pb-ink); }
        .pb-feat li { padding: 3px 0 3px 16px; position: relative; }
        .pb-feat li::before {
            content: "·"; position: absolute; left: 4px; color: var(--pb-good); font-weight: 800;
        }
        .pb-note {
            font-size: 11.5px; color: var(--pb-muted); background: var(--pb-paper);
            border-radius: 6px; padding: 7px 9px; margin-bottom: 12px;
        }
        .pb-btn {
            margin-top: auto; width: 100%; border-radius: 7px; padding: 8px 12px;
            font-size: 12.5px; font-weight: 600; cursor: pointer; border: 1px solid var(--pb-accent);
            background: var(--pb-accent); color: var(--pb-paper);
        }
        .pb-btn[disabled] { background: transparent; color: var(--pb-muted); border-color: var(--pb-rule); cursor: default; }
        .pb-btn:not([disabled]):hover { opacity: .88; }

        .pb-hist {
            background: var(--pb-card); border: 1px solid var(--pb-rule);
            border-radius: 10px; overflow: hidden;
        }
        .pb-hist h3 { margin: 0; padding: 13px 16px; font-size: 13px; border-bottom: 1px solid var(--pb-rule); color: var(--pb-ink); }
        table.pb-tbl { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        table.pb-tbl th {
            text-align: left; font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase;
            color: var(--pb-muted); font-weight: 600; padding: 9px 16px; border-bottom: 1px solid var(--pb-rule);
        }
        table.pb-tbl td { padding: 9px 16px; border-bottom: 1px solid var(--pb-rule); color: var(--pb-ink); }
        table.pb-tbl tr:last-child td { border-bottom: 0; }
        .pb-empty { padding: 18px 16px; font-size: 12.5px; color: var(--pb-muted); }
    </style>

    <div class="tpb-wrap">
        @php($current = $this->currentPlan())

        <div class="pb-current">
            <div>
                <span class="lbl">Current plan</span>
                <span class="val">{{ $current?->name ?? 'None' }}</span>
            </div>
            <div>
                <span class="lbl">Commission</span>
                <span class="val">{{ $current?->commissionLabel() ?? '—' }}</span>
            </div>
            <div>
                <span class="lbl">Monthly fee</span>
                <span class="val">{{ $current ? ($current->isFree() ? 'Free' : \App\Support\Money::format($current->price)) : '—' }}</span>
            </div>
            <div>
                <span class="lbl">Applies to</span>
                <span class="val" style="font-weight:500;font-size:13px;color:var(--pb-muted)">the next payout only</span>
            </div>
        </div>

        <div class="pb-grid">
            @foreach ($this->plans() as $row)
                @php($plan = $row['plan'])
                <div class="pb-plan {{ $row['current'] ? 'is-current' : '' }}">
                    <h3>{{ $plan->name }}</h3>
                    <div class="pb-price">
                        {{ $plan->isFree() ? 'Free' : \App\Support\Money::format($plan->price) }}
                        @unless ($plan->isFree())<small>/mo</small>@endunless
                    </div>
                    <div class="pb-rate">{{ $plan->commissionLabel() }} of each completed job</div>

                    @if ($plan->features)
                        <ul class="pb-feat">
                            @foreach ($plan->features as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="pb-note">{{ $row['note'] }}</div>

                    <button
                        class="pb-btn"
                        @if ($row['current']) disabled @else wire:click="choose('{{ $plan->code }}')" wire:loading.attr="disabled" @endif
                    >
                        {{ $row['current'] ? 'Current plan' : 'Switch to '.$plan->name }}
                    </button>
                </div>
            @endforeach
        </div>

        <div class="pb-hist">
            <h3>Invoices</h3>
            @php($invoices = $this->invoices())
            @if ($invoices->isEmpty())
                <div class="pb-empty">
                    @if ($current?->isFree())
                        No invoices yet. The {{ $current->name }} plan has no monthly fee.
                    @else
                        No billing invoices issued yet. Switching or renewing a paid plan records an invoice here.
                    @endif
                </div>
            @else
                <table class="pb-tbl">
                    <thead>
                        <tr><th>Number</th><th>Issued</th><th>Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td style="font-family:ui-monospace,monospace;font-size:11.5px">{{ $invoice->number }}</td>
                                <td>{{ $invoice->issued_at?->format('d M Y') }}</td>
                                <td>{{ \App\Support\Money::format($invoice->amount) }}</td>
                                <td>{{ ucfirst($invoice->status) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-filament-panels::page>
