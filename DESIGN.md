# TarpaLink Design System (`DESIGN.md`)

A machine-actionable specification for TarpaLink — a job-order platform for the Philippine printing industry.
TarpaLink operates across **two distinct visual surfaces**, each calibrated to its PORE mode:

1. **Storefront & Customer Shell ("Print shop, not software")** — **Persuade / Experience / Read**
   Warm archival paper stock, deep ink black, physical CMYK process accents, registration crop marks, halftone dot matrices, and editorial serif headings paired with tight geometric grotesk body copy.
2. **Shop Console & Filament Operations ("The Counter Tool")** — **Operate**
   High-density operational cockpit: neutral cool-grey canvas (`#F9FAFB`), crisp slate ink (`#111827`), `246px` navigation rail, compact `12.5px–13px` controls, hairline dividers, and tabular numeric discipline.

---

## 1. Aesthetic Direction & Brand Archetype

- **Brand Archetype**: Tactile Editorial Print Studio (`Storefront`) + Linear/Stripe Operational Precision (`Console`).
- **Tunable Dials (`Taste Skill`)**:
  - `DESIGN_VARIANCE`: **8/10** on Storefront (asymmetric split grids, physical print artifacts, registration marks, live quote specimen); **4/10** on Console (predictable operational tables, kanban columns, and KPI strips).
  - `MOTION_INTENSITY`: **5/10** (natural deceleration `cubic-bezier(0.22, 0.75, 0.28, 1)`, subtle paper lift on hover, strict `@media (prefers-reduced-motion: reduce)` compliance).
  - `VISUAL_DENSITY`: **4/10** on Marketing/Storefront; **8/10** on Shop Console & Admin Panel.

---

## 2. Color Tokens (CSS Custom Properties — Hex & OKLCH)

### A. Storefront Surface (`resources/css/app.css`)

| Token | Hex | OKLCH Approximation | Role |
| :--- | :--- | :--- | :--- |
| `--paper` | `#F5F3ED` | `oklch(0.963 0.008 92)` | Primary background canvas (uncoated stock) |
| `--paper-2` | `#FBF9F4` | `oklch(0.982 0.006 92)` | Elevated / secondary paper surface |
| `--white` | `#FFFFFF` | `oklch(1.000 0 0)` | Card, input, and sheet surface |
| `--ink` | `#0C0F0E` | `oklch(0.165 0.006 165)` | Primary typography, active pills, primary buttons |
| `--ink-2` | `#222826` | `oklch(0.270 0.008 165)` | Secondary body copy and lead paragraphs |
| `--muted` | `#6C736F` | `oklch(0.550 0.008 160)` | Metadata, captions, table headers (WCAG AA on paper/white) |
| `--muted-2` | `#98A09B` | `oklch(0.700 0.008 160)` | Placeholders, disabled adornments, subtle timestamps |
| `--line` | `#E1DED4` | `oklch(0.900 0.010 92)` | Primary `1px` structural hairline border |
| `--line-2` | `#EDEAE2` | `oklch(0.935 0.008 92)` | Subtle inner row divider |
| `--cyan` | `#0092E4` | `oklch(0.630 0.165 242)` | Process Cyan (C) — verified badges, info tags |
| `--magenta` | `#E8265E` | `oklch(0.595 0.220 12)` | Process Magenta (M) — proof alerts, underline strokes, hot pins |
| `--yellow` | `#F5B700` | `oklch(0.815 0.170 86)` | Process Yellow (Y) — star ratings, active timeline beacons |
| `--green` | `#0E9F6E` | `oklch(0.620 0.145 162)` | Semantic Success — ready for pickup, live pricing dot, bulk discount |
| `--red` | `#D92D20` | `oklch(0.560 0.205 27)` | Semantic Danger — overdue jobs, validation errors |
| `--tint-c` | `#E8F4FD` | `oklch(0.955 0.022 240)` | Cyan badge surface (pair with `#05649B` text) |
| `--tint-m` | `#FDE9EF` | `oklch(0.945 0.025 12)` | Magenta badge/alert surface (pair with `#B3174A` text) |
| `--tint-y` | `#FEF6E0` | `oklch(0.970 0.032 88)` | Yellow highlight surface (pair with `#8A6200` text) |

