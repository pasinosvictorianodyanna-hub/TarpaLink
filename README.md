# TarpaLink

A job-order platform for the Philippine printing industry. Customers find a print shop, configure a job (tarpaulin, stickers, flyers, DTF shirts), get an exact price from that shop's own price matrix, and approve a proof before anything goes to press. Shops get a queue, a production board, and a CMS-managed storefront presence.

**This is not e-commerce.** There is no cart, no shipping, no inventory. Customers pick up at the counter. The unit of work is a *job*, not an order.

---

## Status

| Area | Owner | State |
|---|---|---|
| Landing page | Dev A | done — CMS-driven hero, featured shops from DB |
| Customer side | Dev A | done — directory, shop profile, builder, checkout, job status, my jobs |
| Shop panel (Filament) | done | issues #3–#6, #7 — panels, price matrix, jobs, production board, reports |
| Admin panel (Filament) | done | issues #8–#9, #10 — dashboard, approvals, users & roles, CMS |
| Integrations | partly done | PayMongo sandbox: intent + signed webhook + retry. SMTP/Maps/storage still open |

**Spine (shared, done):** 17 domain tables, models, `PriceCalculator`, `JobWorkflow`, `Billing`, `ShopApproval`, seeders with seven shops.

## Stack

- **PHP 8.2+ / Laravel 12** — the whole application, one repo, one deploy
- **Vue 3 (Composition API) + Vite** — the interactive storefront (order builder, checkout, job status, account)
- **Filament v4 (Livewire 3 + Alpine)** — admin panel and shop panel; this is the CMS
- **MySQL 8** — 17 domain tables (25 with framework tables)
- **PayMongo (sandbox)** — payments, no live charges

The rule that keeps it coherent: **Vue renders, Filament edits, Laravel decides.** Any price the browser shows is recomputed server-side before an order is created. The server always wins.

## Layout

