{{--
    Pulls Filament's chrome back toward the design system.

    The prototype is paper (#F5F3ED), ink (#0C0F0E) and a serif display face.
    Filament's components are kept as they are — replacing them would mean
    reimplementing tables, forms and modals — but the surfaces, rules and
    headings are re-pointed at the same tokens the storefront uses.

    Selectors are deliberately few. A Filament upgrade that renames a class
    should cost a background colour, not a broken panel.
--}}
<style>
    :root {
        --tl-paper: #F5F3ED;
        --tl-paper-2: #EFEBE0;
        --tl-ink: #0C0F0E;
        --tl-ink-2: #2C3330;
        --tl-muted: #6C736F;
        --tl-rule: #E1DED4;
    }

    .fi-body {
        background-color: var(--tl-paper);
        color: var(--tl-ink-2);
    }

    .fi-sidebar,
    .fi-topbar {
        background-color: #FFFFFF;
        border-color: var(--tl-rule);
    }

    /* The display face, on the things that are actually headings. */
    .fi-header-heading,
    .fi-section-header-heading,
    .fi-ta-header-heading,
    .fi-wi-stats-overview-stat-label {
        font-family: 'Instrument Serif', Georgia, 'Times New Roman', serif;
        letter-spacing: -.01em;
    }

    .fi-header-heading {
        font-weight: 400;
        font-size: 1.65rem;
    }

    /* Sections read as paper cards, not floating panels. */
    .fi-section {
        border-color: var(--tl-rule);
        box-shadow: 0 1px 2px rgba(12, 15, 14, .04);
    }

    .fi-ta-table thead {
        background-color: var(--tl-paper-2);
    }
</style>
