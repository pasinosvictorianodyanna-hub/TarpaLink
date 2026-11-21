<x-filament-panels::page>
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem;">
            <div>
                <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;opacity:.6;">Current plan</div>
                <div style="font-size:1.5rem;font-weight:700;margin-top:.15rem;">{{ $currentPlan }}</div>
                @if ($currentTagline)
                    <div style="font-size:.85rem;opacity:.65;margin-top:.15rem;">{{ $currentTagline }}</div>
                @endif
            </div>
            <div style="display:flex;gap:2.5rem;">
                <div>
                    <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;opacity:.6;">Monthly fee</div>
                    <div style="font-size:1.15rem;font-weight:600;">{{ $currentFee }}</div>
                </div>
                <div>
                    <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;opacity:.6;">Commission</div>
                    <div style="font-size:1.15rem;font-weight:600;">{{ $currentRate }}</div>
                </div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section
        heading="Plans"
        description="Commission is deducted from your payout — the customer never pays more."
    >
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
            @foreach ($planOptions as $plan)
                <div style="border:1px solid rgba(128,128,128,.25);border-radius:12px;padding:1.1rem;display:flex;flex-direction:column;gap:.5rem;{{ $plan['is_current'] ? 'border-color:#0092E4;box-shadow:0 0 0 1px #0092E4 inset;' : '' }}">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
                        <div style="font-weight:700;">{{ $plan['name'] }}</div>
                        @if ($plan['is_current'])
                            <span style="font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;background:#0092E4;color:#fff;padding:.15rem .45rem;border-radius:999px;">Current</span>
                        @endif
                    </div>

                    <div style="font-size:1.35rem;font-weight:700;">{{ $plan['fee'] }}<span style="font-size:.75rem;font-weight:400;opacity:.6;">/mo</span></div>
                    <div style="font-size:.85rem;opacity:.75;">{{ $plan['rate'] }} commission</div>

                    @if ($plan['tagline'])
                        <div style="font-size:.8rem;opacity:.6;">{{ $plan['tagline'] }}</div>
                    @endif

                    <div style="font-size:.78rem;opacity:.7;min-height:2.2rem;">
                        @if ($plan['is_current'])
                            You are on this plan.
                        @elseif ($plan['is_cheaper'])
                            Cheaper than your plan at any volume.
                        @elseif ($plan['crossover'])
                            Worth it above {{ $plan['crossover'] }} monthly sales.
                        @else
                            Costs more at every volume.
                        @endif
                    </div>

                    <div style="margin-top:auto;">
                        @if ($plan['is_current'])
                            <x-filament::button color="gray" disabled>Current plan</x-filament::button>
                        @else
                            <x-filament::button
                                color="primary"
                                wire:click="upgrade('{{ $plan['key'] }}')"
                                wire:confirm="Switch to {{ $plan['name'] }}? Your commission rate changes from the next payout. Past payouts are not affected."
                            >
                                Switch to {{ $plan['name'] }}
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section heading="Invoices" description="Platform fees. A plan change never rewrites an issued invoice.">
        @if (count($invoices) === 0)
            <div style="padding:1rem 0;opacity:.6;font-size:.9rem;">No invoices yet.</div>
        @else
            <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                <thead>
                    <tr style="text-align:left;opacity:.6;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">
                        <th style="padding:.5rem 0;">Invoice</th>
                        <th style="padding:.5rem 0;">Period</th>
                        <th style="padding:.5rem 0;text-align:right;">Amount</th>
                        <th style="padding:.5rem 0;">Status</th>
                        <th style="padding:.5rem 0;">Issued</th>
                        <th style="padding:.5rem 0;">Paid</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr style="border-top:1px solid rgba(128,128,128,.2);">
                            <td style="padding:.6rem 0;font-family:monospace;">{{ $invoice['number'] }}</td>
                            <td style="padding:.6rem 0;">{{ $invoice['period'] }}</td>
                            <td style="padding:.6rem 0;text-align:right;font-weight:600;">{{ $invoice['amount'] }}</td>
                            <td style="padding:.6rem 0;">{{ $invoice['status'] }}</td>
                            <td style="padding:.6rem 0;">{{ $invoice['issued'] }}</td>
                            <td style="padding:.6rem 0;">{{ $invoice['paid'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