### B. Console Surface (`resources/css/console.css`)

| Token | Value | Role |
| :--- | :--- | :--- |
| Canvas | `#F9FAFB` | High-contrast operational background |
| Surface | `#FFFFFF` | Cards, sidebar, sticky top bar |
| Primary Ink | `#111827` | Headings, active navigation, key figures |
| Secondary Text | `#4B5563` | Inactive navigation items, table cells |
| Muted Text | `#6B7280` | Column headers, helper notes |
| Structural Border | `#E5E7EB` | Card borders, sidebar divider, table header border |
| Row Divider | `#F3F4F6` | Internal table row separator |

---

## 3. Typography System

- **Font Families**:
  - `--serif`: `'Instrument Serif', Georgia, 'Times New Roman', serif` — Display headlines, section headers, shop cover titles, price sheet previews.
  - `--sans`: `'Inter Tight', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif` — UI controls, body prose, tables, forms.
  - `--mono`: `ui-monospace, SFMono-Regular, Menlo, monospace` — Job references (`TL-42146`), webhook paths, payment intent IDs.

- **Typographic Scale & Rules**:
  - **Display (`.display`)**: `clamp(42px, 5.4vw, 78px)`, line-height `0.96`, letter-spacing `-0.028em`, `var(--serif)`.
  - **Section Heading (`.h2`)**: `clamp(30px, 3.4vw, 50px)`, line-height `1.02`, letter-spacing `-0.024em`, `var(--serif)`.
  - **Page Title (`.page-title`, `.h3`)**: `28px–34px`, line-height `1.05–1.1`, letter-spacing `-0.02em`, `var(--serif)`.
  - **Card / Block Heading (`.h4`)**: `14px–16px`, font-weight `600`, letter-spacing `-0.01em`, `var(--sans)`.
  - **Lead Copy (`.lead`)**: `17px`, line-height `1.6`, max-width `56ch`, color `var(--ink-2)`.
  - **Body Prose (`.prose p`)**: `14.5px–15px`, line-height `1.65–1.72`, max-width `68ch`.
  - **UI & Table Copy**: `12.5px–13.5px`, line-height `1.5`.
  - **Eyebrow (`.eyebrow`)**: `11px`, font-weight `600`, uppercase, letter-spacing `0.14em`, preceded by a `16px × 1px` ink rule.
  - **Numeric Discipline**: Every price, dimension, square-footage counter, rating, badge count, and table figure MUST use `font-variant-numeric: tabular-nums`.

---

## 4. Spatial Grid & Layout Architecture

- **Base Unit**: `4px` micro-grid / `8px` spatial rhythm.
- **Containers**:
  - `.wrap`: `max-width: 1240px`, horizontal padding `32px` (`18px` at `<=640px`).
  - `.app-body`: `max-width: 1160px` (`940px` for focused customer lists), horizontal padding `28px` (`16px` at `<=640px`).
- **Grid Archetypes**:
  - **Directory (`.dir-grid`)**: `250px` sticky filter rail + `1fr` results column (`3` cards across on desktop, `2` on tablet `<=1080px`, `1` on mobile `<=640px`).
  - **Shop Profile (`.shop-grid`)**: `1fr` price matrix list + `300px` summary card (collapses to `1fr` at `<=1080px`).
  - **Order Builder (`.build-grid`)**: `1fr` 4-step configurator + `400px` sticky live quote sheet (collapses to `1fr` at `<=1080px`).
  - **Shop Console (`.fil`)**: `246px` fixed navigation sidebar + `1fr` operational canvas (collapses to `1fr` with slide-over drawer at `<=1080px`).

---

## 5. Elevation, Borders & Surface Physics

- **Border Radii**:
  - `--r-sm`: `8px` (inner chips, mini thumbnails, console buttons)
  - `--r`: `12px` (KPI cards, alerts, input groups)
  - `--r-lg`: `18px` (primary cards, shop cards, price panels)
  - `--r-xl`: `26px` (hero shop banners, dark CTA bands)
  - Pill controls: `999px` (storefront primary/ghost buttons, status pills, filter chips)
