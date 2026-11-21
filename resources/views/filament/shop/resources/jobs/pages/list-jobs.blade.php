@include('filament.shop.partials.theme')

<x-filament-panels::page>
    <div class="tl-wrap" style="display:grid;gap:14px">

        <div class="tl-card" style="padding:14px 16px">
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="q"
                    placeholder="Search reference, customer or service"
                    style="flex:1;min-width:220px;height:36px;padding:0 12px;border:1px solid var(--tl-rule);border-radius:999px;background:#fff;font-size:12.5px;color:var(--tl-ink)"
                >

                <select
                    wire:model.live="status"
                    style="height:36px;padding:0 10px;border:1px solid var(--tl-rule);border-radius:999px;background:#fff;font-size:12px"
                >
                    @foreach ($this->statusOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select
                    wire:model.live="due"
                    style="height:36px;padding:0 10px;border:1px solid var(--tl-rule);border-radius:999px;background:#fff;font-size:12px"
                >
                    @foreach ($this->dueOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                @if ($this->hasFilters())
                    <button
                        type="button"
                        wire:click="clearFilters"
                        style="height:36px;padding:0 12px;border:1px solid var(--tl-rule);border-radius:999px;background:none;font-size:12px;font-weight:600;cursor:pointer"
                    >Clear</button>
                @endif

                <span class="meta" style="margin-left:auto">{{ $this->total() }} {{ $this->total() === 1 ? 'record' : 'records' }}</span>
            </div>
        </div>

        <div class="tl-card">
            @php($jobs = $this->jobs())

            @if ($jobs->isEmpty())
                <div class="tl-empty">
                    No jobs match these filters.
                </div>
            @else
                <table class="tl-tbl">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Due</th>
                            <th>Status</th>
                            <th style="text-align:right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($jobs as $job)
                            <tr>
                                <td>
                                    <a
                                        href="{{ \App\Filament\Shop\Resources\Jobs\JobResource::getUrl('view', ['record' => $job]) }}"
                                        class="mono"
                                        style="font-weight:600;text-decoration:underline;text-underline-offset:2px"
                                    >{{ $job->reference }}</a>
                                </td>

                                <td>
                                    <div style="font-weight:500">{{ $job->user?->name ?? '—' }}</div>
                                    <div style="font-size:11px;color:var(--tl-muted)">
                                        {{ $job->user?->phone ?: $job->user?->email }}
                                    </div>
                                </td>

                                <td>
                                    <div style="font-weight:500">{{ $job->service?->name ?? '—' }}</div>
                                    <div style="font-size:11px;color:var(--tl-muted)">{{ $job->specSummary() }}</div>
                                </td>

                                <td style="white-space:nowrap">
                                    @if ($job->due_at)
                                        <span style="{{ $job->due_at->isPast() && ! in_array($job->status, ['collected', 'cancelled']) ? 'color:var(--tl-bad);font-weight:600' : '' }}">
                                            {{ $job->due_at->isToday() ? 'Today '.$job->due_at->format('g:i A') : $job->due_at->format('D g:i A') }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>

                                <td>
                                    <span class="tl-pill {{ $this->pillFor($job->status) }}">{{ $job->statusLabel() }}</span>
                                </td>

                                <td class="tl-amt">{{ \App\Support\Money::format($job->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div style="padding:12px 16px;border-top:1px solid var(--tl-rule)">
                    {{ $jobs->links() }}
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
