<x-filament-panels::page>
    <style>
        .tpb-wrap {
            --tpb-bg: #F5F3ED;
            --tpb-border: #E2DED2;
            --tpb-ink: #0C0F0E;
            --tpb-muted: #6B6F6C;
            --tpb-card: #FFFFFF;
            --tpb-count-bg: #E6E2D6;
            --tpb-empty: #D6D1C3;
            --tpb-late: #B3261E;

            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: minmax(185px, 1fr);
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 8px;
            align-items: start;
        }
        .tpb-col {
            background: var(--tpb-bg);
            border: 1px solid var(--tpb-border);
            border-radius: 10px;
            padding: 10px;
            min-height: 420px;
            transition: background .12s, border-color .12s;
        }
        .tpb-col.is-over {
            border-color: var(--tpb-ink);
            border-style: dashed;
        }
        .tpb-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            margin-bottom: 10px;
            padding: 0 2px;
        }
        .tpb-head b {
            font-size: 11px;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--tpb-ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .tpb-count {
            font-size: 11px;
            font-weight: 600;
            color: var(--tpb-muted);
            background: var(--tpb-count-bg);
            border-radius: 999px;
            padding: 1px 7px;
            flex: none;
        }
        .tpb-card {
            background: var(--tpb-card);
            border: 1px solid var(--tpb-border);
            border-left: 3px solid var(--tpb-ink);
            border-radius: 8px;
            padding: 9px 10px;
            margin-bottom: 8px;
            cursor: grab;
            box-shadow: 0 1px 0 rgba(12, 15, 14, .04);
        }
        .tpb-card:active { cursor: grabbing; }
        .tpb-card.is-dragging { opacity: .4; }
        .tpb-ref {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 11px;
            font-weight: 700;
            color: var(--tpb-ink);
        }
        .tpb-what {
            font-size: 12.5px;
            color: var(--tpb-ink);
            margin-top: 3px;
            line-height: 1.35;
        }
        .tpb-meta {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin-top: 7px;
            font-size: 11px;
            color: var(--tpb-muted);
        }
        .tpb-due.is-late { color: var(--tpb-late); font-weight: 600; }
        .tpb-empty {
            border: 1px dashed var(--tpb-empty);
            border-radius: 8px;
            padding: 16px 8px;
            text-align: center;
            font-size: 11.5px;
            color: var(--tpb-muted);
        }
        .tpb-hint {
            margin-top: 10px;
            font-size: 12px;
            color: var(--tpb-muted);
        }

        /* Filament toggles dark mode with a class; the media query covers first paint. */
        .dark .tpb-wrap {
            --tpb-bg: #1C1F1E;
            --tpb-border: #2E3331;
            --tpb-ink: #F2F0EA;
            --tpb-muted: #A8AEAA;
            --tpb-card: #242827;
            --tpb-count-bg: #2E3331;
            --tpb-empty: #3A403E;
            --tpb-late: #FF8A80;
        }

        @media (prefers-color-scheme: dark) {
            .tpb-wrap {
                --tpb-bg: #1C1F1E;
                --tpb-border: #2E3331;
                --tpb-ink: #F2F0EA;
                --tpb-muted: #A8AEAA;
                --tpb-card: #242827;
                --tpb-count-bg: #2E3331;
                --tpb-empty: #3A403E;
                --tpb-late: #FF8A80;
            }
        }
    </style>

    <div class="tpb-wrap">
        @foreach ($this->columns() as $column)
            <div
                class="tpb-col"
                x-data="{ over: false }"
                x-bind:class="over && 'is-over'"
                @dragover.prevent="over = true"
                @dragleave.prevent="over = false"
                @drop.prevent="
                    over = false;
                    const id = parseInt($event.dataTransfer.getData('text/plain'));
                    if (id) { $wire.moveJob(id, '{{ $column['key'] }}') }
                "
            >
                <div class="tpb-head">
                    <b>{{ $column['label'] }}</b>
                    <span class="tpb-count">{{ $column['jobs']->count() }}</span>
                </div>

                @forelse ($column['jobs'] as $job)
                    <div
                        class="tpb-card"
                        draggable="true"
                        wire:key="job-{{ $job->id }}"
                        x-data="{ dragging: false }"
                        x-bind:class="dragging && 'is-dragging'"
                        @dragstart="
                            dragging = true;
                            $event.dataTransfer.setData('text/plain', '{{ $job->id }}');
                            $event.dataTransfer.effectAllowed = 'move';
                        "
                        @dragend="dragging = false"
                    >
                        <div class="tpb-ref">{{ $job->reference }}</div>
                        @php($qty = (int) ($job->spec['qty'] ?? 1))
                        <div class="tpb-what">
                            {{ $job->service?->name ?? '—' }}
                            · {{ $job->spec['width_in'] ?? '?' }}×{{ $job->spec['height_in'] ?? '?' }} in
                            · {{ $qty }} {{ $qty === 1 ? 'pc' : 'pcs' }}
                        </div>
                        <div class="tpb-meta">
                            <span>{{ $job->user?->name ?? '—' }}</span>
                            <span class="tpb-due {{ $job->due_at && $job->due_at->isPast() ? 'is-late' : '' }}">
                                {{ $job->due_at?->format('d M') ?? '—' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="tpb-empty">Nothing here</div>
                @endforelse
            </div>
        @endforeach
    </div>

    <p class="tpb-hint">
        Drag a job to the next column. Only legal moves are accepted — the same
        rules the jobs table enforces. Cancelling is possible before production
        starts, not after.
    </p>
</x-filament-panels::page>
