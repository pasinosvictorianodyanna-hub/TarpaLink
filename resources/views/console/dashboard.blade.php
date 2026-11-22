@extends('layouts.console')

@section('page')
    <div class="fil-stats">
        @foreach ($stats as $stat)
            <div class="fil-stat">
                <div class="l">{{ $stat['label'] }}</div>
                <div class="v">{{ $stat['value'] }}</div>
                <div class="d {{ $stat['arrow'] === 'up' ? 'up' : ($stat['arrow'] === 'down' ? 'dn' : '') }}">
                    @if ($stat['arrow'])
                        {{ $stat['arrow'] === 'up' ? '↑' : '↓' }}
                    @endif
                    {{ $stat['note'] }}
                </div>
            </div>
        @endforeach
    </div>

    <div class="fil-cols two">
        <div class="fil-card">
            <div class="fil-toolbar">
                <b style="font-size:12.5px">Due today</b>
                <span style="margin-left:auto;font-size:11px;color:#6B7280">{{ $dueToday['count'] }} {{ $dueToday['count'] === 1 ? 'job' : 'jobs' }}</span>
            </div>

            <div class="ftbl-wrap">
                <table class="ftbl">
                    <thead>
                        <tr><th>Job</th><th>Customer</th><th>Spec</th><th>Status</th><th class="r">Amount</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($dueToday['rows'] as $row)
                            <tr>
                                <td class="t"><a class="ftbl-link" href="{{ $row['url'] }}">{{ $row['reference'] }}</a></td>
                                <td>{{ $row['customer'] }}</td>
                                <td>
                                    <div class="fil-mini">
                                        <span class="th2" style="background: {{ $row['cover'] }}"></span>
                                        <div><b>{{ $row['title'] }}</b><span>{{ $row['spec'] }}</span></div>
                                    </div>
                                </td>
                                <td><span class="fb fb-{{ $row['tone'] }}">{{ $row['status'] }}</span></td>
                                <td class="r" style="font-weight:600;font-variant-numeric:tabular-nums">{{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="color:#6B7280">Nothing due today.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="fil-card">
            <div class="fil-toolbar">
                <b style="font-size:12.5px">Last 7 days</b>
                <span style="margin-left:auto;font-size:11px;color:#6B7280;font-variant-numeric:tabular-nums">{{ $week['total'] }}</span>
            </div>

            <div style="padding:14px 16px">
                <div class="bars" style="height:96px">
                    @foreach ($week['days'] as $day)
                        <div class="b {{ $day['current'] ? 'hi' : '' }}" style="height: {{ $day['pct'] }}%"></div>
                    @endforeach
                </div>
                <div class="bar-x">
                    @foreach ($week['days'] as $day)
                        <span>{{ $day['label'] }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="fil-card" style="margin-top:16px">
        <div class="fil-toolbar">
            <b style="font-size:12.5px">Needs attention</b>
            <span class="fb fb-r">{{ $attention['count'] }}</span>
        </div>

        <div class="ftbl-wrap">
            <table class="ftbl">
                <thead><tr><th>Job</th><th>Issue</th><th>Waiting</th><th></th></tr></thead>
                <tbody>
                    @forelse ($attention['rows'] as $row)
                        <tr>
                            <td class="t">{{ $row['reference'] }}</td>
                            <td>{{ $row['issue'] }}</td>
                            <td style="color:#6B7280;white-space:nowrap">{{ $row['waiting'] }}</td>
                            <td class="r" style="white-space:nowrap">
                                <form method="POST" action="{{ $row['nudge'] }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="fb fb-n">Nudge</button>
                                </form>
                                <a class="fb fb-y" href="{{ $row['url'] }}">Open job</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="color:#6B7280">Nothing is stuck. Everything is either moving or finished.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
