<x-filament-panels::page>
    {{--
        The map, not the territory.

        Each card is one editable area of the platform and links straight to the
        Filament screen that owns it. The card is deliberately dull-looking: the
        work happens on the destination, and a card that looked like an editor
        would invite people to try editing in it.
    --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:1rem;align-items:stretch;">
        @foreach ($this->cards() as $card)
            <div style="display:flex;">
                <x-filament::section>
                    <div style="display:flex;align-items:flex-start;gap:.7rem;">
                        <span style="display:inline-flex;align-items:center;justify-content:center;flex:none;width:2.2rem;height:2.2rem;border-radius:10px;background:rgba(217,119,6,.12);color:#B45309;">
                            <x-filament::icon :icon="$card['icon']" style="width:1.1rem;height:1.1rem;" />
                        </span>

                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;font-size:1rem;line-height:1.25;">{{ $card['name'] }}</div>
                            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;opacity:.55;margin-top:.15rem;">
                                {{ $card['role'] }}
                            </div>
                        </div>

                        <x-filament::badge :color="$card['badgeTone']" style="flex:none;">
                            {{ $card['badge'] }}
                        </x-filament::badge>
                    </div>

                    {{-- A floor under the blurb, not a clip: cards in a row then
                         put their figure and their button on the same line even
                         when one description runs a line longer than its
                         neighbours. --}}
                    <p style="font-size:.84rem;opacity:.75;line-height:1.5;margin:.85rem 0 0;min-height:5.1rem;">
                        {{ $card['blurb'] }}
                    </p>

                    <div style="margin-top:1rem;padding-top:.9rem;border-top:1px solid rgba(0,0,0,.07);">
                        <div style="font-family:ui-serif,Georgia,serif;font-size:1.6rem;line-height:1.1;letter-spacing:-.01em;">
                            {{ $card['figure'] }}
                        </div>
                        <div style="font-size:.78rem;opacity:.6;margin-top:.2rem;line-height:1.45;min-height:2.3rem;">
                            {{ $card['figureLabel'] }}
                        </div>
                    </div>

                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem .9rem;margin-top:1rem;">
                        <x-filament::button
                            tag="a"
                            :href="$card['url']"
                            size="sm"
                            icon="heroicon-m-arrow-top-right-on-square"
                            icon-position="after"
                        >
                            {{ $card['urlLabel'] }}
                        </x-filament::button>

                        @if ($card['secondary'])
                            <a
                                href="{{ $card['secondary']['url'] }}"
                                style="font-size:.78rem;opacity:.7;text-decoration:underline;text-underline-offset:2px;"
                            >
                                {{ $card['secondary']['label'] }}
                            </a>
                        @endif
                    </div>
                </x-filament::section>
            </div>
        @endforeach
    </div>

    <x-filament::section
        heading="Live platform snapshot"
        description="What the content and the approvals above add up to, right now."
    >
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;">
            @foreach ($this->snapshot() as $tile)
                <div style="border:1px solid rgba(0,0,0,.09);border-radius:10px;padding:.8rem .9rem;">
                    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.07em;opacity:.55;">
                        {{ $tile['label'] }}
                    </div>
                    <div style="font-family:ui-serif,Georgia,serif;font-size:1.35rem;line-height:1.15;margin-top:.3rem;">
                        {{ $tile['value'] }}
                    </div>
                    <div style="font-size:.72rem;opacity:.5;margin-top:.15rem;">
                        {{ $tile['note'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section
        heading="How this fits together"
        description="Why some screens are here and others are not."
    >
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;font-size:.83rem;line-height:1.55;">
            <div>
                <div style="font-weight:600;margin-bottom:.2rem;">Editing lives here</div>
                <p style="margin:0;opacity:.72;">
                    Everything on this page is a Filament screen: tables, forms, approvals.
                    Changes are written to the database and take effect immediately.
                </p>
            </div>
            <div>
                <div style="font-weight:600;margin-bottom:.2rem;">The storefront only reads</div>
                <p style="margin:0;opacity:.72;">
                    The public site and the shop console are Laravel pages rendering Vue
                    islands. They display what is stored here and cannot change it.
                </p>
            </div>
            <div>
                <div style="font-weight:600;margin-bottom:.2rem;">Who can get in</div>
                <p style="margin:0;opacity:.72;">
                    Only a user whose role is <code>admin</code> can reach <code>/admin</code>.
                    Anyone else is refused, even with a correct password.
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
