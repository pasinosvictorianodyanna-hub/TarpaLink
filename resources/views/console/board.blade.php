@extends('layouts.console')

@section('page')
    <div class="board">
        @foreach ($columns as $column)
            <div class="board-col" data-board-col="{{ $column['key'] }}">
                <div class="board-head">
                    <b>{{ $column['label'] }}</b>
                    <span class="board-count">{{ $column['jobs']->count() }}</span>
                </div>

                @forelse ($column['jobs'] as $job)
                    <a
                        class="board-card"
                        href="{{ route('console.jobs.show', $job) }}"
                        draggable="true"
                        data-job="{{ $job->id }}"
                    >
                        <div class="board-ref">{{ $job->reference }}</div>
                        <div class="board-what">
                            {{ $job->service?->name ?? '—' }} · {{ $job->sizeLabel() }}
                        </div>
                        <div class="board-meta">
                            <span>{{ $job->user?->name ?? '—' }}</span>
                            <span class="{{ $job->due_at && $job->due_at->isPast() ? 'is-late' : '' }}">
                                {{ $job->due_at?->format('d M') ?? '—' }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="board-empty">Nothing here</div>
                @endforelse
            </div>
        @endforeach
    </div>

    <p class="board-hint">
        Drag a job to the next column. Only legal moves are accepted — the same rules the
        job screen enforces. Cancelling is possible before production starts, not after.
        Without a mouse, open the job and use “Change status”.
    </p>

    {{-- One form, re-pointed at the dropped job. Nothing is submitted until a drop. --}}
    <form id="board-move" method="POST" action="{{ route('console.board.move', ['job' => '__JOB__']) }}" hidden>
        @csrf
        <input type="hidden" name="status" value="">
    </form>

    <script>
        (function () {
            var form = document.getElementById('board-move');
            var base = form.getAttribute('action');
            var status = form.querySelector('[name="status"]');

            document.querySelectorAll('[data-board-col]').forEach(function (col) {
                col.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    col.classList.add('is-over');
                });

                col.addEventListener('dragleave', function () {
                    col.classList.remove('is-over');
                });

                col.addEventListener('drop', function (e) {
                    e.preventDefault();
                    col.classList.remove('is-over');

                    var id = e.dataTransfer.getData('text/plain');

                    if (!id) return;

                    form.setAttribute('action', base.replace('__JOB__', id));
                    status.value = col.getAttribute('data-board-col');
                    form.submit();
                });
            });

            document.querySelectorAll('[data-job]').forEach(function (card) {
                card.addEventListener('dragstart', function (e) {
                    e.dataTransfer.setData('text/plain', card.getAttribute('data-job'));
                    e.dataTransfer.effectAllowed = 'move';
                    card.classList.add('is-dragging');
                });

                card.addEventListener('dragend', function () {
                    card.classList.remove('is-dragging');
                });
            });
        })();
    </script>
@endsection