- **Shadow Tiers**:
  - `--sh-sm`: `0 1px 2px rgba(12,15,14,.05), 0 6px 16px -10px rgba(12,15,14,.25)`
  - `--sh`: `0 1px 3px rgba(12,15,14,.06), 0 14px 34px -18px rgba(12,15,14,.30)`
  - `--sh-lg`: `0 2px 6px rgba(12,15,14,.06), 0 34px 70px -32px rgba(12,15,14,.35)` (hero tilted quote specimen only)
- **Print-Craft Textures**:
  - `.halftone`: `radial-gradient(circle at center, currentColor 1px, transparent 1.4px)` at `7px × 7px`.
  - `.grid-paper`: `26px × 26px` architectural ruling lines.
  - `.crop`: Corner registration marks (`11px × 11px` crosshairs offset `-6px` from corners).

---

## 6. Component Specifications

- **Buttons (`.btn`)**:
  - Default height `44px` (`.btn-sm` `34px` desktop / `40px` touch target on mobile, `.btn-lg` `52px`), pill radius `999px`, `font-weight: 600`.
  - Tactile active press: `transform: translateY(1px) scale(0.99)`.
  - Focus ring: `:focus-visible` `2px solid var(--ink)` with `2px` offset.
  - Disabled state: flat `--paper` background, `--muted` text, `1px solid var(--line)`, `cursor: not-allowed`.
- **Interactive Chips & Service Cards (`.opt`, `.selpill`)**:
  - Must be native `<button type="button">` elements with `aria-pressed` or `role="radio"` / `aria-checked`.
  - Selected state (`.on`): `background: var(--ink); color: #fff; border-color: var(--ink)`.
- **Inputs & Selects (`.inp`, `.field`, `.finp`)**:
  - Height `38px–42px`, `1px solid var(--line)`, radius `9px–10px`, focus ring `0 0 0 3px rgba(12,15,14,.08)`.
  - Every control must have an explicit `<label>` or `aria-label`.
- **Tables (`.tbl`, `.ftbl`, `.tl-tbl`)**:
  - Uppercase `10px–10.5px` headers with `0.08em–0.1em` tracking.
  - Right-aligned numeric columns (`.r`, `.num`, `.tl-amt`) with `tabular-nums`.
  - Wrapped in horizontal scroll containers (`overflow-x: auto`) on narrow viewports so tables never expand `document.documentElement.scrollWidth`.

---

## 7. Motion & Micro-Interactions

- **Easing**: `--ease: cubic-bezier(0.22, 0.75, 0.28, 1)`.
- **Compositor Discipline**: Animate `transform`, `opacity`, `background-color`, `border-color`, and `box-shadow` explicitly — never use `transition: all`.
- **Reduced Motion**: Under `@media (prefers-reduced-motion: reduce)`, disable infinite keyframe animations (`pulse`, `float`, `slide` marquee) and collapse transition durations.

---

## 8. Anti-Patterns (Forbidden Rules)

1. **No Browser-Calculated Prices**: Components display server-computed quotes from `PriceCalculator` (`/api/quote`) and never invent or round currency to single decimal digits (`₱1,214.1` is forbidden; always format centavos cleanly).
2. **No Card-in-Card Nesting**: Never place a bordered `.card` with a shadow inside another bordered `.card`. Use `1px solid var(--line-2)` hairlines or `--paper-2` background shifts.
3. **No Unscoped OS Dark-Mode Overrides in Light Panels**: Never use raw `@media (prefers-color-scheme: dark)` inside Filament views where Filament manages theme state via `.dark` class toggling.
4. **No Dead Interactive Affordances**: Never put `cursor: pointer` on non-interactive badges (`.sb-head .opt`) or static tabs, and never hide primary navigation on mobile/tablet without a working drawer or toggle.
5. **No Raw `<div>` / `<span>` Click Controls**: Every clickable preset, service card, and tab must be a keyboard-accessible `<button>` or `<a href="...">`.