```
app/                      Laravel application
  Services/PriceCalculator.php    the only place a price is computed
  Services/JobWorkflow.php        the only place status transitions are allowed
  Services/Billing.php            plan changes; never rewrites settled payouts
  Services/ShopApproval.php       approve / reject / suspend a shop, in one place
  Services/PayMongo.php           sandbox intents + webhook signature check
  Models/                         16 models (17 domain tables — shop_users is a pivot)
  Policies/                       16 policies; one per model, no blanket admin bypass
  Http/Controllers/               home, shops, quote, checkout, jobs, account, auth, CSV export
  Filament/Shop/                  shop panel: services, jobs, board, reports, billing
  Filament/Admin/                 dashboard widgets, shops, users, CMS
  Providers/Filament/             the two panel providers
database/
  migrations/             17 domain tables
  seeders/                7 shops, 17 services, 43 price tiers, 33 jobs in every status
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
| Shop owner | `lia@meridian.ph` (Meridian Press — **pending**, cannot sign in to `/shop`) |
| Admin | `sofia@tarpalink.ph` |

Shops are seeded on different plans — Inkwell Press and Tinta & Co. on Growth (3%),
Halftone Hub on Pro (1.5%), the rest on Starter (5%) — so commission math can be
tested against more than one rate. Meridian Press exists so the approval queue has
something in it on a fresh clone: approve it as `sofia@tarpalink.ph` and `lia@meridian.ph`
can sign in; reject it and she stays locked out.

## The two panels

Both panels run on the **single `web` guard**. There is no second guard and no second
user table: a shop owner is a normal `users` row, and which shop they operate comes
from the `shop_users` pivot. Access is decided in `User::canAccessPanel()`.

| Panel | URL | Who | Contents |
|---|---|---|---|
| Shop | `/shop` | `role = shop_owner`, shop `active` | Services + size tiers, finishing options, quantity discounts, jobs, production board, reports & payout ledger, plan & billing |
| Admin | `/admin` | `role = admin` | Dashboard (GMV, commission, approvals, latest jobs), shops & approvals, users & roles, site content (CMS), site settings |

Sign in at `/shop/login` as `kevin@inkwell.ph`. Every query is scoped to that owner's
`shop_id`, so a shop owner sees only Inkwell Press — and `/admin` returns 403 for them.

**A shop that has not been approved has no panel.** `User::canAccessPanel()` requires
the shop to be `active`, so a `pending` or `suspended` owner is refused at the door —
there is no page to forget the check on. Approving from `/admin` opens it immediately;
rejecting stores the reason on the shop and keeps it closed.

**The shop panel never invents a price.** It edits the matrix; `PriceCalculator` is
still the only thing that quotes. Change a tier rate and the storefront quotes the new
price immediately, with no deploy and no cache clear — the matrix fingerprint changes,
so new jobs get a new `matrix_version` while old jobs keep theirs.

**Status changes go through one place.** `JobWorkflow::TRANSITIONS` is the only
definition of what is legal. The jobs table and the production board both ask it, and
it re-checks on save — hiding a button is not a rule.

## Who may do what

Four roles — Guest, Customer, Shop owner, Administrator — but only three live on
`users.role`. The fourth distinction, **owner vs staff within one shop**, lives on the
`shop_users` pivot, because a person can be an owner of one shop and staff at another.

Sixteen policies, one per model, registered explicitly in `AppServiceProvider`. There is
**no `Gate::before` administrator bypass**, and that is deliberate: a blanket "admin can
do anything" makes rules like *nobody may edit the status trail* or *an admin may not
delete their own account* impossible to express, because the bypass wins before the
policy is ever consulted. Every grant is written out where it belongs.

| Rule | Where it is enforced |
|---|---|
| A customer cannot open `/shop` | `User::canAccessPanel()` → 403 |
| A shop owner cannot touch another shop's matrix | `ServicePolicy`, `PriceTierPolicy`, `PriceOptionPolicy` |
| A customer cannot read another customer's job | `JobPolicy::view` + `Gate::authorize` in `JobController` |
| Only an admin may approve a shop | `ShopPolicy::approve` |
| Only an admin may change `page_sections` | `PageSectionPolicy` |

Query scoping in the panel (`getEloquentQuery()->where('shop_id', …)`) and the policies
are two different jobs: the scope stops accidental leaks and turns a direct URL into a
404, the policy is what actually says no. Both are needed.

## Money that leaves the building

Three rules keep the revenue side honest.

**The webhook is the only thing that marks a payment paid.** Checkout creates a
payment intent and stops there. `ProcessPayMongoWebhook` is idempotent on the intent
id, so a replayed delivery cannot double-credit. It retries three times with backoff;
after that the payment is marked failed and appears on the admin dashboard rather than
leaving a job quietly stuck in pending.

**Commission is snapshotted, never recomputed.** `payments.commission` is an absolute
centavo amount captured at settlement, using the shop's rate at that moment. The
payout ledger is that table — gross, commission and net on one row, so it cannot
disagree with the payment it describes.

**Settlement is a second lifecycle.** `payments.status` says whether the *customer*
paid; `payments.payout_status` says whether *we* have paid the shop. One column for both
would lose the difference between money in and money still owed out. `/shop/reports`
shows the split — held vs released — and `GET /shop/reports/export` streams the same rows
as CSV.

**A plan change only moves the rate forward.** `Billing::subscribe()` writes
`shops.plan` and `shops.commission_rate`, closes the old subscription and issues an
invoice. It never touches a settled payment. Crossover points are computed from the
fee and rate difference, not hardcoded: Growth beats Starter above ₱29,950 of monthly
GMV, Pro beats Growth above ₱60,000.

**The CMS owns words, not layout.** `page_sections` drives the hero, banner, FAQ,
testimonials and footer blurb. Fields are chosen by section key, so testimonials get a
repeater rather than a JSON blob. Setting a section to hidden — or deleting it —
removes the block from the page instead of leaving a hole.

## Tests

```bash
php artisan test
```

62 tests. They cover the acceptance criteria of the finished issues: tenant isolation and
cross-panel 403s (#3), the edit-a-rate-see-a-new-quote loop (#4), illegal status
transitions (#5), board drops (#6), the ledger balancing and a plan change leaving
settled payouts alone (#7), approval and rejection both locking the owner in or out (#8),
the policy matrix (#9), CMS edits and section deletion (#10), webhook idempotency and
failure marking (#11), and crossover pricing (#12).

The reports tests compare against an independent `SUM()` over `payments` rather than
against the page's own numbers — a test that recomputes the same expression the code
computes proves nothing.

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
