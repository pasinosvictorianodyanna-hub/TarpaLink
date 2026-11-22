<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1rem;">
        @foreach ($this->cards() as $card)
            <x-filament::section>
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;">
                    <div>
                        <div style="font-weight:700;font-size:1rem;">{{ $card['name'] }}</div>
                        <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;opacity:.6;margin-top:.1rem;">
                            {{ $card['role'] }}
                        </div>
                    </div>
                    <x-filament::badge :color="$card['badgeTone']">{{ $card['badge'] }}</x-filament::badge>
                </div>

                <p style="font-size:.85rem;opacity:.75;margin:.75rem 0 1rem;">{{ $card['blurb'] }}</p>

                <dl style="display:grid;grid-template-columns:auto 1fr;gap:.35rem .9rem;font-size:.8rem;margin:0;">
                    @foreach ($card['facts'] as $label => $value)
                        <dt style="opacity:.55;">{{ $label }}</dt>
                        <dd style="margin:0;font-weight:500;word-break:break-word;">{{ $value }}</dd>
                    @endforeach
                </dl>

                @if ($card['fix'])
                    <div style="margin-top:1rem;padding:.6rem .75rem;border-radius:8px;background:rgba(217,119,6,.10);border:1px solid rgba(217,119,6,.35);font-size:.78rem;">
                        {{ $card['fix'] }}
                    </div>
                @endif

                {{-- A card is editable when its value is a credential that rotates.
                     Mail, queue and storage are deployment shape: changing the
                     queue driver from a browser is how payments stop settling
                     without anyone noticing. --}}
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-top:1rem;">
                    <span style="font-size:.72rem;opacity:.55;">{{ $card['source'] }}</span>

                    @if ($card['editable'])
                        <x-filament::button
                            size="xs"
                            color="gray"
                            wire:click="mountAction('{{ $card['editable'] }}')"
                        >
                            Edit
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section heading="Health checks" description="What the button above actually runs.">
        <ul style="list-style:none;padding:0;margin:0;font-size:.85rem;">
            @foreach ($this->health() as $check)
                <li style="display:flex;align-items:center;gap:.5rem;padding:.3rem 0;">
                    <span style="color:{{ $check['ok'] ? '#0E9F6E' : '#D92D20' }};font-weight:700;">
                        {{ $check['ok'] ? '✓' : '✕' }}
                    </span>
                    {{ $check['label'] }}
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-panels::page>
