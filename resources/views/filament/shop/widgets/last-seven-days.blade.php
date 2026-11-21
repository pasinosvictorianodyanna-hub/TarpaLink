@include('filament.shop.partials.theme')

<x-filament-widgets::widget>
    <div class="tl-wrap">
        <div class="tl-card">
            <header>
                <h3>Last 7 days</h3>
                <span class="meta">{{ \App\Support\Money::short($total) }}</span>
            </header>

            <div class="tl-chart is-short">
                @foreach ($days as $day)
                    <div class="tl-col {{ $day['current'] ? 'is-current' : '' }}">
                        <div class="tl-track">
                            <div
                                class="tl-bar"
                                style="height: {{ max($day['pct'], 2) }}%"
                                title="{{ $day['date'] }}: {{ \App\Support\Money::format($day['total']) }}"
                            ></div>
                        </div>
                        <div class="tl-lbl">{{ $day['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
