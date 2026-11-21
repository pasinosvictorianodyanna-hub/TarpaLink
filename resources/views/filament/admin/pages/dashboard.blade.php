@include('filament.shop.partials.theme')

<x-filament-panels::page>
    <div class="tl-wrap" style="display:grid;gap:14px">

        {{-- Stat tiles --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
            @foreach ($this->tiles() as $tile)
                <div class="tl-card" style="padding:16px 18px">
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.09em;color:var(--tl-muted)">
                        {{ $tile['label'] }}
                    </div>
                    <div style="font-size:1.6rem;font-weight:600;margin-top:6px;font-variant-numeric:tabular-nums">
                        {{ $tile['value'] }}
                    </div>
                    <div style="font-size:11.5px;margin-top:6px;color:{{ $tile['tone'] === 'bad' ? 'var(--tl-bad)' : ($tile['tone'] === 'wait' ? 'var(--tl-wait)' : 'var(--tl-good)') }}">
                        {{ $tile['note'] }}
                    </div>
                    <div style="font-size:11px;color:var(--tl-muted);margin-top:2px">{{ $tile['sub'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- Jobs per day --}}
        @php($chart = $this->chart())
        <div class="tl-card">
            <header>
                <h3>Jobs per day</h3>
                {{-- Said plainly rather than silently distorting the shape. --}}
                <span class="meta">last 14 days · bar height √-scaled</span>
            </header>

            <div style="display:flex;gap:14px;padding:0 16px 6px;font-size:11px;color:var(--tl-muted)">
                <span><span style="display:inline-block;width:8px;height:8px;background:var(--tl-ink);border-radius:2px;margin-right:4px"></span>Placed</span>
                <span><span style="display:inline-block;width:8px;height:8px;background:var(--tl-good);border-radius:2px;margin-right:4px"></span>Completed</span>
                <span style="margin-left:auto">peak {{ $chart['max'] }} in a day</span>
            </div>

            <div class="tl-chart is-short">
                @foreach ($chart['labels'] as $i => $label)
                    <div class="tl-col">
                        <div class="tl-track" style="display:flex;align-items:flex-end;gap:2px;justify-content:center">
                            {{--
                                Square root, not linear. One day carries most of the
                                traffic, so a linear scale renders thirteen days as
                                two-pixel stubs and the chart stops communicating.
                                The header says so; the tooltip carries the real count.
                            --}}
                            <div
                                class="tl-bar"
                                style="height:{{ max((int) round(sqrt($chart['placed'][$i] / $chart['max']) * 100), 4) }}%;width:5px"
                                title="{{ $label }}: {{ $chart['placed'][$i] }} placed"
                            ></div>
                            <div
                                class="tl-bar"
                                style="height:{{ max((int) round(sqrt($chart['completed'][$i] / $chart['max']) * 100), 4) }}%;width:5px;background:var(--tl-good)"
                                title="{{ $label }}: {{ $chart['completed'][$i] }} collected"
                            ></div>
                        </div>
                        <div class="tl-lbl">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px;align-items:start">

            {{-- Pending approvals --}}
            <div class="tl-card">
                <header>
                    <h3>Waiting for approval</h3>
                    <span class="meta">{{ $this->pendingShops()->count() }}</span>
                </header>

                @if ($this->pendingShops()->isEmpty())
                    <div class="tl-empty">No shops waiting. The queue is clear.</div>
                @else
                    <table class="tl-tbl">
                        <tbody>
                            @foreach ($this->pendingShops() as $shop)
                                <tr>
                                    <td>
                                        <div style="font-weight:600">{{ $shop->name }}</div>
                                        <div style="font-size:11px;color:var(--tl-muted)">{{ $shop->city }} · {{ $shop->services_count }} services</div>
                                    </td>
                                    <td style="text-align:right">
                                        <button
                                            type="button"
                                            wire:click="approve({{ $shop->id }})"
                                            wire:loading.attr="disabled"
                                            style="height:28px;padding:0 12px;border-radius:999px;border:1px solid var(--tl-good);background:var(--tl-good);color:#fff;font-size:11.5px;font-weight:600;cursor:pointer"
                                        >Approve</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Payments needing attention --}}
            <div class="tl-card">
                <header>
                    <h3>Payments needing attention</h3>
                    <span class="meta">{{ $this->paymentsNeedingAttention()->count() }}</span>
                </header>

                @if ($this->paymentsNeedingAttention()->isEmpty())
                    <div class="tl-empty">Every payment landed. Nothing to chase.</div>
                @else
                    <table class="tl-tbl">
                        <tbody>
                            @foreach ($this->paymentsNeedingAttention() as $payment)
                                <tr>
                                    <td>
                                        <div style="font-weight:600">{{ $payment->job?->reference ?? '—' }}</div>
                                        <div style="font-size:11px;color:var(--tl-muted)">
                                            {{ $payment->job?->shop?->name }} · {{ $payment->attempts }} attempts
                                        </div>
                                    </td>
                                    <td style="text-align:right">
                                        <span class="tl-pill cancel">{{ ucfirst($payment->status) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Latest jobs --}}
        <div class="tl-card">
            <header>
                <h3>Latest jobs across the platform</h3>
                <span class="meta">live feed</span>
            </header>

            @if ($this->latestJobs()->isEmpty())
                <div class="tl-empty">No jobs yet.</div>
            @else
                <table class="tl-tbl">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Shop</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th style="text-align:right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->latestJobs() as $job)
                            <tr>
                                <td class="mono">{{ $job->reference }}</td>
                                <td>{{ $job->shop?->name ?? '—' }}</td>
                                <td>{{ $job->user?->name ?? '—' }}</td>
                                <td>{{ $job->service?->name ?? '—' }}</td>
                                <td><span class="tl-pill pending">{{ $job->statusLabel() }}</span></td>
                                <td class="tl-amt">{{ \App\Support\Money::format($job->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-filament-panels::page>
