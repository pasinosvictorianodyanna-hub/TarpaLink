@extends('layouts.console')

@section('page')
    <div class="fil-cols two">
        <div style="display:grid;gap:16px">
            <div class="fil-card">
                <div class="fil-toolbar">
                    <b style="font-size:12.5px">The job</b>
                    <span class="fb fb-{{ match ($job->status) {
                        'ready', 'collected' => 'g',
                        'approved', 'in_production' => 'y',
                        'cancelled' => 'r',
                        default => 'n',
                    } }}" style="margin-left:auto">{{ $job->statusLabel() }}</span>
                </div>

                <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;padding:14px 16px">
                    @php
                        $facts = [
                            ['Customer', $job->user?->name ?? '—', $job->user?->phone],
                            ['Service', $job->service?->name ?? '—', $job->service?->unit === 'sqft' ? 'priced by area' : 'priced per piece'],
                            ['Due', $job->due_at?->format('d M Y') ?? '—', $job->due_at && $job->due_at->isPast() && ! in_array($job->status, ['collected', 'cancelled'], true) ? 'past its date' : null],
                            ['Size', $job->sizeLabel(), null],
                            ['Quantity', (string) ($job->spec['qty'] ?? '—'), null],
                            ['Price list', 'v'.$job->matrix_version, 'placed '.$job->created_at?->format('d M Y')],
                        ];
                    @endphp

                    @foreach ($facts as [$label, $value, $note])
                        <div>
                            <div style="font-size:10.5px;letter-spacing:.05em;text-transform:uppercase;color:#6B7280;font-weight:600">{{ $label }}</div>
                            <div style="font-weight:600;margin-top:2px">{{ $value }}</div>
                            @if ($note)
                                <div style="font-size:11px;color:#6B7280">{{ $note }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($job->notes)
                    <div style="padding:0 16px 14px">
                        <div style="font-size:10.5px;letter-spacing:.05em;text-transform:uppercase;color:#6B7280;font-weight:600;margin-bottom:4px">Customer's notes</div>
                        <div style="font-size:12.5px;color:#374151;white-space:pre-line">{{ $job->notes }}</div>
                    </div>
                @endif
            </div>

            <div class="fil-card">
                <div class="fil-toolbar"><b style="font-size:12.5px">Priced lines</b></div>

                <div class="ftbl-wrap">
                    <table class="ftbl">
                        <thead><tr><th>Line</th><th class="r">Qty × rate</th><th class="r">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($job->lines as $line)
                                <tr>
                                    <td>{{ $line->label }}</td>
                                    <td class="r" style="color:#6B7280;font-variant-numeric:tabular-nums">
                                        {{ rtrim(rtrim(number_format((float) $line->qty, 2, '.', ''), '0'), '.') }}
                                        × {{ \App\Support\Money::format((int) $line->unit_price) }}
                                    </td>
                                    <td class="r" style="font-variant-numeric:tabular-nums">{{ \App\Support\Money::format((int) $line->amount) }}</td>
                                </tr>
                            @endforeach

                            @if ($job->discount > 0)
                                <tr>
                                    <td colspan="2" class="r" style="color:#067647">Bulk discount</td>
                                    <td class="r" style="color:#067647;font-variant-numeric:tabular-nums">−{{ \App\Support\Money::format($job->discount) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <div class="fil-foot">
                    <span>Total</span>
                    <strong style="font-size:14px;font-variant-numeric:tabular-nums">{{ \App\Support\Money::format($job->total) }}</strong>
                </div>
            </div>

            <div class="fil-card">
                <div class="fil-toolbar">
                    <b style="font-size:12.5px">History</b>
                    <span style="margin-left:auto;font-size:11px;color:#6B7280">{{ $job->activities->count() }} entries</span>
                </div>

                <div style="padding:6px 16px 14px">
                    @forelse ($job->activities->sortByDesc('created_at') as $activity)
                        <div style="padding:9px 0;border-bottom:1px solid #F3F4F6">
                            <div style="font-size:12px;color:#111827;font-weight:500">{{ $activity->headline() }}</div>
                            @foreach ($activity->bodyLines(4) as $line)
                                <div style="font-size:12px;color:#6B7280;margin-top:3px">{{ $line }}</div>
                            @endforeach
                        </div>
                    @empty
                        <div style="padding:12px 0;font-size:12.5px;color:#6B7280">No status changes yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div style="display:grid;gap:16px">
            <div class="fil-card">
                <div class="fil-toolbar"><b style="font-size:12.5px">Payment</b></div>

                <div style="padding:14px 16px;display:grid;gap:9px">
                    <div style="display:flex;justify-content:space-between;font-size:12.5px">
                        <span style="color:#6B7280">Subtotal</span>
                        <span style="font-variant-numeric:tabular-nums">{{ \App\Support\Money::format($job->subtotal) }}</span>
                    </div>

                    @if ($job->discount > 0)
                        <div style="display:flex;justify-content:space-between;font-size:12.5px">
                            <span style="color:#067647">Discount</span>
                            <span style="color:#067647;font-variant-numeric:tabular-nums">−{{ \App\Support\Money::format($job->discount) }}</span>
                        </div>
                    @endif

                    <div style="display:flex;justify-content:space-between;font-size:12.5px;border-top:1px solid #F3F4F6;padding-top:9px">
                        <span style="font-weight:600">Total</span>
                        <strong style="font-variant-numeric:tabular-nums">{{ \App\Support\Money::format($job->total) }}</strong>
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:center;font-size:12.5px">
                        <span style="color:#6B7280">Status</span>
                        @if ($job->payment === null)
                            <span class="fb fb-n">no payment</span>
                        @elseif ($job->payment->status === 'paid')
                            <span class="fb fb-g">paid {{ $job->payment->paid_at?->format('d M') }}</span>
                        @else
                            <span class="fb fb-y">{{ $job->payment->status }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="fil-card">
                <div class="fil-toolbar"><b style="font-size:12.5px">Proofs</b></div>

                <div style="padding:6px 16px 14px">
                    @forelse ($job->proofs->sortByDesc('version') as $proof)
                        <div style="display:flex;align-items:center;gap:9px;padding:9px 0;border-bottom:1px solid #F3F4F6">
                            <div style="flex:1;min-width:0">
                                <div style="font-size:12.5px;font-weight:600">Version {{ $proof->version }}</div>
                                <div style="font-size:11px;color:#6B7280">{{ $proof->created_at?->format('d M Y, H:i') }}</div>
                            </div>
                            <span class="fb fb-{{ $proof->status === 'approved' ? 'g' : ($proof->status === 'rejected' ? 'r' : 'y') }}">{{ $proof->status }}</span>
                        </div>
                    @empty
                        <div style="padding:12px 0;font-size:12.5px;color:#6B7280">Nothing sent yet.</div>
                    @endforelse
                </div>
            </div>

            @if ($allowed !== [])
                <div class="fil-card">
                    <div class="fil-toolbar"><b style="font-size:12.5px">Move it on</b></div>

                    <form method="POST" action="{{ route('console.jobs.status', $job) }}" style="padding:14px 16px;display:grid;gap:9px">
                        @csrf
                        <select name="status" required style="height:34px;padding:0 9px;border:1px solid #E5E7EB;border-radius:7px;font:inherit;font-size:12.5px;background:#fff">
                            @foreach ($allowed as $next)
                                <option value="{{ $next }}">{{ \App\Models\Job::STATUS_LABELS[$next] ?? $next }}</option>
                            @endforeach
                        </select>
                        <button class="fbtn fbtn-primary" type="submit" style="justify-content:center">Change status</button>
                    </form>
                </div>
            @endif

            {{-- The customer owns the next move on these, so the only thing the
                 shop can usefully do is chase. Written as its own card rather
                 than a link in the status form: it changes nothing about the
                 job's state, and putting it beside "Change status" would read
                 as one more way to move the job. --}}
            @if (in_array($job->status, ['quoted', 'awaiting_proof', 'ready'], true))
                <div class="fil-card">
                    <div class="fil-toolbar"><b style="font-size:12.5px">Waiting on the customer</b></div>

                    <form method="POST" action="{{ route('console.jobs.nudge', $job) }}" style="padding:14px 16px;display:grid;gap:9px">
                        @csrf
                        <p style="margin:0;font-size:12px;color:#6B7280">
                            Emails {{ $job->user?->name ?? 'the customer' }} about this job and records it on the history.
                        </p>
                        <button class="fbtn fbtn-ghost" type="submit" style="justify-content:center">Nudge the customer</button>
                    </form>
                </div>
            @endif

            @if ($canProof)
                <div class="fil-card">
                    <div class="fil-toolbar"><b style="font-size:12.5px">Send a proof</b></div>

                    <form method="POST" action="{{ route('console.jobs.proof', $job) }}" enctype="multipart/form-data" style="padding:14px 16px;display:grid;gap:9px">
                        @csrf

                        <label style="font-size:11px;color:#6B7280">
                            Artwork
                            <input type="file" name="file" accept="image/png,image/jpeg,image/webp,application/pdf" required style="display:block;margin-top:4px;font-size:12px;width:100%">
                        </label>

                        <label style="font-size:11px;color:#6B7280">
                            Note to the customer
                            <textarea name="note" rows="2" maxlength="500" style="display:block;margin-top:4px;width:100%;padding:7px 9px;border:1px solid #E5E7EB;border-radius:7px;font:inherit;font-size:12.5px"></textarea>
                        </label>

                        <button class="fbtn fbtn-primary" type="submit" style="justify-content:center">Send proof</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
