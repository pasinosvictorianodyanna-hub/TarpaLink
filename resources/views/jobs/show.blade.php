@extends('layouts.app')

@section('title', 'Job '.$job->reference.' — TarpaLink')

@section('content')
@php
    $peso = fn ($cents) => '₱'.number_format($cents / 100, 2);
    $proof = $job->latestProof();
    $steps = $job->progressSteps();
    $artworks = $job->artworks;
    $artwork = $artworks->first();
    $maps = 'https://www.google.com/maps/search/?api=1&query='.urlencode($job->shop->address.', '.$job->shop->city);
@endphp

<div class="app">
    <div class="app-body">
        <div class="crumbs">
            <a href="{{ route('account') }}">My jobs</a> <span>/</span> #{{ $job->reference }}
        </div>

        <div class="card job-head">
            <div style="min-width:0">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <h1 class="job-title">{{ $job->titleLine() }}</h1>
                    <span class="status st-{{ $job->statusTone() }}"><i></i>{{ $job->statusLabel() }}</span>
                </div>
                <p class="page-sub">
                    Order #{{ $job->reference }} · placed {{ $job->created_at->format('d M, g:i A') }} · {{ $job->shop->name }}
                </p>
            </div>
            <div class="job-head-actions">
                <a class="btn btn-primary btn-sm" href="{{ route('jobs.receipt', $job) }}">View receipt</a>
            </div>
        </div>

        @if ($job->status === 'awaiting_proof')
            <div class="jobs-alert">
                <span class="ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M12 8v5"/><circle cx="12" cy="16.5" r=".6" fill="currentColor"/><path d="M10.3 3.9L2.6 17a2 2 0 001.7 3h15.4a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/>
                    </svg>
                </span>
                <div>
                    <b>Your proof is waiting for approval</b>
                    <div class="meta">Nothing goes to press until you approve. Check the logo, the spelling and the size.</div>
                </div>
            </div>
        @elseif ($job->acceptsArtwork() && $artwork === null)
            {{-- The order exists and the shop cannot start it. Without this the
                 page looked finished: a paid job, a progress rail, and no hint
                 that the one thing the shop is waiting for has not arrived. --}}
            <div class="jobs-alert">
                <span class="ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M12 8v5"/><circle cx="12" cy="16.5" r=".6" fill="currentColor"/><path d="M10.3 3.9L2.6 17a2 2 0 001.7 3h15.4a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/>
                    </svg>
                </span>
                <div>
                    <b>{{ $job->shop->name }} is waiting for your design</b>
                    <div class="meta">Send the file below and they can start. Nothing is printed until you approve a proof.</div>
                </div>
            </div>
        @endif

        <div class="job-grid">
            <div class="job-col">
                <div class="card job-panel">
                    <div class="job-panel-head">
                        <h4>Progress</h4>
                        @if ($job->due_at && ! in_array($job->status, ['collected', 'cancelled'], true))
                            <span class="tiny">Est. ready {{ $job->due_at->format('D, d M · g:i A') }}</span>
                        @endif
                    </div>

                    <div class="rail">
                        @foreach ($steps as $step)
                            <div class="rail-step {{ $step['state'] }}">
                                <span class="rail-dot">
                                    @if ($step['state'] === 'done')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"><path d="M5 13l4 4L19 7"/></svg>
                                    @endif
                                </span>
                                <div>
                                    <b>{{ $step['label'] }}</b>
                                    @if ($step['detail'])<div class="d">{{ $step['detail'] }}</div>@endif
                                    @if ($step['at'])<div class="t">{{ $step['at']->format('d M · g:i A') }}</div>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($artwork)
                    <div class="card job-panel">
                        <div class="job-panel-head">
                            <h4>Your design</h4>
                            <span class="tag {{ $job->acceptsArtwork() ? 'tag-c' : 'tag-g' }}">
                                {{ $artworks->count() === 1 ? 'Sent' : $artworks->count().' files' }}
                            </span>
                        </div>

                        <p class="small">
                            {{ $job->acceptsArtwork()
                                ? 'This is what '.$job->shop->name.' will print. You can replace it until you approve a proof.'
                                : 'What '.$job->shop->name.' printed. Locked once you approved the proof.' }}
                        </p>

                        <div style="margin-top:12px;display:grid;gap:8px">
                            @foreach ($artworks as $index => $file)
                                <div style="display:flex;align-items:center;gap:11px;padding:10px 12px;border:1px solid var(--line-2);border-radius:9px;{{ $index === 0 ? '' : 'opacity:.62' }}">
                                    <span class="ico" style="flex:none;width:26px;height:26px;display:grid;place-items:center;border-radius:6px;background:var(--tint-y)">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5"/>
                                        </svg>
                                    </span>
                                    <div style="min-width:0;flex:1">
                                        <b style="display:block;font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $file->shortName() }}</b>
                                        <span class="tiny">
                                            {{ $file->sizeLabel() }} · {{ $file->created_at->format('d M, g:i A') }}
                                            @if ($index === 0 && $artworks->count() > 1) · current @endif
                                        </span>
                                    </div>
                                    <a class="btn btn-ghost btn-sm" style="flex:none" href="{{ route('jobs.artwork.file', [$job, $file]) }}">Download</a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    {{-- The card renders with nothing in it rather than not at all:
                         a job page with no artwork card reads as "nothing to do
                         here", and the customer never learns the shop is stuck. --}}
                    <div class="card job-panel">
                        <div class="job-panel-head">
                            <h4>Your design</h4>
                            <span class="tag tag-y">Not sent yet</span>
                        </div>
                        <p class="small">
                            {{ $job->shop->name }} prints the file you send — PNG, JPEG, WebP or PDF, up to 20 MB.
                            Export your design to PDF if you work in Canva, Illustrator or Photoshop.
                        </p>
                    </div>
                @endif

                @if ($job->acceptsArtwork())
                    <div class="card job-panel">
                        <div class="job-panel-head">
                            <h4>{{ $artwork ? 'Replace the file' : 'Send your design' }}</h4>
                        </div>

                        <form method="POST" action="{{ route('jobs.artwork.store', $job) }}" enctype="multipart/form-data">
                            @csrf
                            <label style="display:block">
                                <span class="lbl">Design file</span>
                                <input type="file" name="file" class="inp" required
                                       accept=".png,.jpg,.jpeg,.webp,.pdf,image/png,image/jpeg,image/webp,application/pdf"
                                       style="padding:9px 11px">
                            </label>

                            @error('file')
                                <div class="alert alert-err" role="alert" style="margin-top:10px">{{ $message }}</div>
                            @enderror

                            <button class="btn btn-primary btn-sm" type="submit" style="margin-top:10px">
                                {{ $artwork ? 'Send replacement' : 'Send to '.$job->shop->name }}
                            </button>

                            <p class="tiny" style="margin-top:9px">
                                Only you and {{ $job->shop->name }} can open this file.
                            </p>
                        </form>
                    </div>
                @elseif ($artwork)
                    <div class="card" style="padding:14px 16px">
                        <p class="tiny" style="line-height:1.55">
                            The artwork is locked — you approved a proof, so what the shop printed is what you signed off.
                            Ask the shop for a new proof if something needs changing.
                        </p>
                    </div>
                @endif

                @if ($proof)
                    <div class="card job-panel">
                        <div class="job-panel-head">
                            <h4>{{ $proof->status === 'approved' ? 'Approved proof' : 'Proof' }}</h4>
                            <span class="tag {{ $proof->status === 'approved' ? 'tag-g' : ($proof->status === 'rejected' ? 'tag-r' : 'tag-c') }}">
                                {{ match ($proof->status) {
                                    'approved' => 'Approved v'.$proof->version,
                                    'rejected' => 'Changes requested · v'.$proof->version,
                                    default => 'Awaiting approval · v'.$proof->version,
                                } }}
                            </span>
                        </div>

                        @if ($proof->status === 'approved')
                            <p class="small">
                                Version {{ $proof->version }} approved {{ $proof->approved_at?->format('d M Y · g:i A') }}.
                                The artwork is locked — what goes on press is what you signed off.
                            </p>
                        @elseif ($proof->status === 'rejected')
                            <p class="small">You asked for changes to version {{ $proof->version }}. The shop will upload a new one.</p>
                        @else
                            <p class="small">The shop uploaded version {{ $proof->version }} and it is waiting on you. Nothing goes to press until you approve.</p>
                        @endif

                        <a class="btn btn-ghost btn-sm" style="margin-top:12px"
                           href="{{ route('jobs.proof.file', [$job, $proof]) }}" target="_blank" rel="noopener">
                            View proof artwork ↗
                        </a>

                        @if ($proof->note)
                            <p class="small" style="margin-top:10px;color:var(--ink-2)">“{{ $proof->note }}”</p>
                        @endif

                        @if ($proof->status === 'sent' && $job->status === 'awaiting_proof')
                            <div class="proof-decision">
                                <form method="POST" action="{{ route('jobs.proof.approve', [$job, $proof]) }}">
                                    @csrf
                                    <button class="btn btn-primary btn-sm" type="submit">Approve proof</button>
                                </form>
                                <form method="POST" action="{{ route('jobs.proof.reject', [$job, $proof]) }}">
                                    @csrf
                                    <input type="text" name="note" class="inp" maxlength="500"
                                           placeholder="What needs changing? (optional)" aria-label="What needs changing?">
                                    <button class="btn btn-ghost btn-sm" type="submit">Request changes</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <aside class="job-side">
                <div class="card pickup">
                    <div class="k">Pickup details</div>
                    <div class="ref">{{ $job->reference }}</div>
                    <div class="tiny" style="margin-top:3px">Your order reference</div>

                    <div class="kv" style="margin-top:16px;padding-top:14px;border-top:1px dashed var(--line)">
                        <span>Shop</span><b>{{ $job->shop->name }}</b>
                    </div>
                    <div class="kv">
                        <span>Address</span><b>{{ $job->shop->address }}, {{ $job->shop->city }}</b>
                    </div>
                    <div class="kv">
                        <span>Ready</span><b>{{ $job->due_at?->format('D, g:i A') ?? '—' }}</b>
                    </div>

                    <p class="tiny" style="margin-top:12px;line-height:1.55">
                        Give your order number at the counter. Payment is released to the shop once you collect.
                    </p>

                    <a class="btn btn-ghost btn-sm" style="width:100%;margin-top:12px" href="{{ $maps }}" target="_blank" rel="noopener">
                        Get directions
                    </a>
                </div>

                <div class="card" style="padding:16px">
                    <div style="display:flex;align-items:center;gap:10px">
                        <span class="avatar" style="background:{{ $job->shop->coverGradient() }}">
                            {{ \Illuminate\Support\Str::of($job->shop->name)->explode(' ')->filter(fn ($p) => preg_match('/^\p{L}/u', $p))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}
                        </span>
                        <div style="min-width:0">
                            <b style="display:block;font-size:13px">{{ $job->shop->name }}</b>
                            <span class="tiny">★ {{ number_format($job->shop->rating, 1) }} · {{ $job->shop->turnaroundLabel() }}</span>
                        </div>
                    </div>
                    <div class="kv" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line-2)">
                        <span>Payment</span>
                        <b>
                            @if ($job->payment)
                                {{ $job->payment->provider === 'paymongo' ? 'PayMongo' : ucfirst($job->payment->provider) }}
                                · {{ ucfirst($job->payment->status) }}
                            @else
                                Cash at pickup
                            @endif
                        </b>
                    </div>
                    <div class="kv">
                        <span>Total</span><b>{{ $peso($job->total) }}</b>
                    </div>
                </div>

                @auth
                    @if (auth()->id() === $job->user_id && in_array($job->status, ['quoted', 'awaiting_proof'], true))
                        {{-- The published policy promises a full refund before the proof is
                             approved. Until #82 nothing implemented it, so the promise is now
                             a button on the page it was made on. --}}
                        <div class="card" style="padding:16px">
                            <b style="display:block;font-size:13px">Need to cancel?</b>
                            <p class="tiny" style="margin-top:6px;line-height:1.55">
                                Nothing has been printed yet, so
                                @if ($job->payment?->status === 'paid')
                                    all {{ $peso($job->payment->remainingRefundable()) }} comes back to you.
                                @else
                                    there is nothing to pay.
                                @endif
                                Once you approve a proof the material is committed, and a refund is
                                then at the shop's discretion.
                            </p>
                            <form method="POST" action="{{ route('jobs.cancel', $job) }}" style="margin-top:10px">
                                @csrf
                                <input type="text" name="reason" class="inp" maxlength="255"
                                       placeholder="Why? (optional)" aria-label="Why are you cancelling?">
                                <button class="btn btn-ghost btn-sm" style="width:100%;margin-top:8px" type="submit">
                                    Cancel this order
                                </button>
                            </form>
                        </div>
                    @endif
                @endauth
            </aside>
        </div>
    </div>
</div>
@endsection
