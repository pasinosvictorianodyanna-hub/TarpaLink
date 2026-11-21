# TarpaLink

A job-order platform for the Philippine printing industry. Customers find a print shop, configure a job (tarpaulin, stickers, flyers, DTF shirts), get an exact price from that shop's own price matrix, and approve a proof before anything goes to press. Shops get a queue, a production board, and a CMS-managed storefront presence.

**This is not e-commerce.** There is no cart, no shipping, no inventory. Customers pick up at the counter. The unit of work is a *job*, not an order.

---

## Status

| Area | Owner | State |
|---|---|---|
| Landing page | Dev A | done — CMS-driven hero, featured shops from DB |
| Customer side | Dev A | done — directory, shop profile, builder, checkout, job status, my jobs |
| Shop panel (Filament) | done | issues #3–#6 — panels, price matrix, jobs, production board |
| Admin panel (Filament) | not started | scaffolded at `/admin`; resources are issues #8–#12 |
| Integrations (PayMongo, SMTP, Maps, storage) | split | PayMongo sandbox intent only |

**Spine (shared, done):** 14 domain tables, models, `PriceCalculator`, `JobWorkflow`, seeders with six shops.

## Stack

- **PHP 8.2+ / Laravel 12** — the whole application, one repo, one deploy
- **Vue 3 (Composition API) + Vite** — the interactive storefront (order builder, checkout, job status, account)
- **Filament v4 (Livewire 3 + Alpine)** — admin panel and shop panel; this is the CMS
- **MySQL 8** — 14 domain tables (22 with framework tables)
- **PayMongo (sandbox)** — payments, no live charges

The rule that keeps it coherent: **Vue renders, Filament edits, Laravel decides.** Any price the browser shows is recomputed server-side before an order is created. The server always wins.

## Layout

```
app/                      Laravel application
  Services/PriceCalculator.php    the only place a price is computed
  Services/JobWorkflow.php        the only place status transitions are allowed
  Models/                         14 domain models
  Http/Controllers/               home, shops, quote, checkout, jobs, account, auth
  Filament/Shop/                  shop panel: services, jobs, board, discounts
  Filament/Admin/                 admin panel resources (issues #8–#12)
  Providers/Filament/             the two panel providers
database/
  migrations/             14 domain tables
  seeders/                6 shops, 16 services, 40 price tiers, 21 jobs in every status
resources/
  css/app.css             design system, extracted from the prototype
  js/app.js               Vue island bootstrap
  js/components/          OrderBuilder.vue
  views/                  Blade layouts and pages
  views/filament/         custom panel views (production board)
design/
  prototype.html          interactive design reference — 18 screens, no build step
docs/
  SPLIT.md                who builds what, and in which order
  ISSUES.md               backlog for the second developer
  CONTRACT.md             the price engine interface both halves depend on
```

`design/prototype.html` is the visual source of truth. Open it in a browser — use the
area tabs to move between Landing, Customer, Shop panel and Admin panel. It is a static
mockup; no framework code lives in it.

## Running locally

Local dev runs on **SQLite** so there is nothing to install. Production is MySQL 8 —
the migrations are portable, just switch `DB_CONNECTION`.

```bash
composer install
cp .env.example .env          # already present in this checkout
php artisan key:generate
php artisan migrate:fresh --seed
php artisan filament:assets   # published Filament CSS/JS — gitignored, regenerate after clone
npm install
npm run build                 # or: npm run dev
php artisan serve
```

Then open <http://127.0.0.1:8000>.

**PHP needs `ext-zip`** enabled for Filament to install (it pulls in openspout for
exports). If `composer install` fails on `ext-zip`, uncomment `extension=zip` in
`php.ini`.

### Seeded accounts (password: `password`)

| Role | Email |
|---|---|
| Customer | `jomar@tarpalink.ph` |
| Customer | `marisol@example.com` |
| Shop owner | `kevin@inkwell.ph` (Inkwell Press, Growth plan) |
| Admin | `sofia@tarpalink.ph` |

Shops are seeded on different plans — Inkwell Press and Tinta & Co. on Growth (3%),
Halftone Hub on Pro (1.5%), the rest on Starter (5%) — so commission math can be
tested against more than one rate.

## The two panels

Both panels run on the **single `web` guard**. There is no second guard and no second
user table: a shop owner is a normal `users` row, and which shop they operate comes
from the `shop_users` pivot. Access is decided in `User::canAccessPanel()`.

| Panel | URL | Who | Contents |
|---|---|---|---|
| Shop | `/shop` | `role = shop_owner` | Services + size tiers, finishing options, quantity discounts, jobs, production board |
| Admin | `/admin` | `role = admin` | Scaffolded; resources arrive with issues #8–#12 |

Sign in at `/shop/login` as `kevin@inkwell.ph`. Every query is scoped to that owner's
`shop_id`, so a shop owner sees only Inkwell Press — and `/admin` returns 403 for them.

**The shop panel never invents a price.** It edits the matrix; `PriceCalculator` is
still the only thing that quotes. Change a tier rate and the storefront quotes the new
price immediately, with no deploy and no cache clear — the matrix fingerprint changes,
so new jobs get a new `matrix_version` while old jobs keep theirs.

**Status changes go through one place.** `JobWorkflow::TRANSITIONS` is the only
definition of what is legal. The jobs table and the production board both ask it, and
it re-checks on save — hiding a button is not a rule.

## Tests

```bash
php artisan test
```

16 tests. The shop-panel suite covers the acceptance criteria of issues #3–#6: tenant
isolation, 403 across panels, illegal status transitions, and the "edit a rate, see a
new quote" loop.

## How the Vue half is wired

The storefront is server-rendered Blade. Vue mounts only where an element declares a
component, so the landing page and directory ship no JavaScript:

```html
<div id="order-builder" data-component="order-builder" data-props='{...}'></div>
```

`resources/js/app.js` scans for `[data-component]`, looks the component up in a small
registry, and mounts it with the JSON props. Adding a new interactive screen means one
line in the registry — not a router, not a bundle per page.

The order builder posts to `POST /api/quote` on every change. That endpoint calls
`PriceCalculator` and returns the same line items the server will use to create the
job, so the number on screen and the number in the database cannot drift.
