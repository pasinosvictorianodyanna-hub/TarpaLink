# TarpaLink

A job-order platform for the Philippine printing industry. Customers find a print shop, configure a job (tarpaulin, stickers, flyers, DTF shirts), get an exact price from that shop's own price matrix, and approve a proof before anything goes to press. Shops get a queue, a production board, and a CMS-managed storefront presence.

**This is not e-commerce.** There is no cart, no shipping, no inventory. Customers pick up at the counter. The unit of work is a *job*, not an order.

---

## Status

| Area | Owner | State |
|---|---|---|
| Landing page | Dev A | done — CMS-driven hero, featured shops from DB |
| Customer side | Dev A | done — directory, shop profile, builder, checkout, job status, my jobs |
| Shop panel (Filament) | Dev B | partial — reports, payouts, billing. No price matrix / jobs / production board yet |
| Admin panel (Filament) | Dev B | done — dashboard, approvals, shops, users, CMS, plans, invoices |
| Integrations (PayMongo, SMTP, Maps, storage) | split | webhook + payout ledger done, sandbox keys only |

**Spine (shared, done):** 13 domain tables, models, `PriceCalculator`, seeders with six shops.
**Added with the panels:** `payouts`, `plans`, `subscriptions`, `invoices`, `payment_webhooks`
(26 tables total), plus two Filament panels on the single `web` guard.

## Stack

- **PHP 8.2+ / Laravel 12** — the whole application, one repo, one deploy
- **Vue 3 (Composition API) + Vite** — the interactive storefront (order builder, checkout, job status, account)
- **Filament v4 (Livewire 3 + Alpine)** — admin panel and shop panel; this is the CMS
- **MySQL 8** — 13 domain tables (21 with framework tables)
- **PayMongo (sandbox)** — payments, no live charges

The rule that keeps it coherent: **Vue renders, Filament edits, Laravel decides.** Any price the browser shows is recomputed server-side before an order is created. The server always wins.

## Layout

```
app/                      Laravel application
  Services/PriceCalculator.php    the only place a price is computed
  Models/                         13 domain models
  Http/Controllers/               home, shops, quote, checkout, jobs, account, auth
database/
  migrations/             13 domain tables
  seeders/                6 shops, 16 services, 40 price tiers, 21 jobs in every status
resources/
  css/app.css             design system, extracted from the prototype
  js/app.js               Vue island bootstrap
  js/components/          OrderBuilder.vue
  views/                  Blade layouts and pages
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
npm install
npm run build                 # or: npm run dev
php artisan serve
```

Then open <http://127.0.0.1:8000>.

### Seeded accounts (password: `password`)

| Role | Email |
|---|---|
| Customer | `jomar@tarpalink.ph` |
| Customer | `marisol@example.com` |
| Shop owner | `kevin@inkwell.ph` (Inkwell Press, Growth plan) |
| Shop owner | `nina@karatula.ph` (Karatula Print — **pending**, cannot log in until approved) |
| Admin | `sofia@tarpalink.ph` |

Panels: **`/admin`** for administrators, **`/shop`** for shop owners. Both are
Filament and both use the same `web` guard — access is decided in
`User::canAccessPanel()`, so a customer who logs in at `/shop` gets a 403.

Shops are seeded on different plans — Inkwell Press and Tinta & Co. on Growth (3%),
Halftone Hub on Pro (1.5%), the rest on Starter (5%) — so commission math can be
tested against more than one rate.

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

## Panels and money

Two Filament panels, one guard. `app/Filament/Admin/` and `app/Filament/Shop/`
are discovered separately, so an admin resource can never appear in a shop's
navigation.

The money path, in order:

1. Checkout creates a `payments` row with `status = pending` and the shop's rate.
2. `POST /webhooks/paymongo` verifies the HMAC signature, then settles it.
3. `PaymentSettlement` recomputes commission at the rate in force **when the money
   lands**, marks the payment paid, and calls `PayoutLedger`.
4. `PayoutLedger` writes one `payouts` row. `payouts.payment_id` is unique, so a
   replayed callback cannot credit twice.

Commission is always read from `shops.commission_rate` (basis points), never a
literal. Every payout stores the rate it was written with, which is why a plan
change is safe: it moves the shop's rate and issues a new invoice, and nothing
that already happened changes.

Without `PAYMONGO_WEBHOOK_SECRET` the webhook endpoint only accepts traffic
outside production — a missing env var fails closed, not open.
