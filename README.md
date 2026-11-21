# TarpaLink

A job-order platform for the Philippine printing industry. Customers find a print shop, configure a job (tarpaulin, stickers, flyers, DTF shirts), get an exact price from that shop's own price matrix, and approve a proof before anything goes to press. Shops get a queue, a production board, and a CMS-managed storefront presence.

**This is not e-commerce.** There is no cart, no shipping, no inventory. Customers pick up at the counter. The unit of work is a *job*, not an order.

---

## Status

| Area | Owner | State |
|---|---|---|
| Landing page | Dev A | done — CMS-driven hero, featured shops from DB |
| Customer side | Dev A | done — directory, shop profile, builder, checkout, job status, my jobs, profile |
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
  Http/Controllers/               home, shops, quote, checkout, jobs, account, profile, auth, CSV export
  Filament/Shop/                  shop panel: services, jobs, board, reports, billing
  Filament/Admin/                 dashboard widgets, shops, users, CMS
  Providers/Filament/             the two panel providers
database/
  migrations/             17 domain tables
  seeders/                7 shops, 17 services, 43 price tiers, 33 jobs in every status
resources/
  css/app.css             design system, extracted from the prototype
  js/app.js               Vue island bootstrap
  js/components/          OrderBuilder.vue, ProfileForm.vue
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
| Shop | `/shop` | `role = shop_owner`, shop `active` | Dashboard (revenue today, queue, due today, 7-day takings, stuck jobs), services + size tiers, finishing options, quantity discounts, jobs, production board, reports & payout ledger, plan & billing |
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
it re-checks on save — hiding a button is not a rule. Collecting a job stamps
`jobs.collected_at` there too, so the dashboard's on-time rate has a column it can
trust instead of reading the activity log.

**The dashboard counts; it does not cache.** `/shop` is four cards, today's queue, the
last seven days and a "needs attention" list, each computed from this shop's rows on
every request. The cards are not decoration: revenue today is a `SUM()` over `payments`
paid today, and on-time rate is collected jobs whose `collected_at` is not later than
`due_at`. `Needs attention` only lists jobs where the next move is somebody else's —
a proof unapproved for 6 hours, a quote unconfirmed for 24, a finished job uncollected
for 48 — plus anything already past its pickup date, which outranks all of them. The
thresholds are deliberately conservative: chasing a two-hour-old quote trains the owner
to ignore the list.

**There is no "New job" button.** A job is a priced snapshot the storefront produces;
`JobResource` has no create page, because a hand-keyed job would have to invent a price
outside `PriceCalculator`. The dashboard's only action is the payouts export.

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

145 tests. They cover the acceptance criteria of the finished issues: tenant isolation and
cross-panel 403s (#3), the edit-a-rate-see-a-new-quote loop (#4), illegal status
transitions (#5), board drops (#6), the ledger balancing and a plan change leaving
settled payouts alone (#7), approval and rejection both locking the owner in or out (#8),
the policy matrix (#9), CMS edits and section deletion (#10), webhook idempotency and
failure marking (#11), crossover pricing (#12), the profile screen's two endpoints
(#16), every dashboard figure against a plain `SUM()`/`COUNT()` over the same rows
(#17), and the directory's filter rail against the data it filters (#24).

The reports and dashboard tests compare against an independent `SUM()` over `payments`
rather than against the page's own numbers — a test that recomputes the same expression
the code computes proves nothing.

**One caveat when rendering a page to a file for a screenshot.** Livewire emits its
`<script>` tag once per application instance, and the test client reuses one instance
across requests inside a single test. Dumping two pages in the same test method gives
you a second document with no JavaScript — which renders as an empty panel, because
Filament's layout starts the main column at `opacity: 0` and Alpine is what lifts it.
One page per test method, or the "bug" is your own harness.

## How the Vue half is wired

Laravel owns the document. Vue owns the body of a screen.

Blade renders the shell — `<head>`, the marketing nav, the footer, the flash
message, the CSRF token — and the body is a mount point:

```html
<div data-component="home" data-props="{...}"></div>
```

`resources/js/app.js` scans for `[data-component]`, looks the name up in a small
registry, and mounts the component with the JSON props. There is no router and no
bundle per page: a screen with no `data-component` ships no JavaScript at all.

There are two tiers in the registry, and they differ only in size:

| Tier | Directory | What it is |
|---|---|---|
| Page | `resources/js/pages/` | A whole screen. The Blade view is just the mount host. |
| Island | `resources/js/components/` | One interactive block inside a Blade screen. |

The landing page and the three dashboards are pages. The order builder and the
profile form are islands.

The order builder posts to `POST /api/quote` on every change. That endpoint calls
`PriceCalculator` and returns the same line items the server will use to create the
job, so the number on screen and the number in the database cannot drift.

### What stays on the server

Moving a screen to Vue does not move the decisions with it. The controller shapes
every string the component renders — the headline split, each shop's badge, its
distance and its "from" price — and the component decides layout only. A price
computed in the browser is a price the browser can disagree with.

That is also what makes the screens testable: `tests/Feature/LandingTest.php` reads
`$response->viewData('props')` and asserts on the contract, not on markup that only
exists after JavaScript runs. Decoding `data-props` and asserting against it is the
pattern for any page — see `tests/Feature/ProfileTest.php` for the island version.

Two caveats when checking a Vue page in a browser:

- A plain HTTP check sees an empty `<div>`. Anything that renders in the body has to
  be verified with a real browser or against the props.
- A Blade comment (`{{-- --}}`) inside a Vue template is a compile error, not a
  comment. Vue sees `{{ --... }}` and tries to evaluate it.
