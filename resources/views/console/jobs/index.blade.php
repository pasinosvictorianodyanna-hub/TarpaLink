@extends('layouts.console')

@section('page')
    <div class="fil-card">
        <div class="fil-toolbar">
            <form method="GET" action="{{ route('console.jobs') }}" style="display:flex;gap:7px;flex:1;min-width:0;flex-wrap:wrap">
                <input
                    type="search"
                    name="q"
                    value="{{ $term }}"
                    placeholder="Reference or customer"
                    aria-label="Search jobs"
                    style="height:34px;padding:0 11px;border:1px solid #E5E7EB;border-radius:7px;font:inherit;font-size:12.5px;min-width:200px;flex:1;max-width:320px"
                >

                <select
                    name="status"
                    aria-label="Filter by status"
                    onchange="this.form.submit()"
                    style="height:34px;padding:0 9px;border:1px solid #E5E7EB;border-radius:7px;font:inherit;font-size:12.5px;background:#fff"
                >
                    <option value="">All statuses ({{ $counts->sum() }})</option>
                    @foreach (\App\Models\Job::STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>
                            {{ $label }} ({{ $counts[$value] ?? 0 }})
                        </option>
                    @endforeach
                </select>

                <button class="fbtn fbtn-ghost" type="submit">Filter</button>

                @if ($term !== '' || $status !== '')
                    <a class="fbtn fbtn-ghost" href="{{ route('console.jobs') }}">Clear</a>
                @endif
            </form>

            <span style="font-size:11.5px;color:#6B7280;font-variant-numeric:tabular-nums">
                {{ $jobs->total() }} {{ $jobs->total() === 1 ? 'result' : 'results' }}
            </span>
        </div>

        <div class="ftbl-wrap">
            <table class="ftbl">
                <thead>
                    <tr>
                        <th>Job</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Spec</th>
                        <th>Design</th>
                        <th>Status</th>
                        <th>Due</th>
                        <th class="r">Amount</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jobs as $job)
                        <tr>
                            <td class="t"><a class="ftbl-link" href="{{ route('console.jobs.show', $job) }}">{{ $job->reference }}</a></td>
                            <td>
                                {{ $job->user?->name ?? '—' }}
                                <div class="sub">{{ $job->created_at?->format('d M Y') }}</div>
                            </td>
                            <td>{{ $job->service?->name ?? '—' }}</td>
                            <td style="color:#6B7280">{{ $job->sizeLabel() }}</td>
                            <td>
                                {{-- The customer's file, which the shop has no other
                                     way to notice from this screen: the upload only
                                     sends an email, and mail is best-effort (#82). --}}
                                @if ($job->artworks_count > 0)
                                    <span class="fb fb-g">sent</span>
                                @elseif (in_array($job->status, ['collected', 'cancelled'], true))
                                    <span style="color:#9CA3AF">—</span>
                                @else
                                    <span class="fb fb-y">not sent</span>
                                @endif
                            </td>
                            <td>
                                <span class="fb fb-{{ match ($job->status) {
                                    'ready', 'collected' => 'g',
                                    'approved', 'in_production' => 'y',
                                    'cancelled' => 'r',
                                    default => 'n',
                                } }}">{{ $job->statusLabel() }}</span>
                            </td>
                            <td style="white-space:nowrap;{{ $job->due_at && $job->due_at->isPast() && ! in_array($job->status, ['collected', 'cancelled'], true) ? 'color:#B42318;font-weight:600' : 'color:#6B7280' }}">
                                {{ $job->due_at?->format('d M') ?? '—' }}
                            </td>
                            <td class="r" style="font-weight:600;font-variant-numeric:tabular-nums">{{ \App\Support\Money::format($job->total) }}</td>
                            <td class="r"><a class="fb fb-y" href="{{ route('console.jobs.show', $job) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="color:#6B7280">
                                @if ($term !== '' || $status !== '')
                                    Nothing matches that filter.
                                @else
                                    No jobs yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($jobs->hasPages())
            <div class="fil-foot">
                <span>Page {{ $jobs->currentPage() }} of {{ $jobs->lastPage() }}</span>
                <span style="display:flex;gap:7px">
                    @if ($jobs->onFirstPage())
                        <span class="fbtn fbtn-ghost" style="opacity:.45;pointer-events:none">Previous</span>
                    @else
                        <a class="fbtn fbtn-ghost" href="{{ $jobs->previousPageUrl() }}">Previous</a>
                    @endif

                    @if ($jobs->hasMorePages())
                        <a class="fbtn fbtn-ghost" href="{{ $jobs->nextPageUrl() }}">Next</a>
                    @else
                        <span class="fbtn fbtn-ghost" style="opacity:.45;pointer-events:none">Next</span>
                    @endif
                </span>
            </div>
        @endif
    </div>
@endsection
