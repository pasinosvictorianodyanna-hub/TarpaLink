# Backlog

Issues for the second developer, plus the two spine tasks that must be done together
before either half starts.

> **Status:** every issue **#1**–**#12** is built, plus **#14** (design fidelity),
> **#16** (the profile screen) and **#17** (shop dashboard). They stay here as the
> reference for what exists — read them, do not rebuild them.
> **The documentation set is written:** [SRS](SRS.md) (requirements and traceability),
> [ARCHITECTURE](ARCHITECTURE.md) (context, containers, sequences, decisions) and
> [SCHEMA](SCHEMA.md) (ER diagram, dictionary, invariants).
> **Next up:** **#13** — reconcile `main` with `feature/reports-payouts-policies`,
> which independently implements #7–#9.

Labels used: `spine` · `shop-panel` · `admin-panel` · `integration` · `billing` ·
`p0` (blocks someone) · `p1` (normal)

---

## 1. Schema: 13 domain tables, models and relationships ✅ done

**Labels:** `spine` `p0`
**Blocks:** everything
**State:** built in `51b4ee3`

The whole database in one migration set, so neither half invents its own version.

**Domain tables (13):** `users`, `shops`, `shop_users`, `services`, `price_tiers`,
`price_options`, `quantity_discounts`, `jobs`, `job_lines`, `proofs`, `payments`,
`page_sections`, `site_settings`.

`php artisan migrate:fresh` creates **21** tables in total — the 13 above plus 8
framework tables (`migrations`, `sessions`, `cache`, `cache_locks`, `queue_jobs`,
`job_batches`, `failed_jobs`, `password_reset_tokens`). The queue table is named
`queue_jobs`, not `jobs`, because `jobs` is the domain table.

Rules that are not negotiable:

- Every shop-scoped table carries `shop_id`. Scoping is done through the relationship
  (`$shop->jobs()`, `$shop->services()`) or an explicit `where`, **not** by a global
  scope — there is no ambient "current shop" yet, and a global scope without one
  silently returns nothing. The scope belongs with the panel work (#3, #9), where the
  authenticated shop is actually known.
- Money columns are `unsignedBigInteger` in centavos. Never `decimal`, never `float`.
- `job_lines` stores `label`, `qty`, `unit_price`, `amount` — the snapshot of the
  priced lines.
- `jobs` stores `matrix_version` **on the job**, not on each line. One matrix version
  priced the whole job, so one field is enough.
- `proofs` is versioned: `job_id`, `version`, `file_path`, `status`, `approved_at`.
- `shops` carries `plan` and `commission_rate` (basis points) — see #12.

**Acceptance:** `php artisan migrate:fresh --seed` runs clean on a fresh clone, and
`SHOW TABLES` returns 21 (13 domain + 8 framework).

---

## 2. PriceCalculator + seeders ✅ done

**Labels:** `spine` `p0`
**Blocks:** order builder (Dev A), price matrix (Dev B)
**State:** built in `51b4ee3`

One service class matching `docs/CONTRACT.md` exactly. No controller computes prices
inline; everything goes through this.

Seeders produce:

| Thing | Count |
|---|---|
| Shops (each with a different matrix) | 7 — six trading, one `pending` |
| Services | 17 |
| Price tiers | 43 |
| Price options | 26 |
| Quantity-discount bands | 15 |
| Users (1 admin, 2 shop owners, 5 customers) | 8 |
| Jobs, spread across every status | 33 |
| Payments (approved and later) | 27 — 18 released, 9 still held |
| Proofs | 29 |
| CMS page sections | 6 |

Every shop gets at least 2 jobs so the production board has something to drag, and
collected jobs carry past dates so reports have history — including two older jobs per
trading shop, so the revenue chart spans about six weeks instead of one bar. Shops are
seeded on different plans (Starter 5%, Growth 3%, Pro 1.5%) so the payout ledger is not
all one rate. **Meridian Press** is seeded `pending` with an owner and no jobs, so the
approval queue has something in it on a fresh clone.

**Acceptance:** a tinker session calling `PriceCalculator::quote()` on a seeded shop
returns the numbers in `CONTRACT.md`; the order builder can be built without touching
the database.

---

## 3. Scaffold Filament: two panels, one guard ✅ done

**Labels:** `shop-panel` `p0`
**State:** built

Two separate Filament panels in one app, both on the **single `web` guard**:

- `AdminPanel` at `/admin` — for `users.role = 'admin'`
- `ShopPanel` at `/shop` — for `users.role = 'shop_owner'`

There is no second guard and no second user table. A shop owner is a normal `users`
row; which shop they belong to comes from the `shop_users` pivot (`shop_id`,
`user_id`, `role` = `owner|staff`). Access is decided in `canAccessPanel()`, not by
the guard.

- Separate login pages, separate branding (admin = neutral, shop = the shop's own name
  in the top bar).
- The shop panel resolves the active shop from the pivot and scopes every resource to
  it. This is where a `scopeForShop()` / global scope finally makes sense.
- A shop owner must not reach `/admin`; an admin must not be silently dropped into a
  shop's scope.

**Acceptance:** logging in at `/shop` as `kevin@inkwell.ph` shows only Inkwell Press
records; hitting `/admin` returns 403. Logging in at `/admin` as `sofia@tarpalink.ph`
shows all shops.

---

## 4. Price matrix resource ✅ done

**Labels:** `shop-panel` `p0`
**State:** built
**Unblocks:** Dev A's order builder

The most important task in this backlog. A Filament resource where a shop owner edits
their own prices: size tiers per service, per-unit rates, finishing options (eyelets,
hemming, lamination), quantity discount bands, and minimum order value.

Editing tiers changes the matrix fingerprint, so the next quote returns a new
`matrix_version` while old jobs keep theirs.

**Acceptance:** change the tarpaulin tier-B rate in the panel, reload the storefront,
and the order builder quotes the new price with no deploy and no cache clear.

---

## 5. Jobs resource + status transitions ✅ done

**Labels:** `shop-panel` `p1`
**State:** built

Filament resource listing this shop's jobs. Read-only on price, editable on status.

The seven statuses already exist in the `jobs` enum and in `Job::statusLabel()`:
`quoted`, `awaiting_proof`, `approved`, `in_production`, `ready`, `collected`,
`cancelled`. The forward path is
`quoted → awaiting_proof → approved → in_production → ready → collected`;
`cancelled` is reachable from any pre-production state and is terminal — it is not a
step in `Job::timeline()`, which already returns the six forward steps for the
customer's status page.

Illegal transitions are rejected server-side, not just hidden in the UI. Each change
writes to an activity log and fires the customer notification.

**Acceptance:** a job cannot jump from `quoted` to `collected`; the attempt returns a
validation error and leaves the row untouched.

---

## 6. Production board ✅ done

**Labels:** `shop-panel` `p1`
**State:** built

Custom Filament page — a kanban of the shop's active jobs by status. Drag between
columns writes the same transition rules as task 5. `cancelled` is not a column.

**Acceptance:** dragging a job from *On press* to *Finishing* updates the status, the
customer sees it on the job status page, and dropping into an illegal column snaps back.

---

## 7. Reports + payouts ✅ done

**Labels:** `shop-panel` `p1`
**State:** built

`/shop/reports` — four headline figures, eight weeks of gross as bars, top five services
by revenue, and the payout ledger. `GET /shop/reports/export` streams the same ledger as
CSV, registered through the panel's `authenticatedRoutes()` so it inherits the panel's
session and `canAccessPanel()` check rather than sitting outside them.

Settlement is a **second lifecycle** on `payments`: `status` says whether the customer
paid, `payout_status` says whether we have paid the shop. Folding them into one column
would lose the difference between "money in" and "money owed out".

Revenue by week, top services by revenue, and a payout ledger: gross, platform
commission, net, status, payout date. Export to CSV.

The commission rate is **not** a constant. Read it from `shops.commission_rate`
(basis points) via `$shop->commissionLabel()`; `payments.commission` already holds the
absolute centavo amount, snapshotted at the time of payment. Inkwell Press seeds at 3%
(Growth), Halftone Hub at 1.5% (Pro), the rest at 5% (Starter).

**Acceptance:** the ledger sums to gross − commission = net for every row, and the
month total matches a manual `SUM()` on `payments`. Changing a shop's plan does not
alter any existing payout row.

**Proven by:** `ReportsTest` — 7 tests, including one that sums `payments` independently
and compares, and one that subscribes a shop to Pro and asserts every existing
`commission` value is byte-identical afterwards.

---

## 8. Admin dashboard + shop approvals ✅ done

**Labels:** `admin-panel` `p1`
**State:** built

Dashboard: `PlatformStats` (GMV, commission, active shops, jobs this month),
`PendingApprovals` (the queue, hidden entirely when empty), `RevenueChart` (eight weeks
of GMV), `LatestJobs` (feed across all shops), plus the existing
`PaymentsNeedingAttention` pinned to the top when it has rows. All four render inline
rather than lazily, so the dashboard does not pop in after paint.

Admission lives in `App\Services\ShopApproval` — `approve`, `reject`, `suspend`,
`reopen` — called by both the queue widget and the shops resource, so the status and the
reason are written in exactly one place. `shops` gained `status_reason`, `approved_at`,
`approved_by` and `status_changed_by`; the last two are the audit trail of who let a shop
in and who shut it down.

Platform GMV, jobs, active shops, commission earned. A pending-approvals queue where
an admin approves or rejects a new shop (rejected shops cannot log in to the shop
panel). A live feed of the latest jobs across all shops.

Approval is `shops.status` — `pending → active`, or `suspended`. Commission earned is
the sum of `payments.commission`, not GMV × one rate, because shops sit on different
plans.

**Acceptance:** approving a seeded pending shop lets that owner log in; rejecting
leaves them locked out with a reason.

**Proven by:** `AdminDashboardTest` — 8 tests covering the queue, the commission sum
(asserted *not* to equal `GMV × 5%`, since the shops are on three different plans),
approval through the actual table action, and the lockout in both directions.

---

## 9. Users, roles, policies ✅ done

**Labels:** `admin-panel` `p1`
**State:** built

Sixteen policies in `app/Policies/`, registered explicitly in `AppServiceProvider` so the
whole authorization surface is visible in one place. `User` gained `shopIds()`,
`belongsToShop()` and `isOwnerOf()` — the last one distinguishes a shop's owner from its
staff, which is the difference between "may edit the price matrix" and "may work the
board". The admin panel gained a `Users` resource with role editing and a `Shop access`
relation manager over the `shop_users` pivot.

**There is no `Gate::before` administrator bypass.** A blanket "admin can do anything"
makes "nobody may edit the status trail" and "an admin may not delete their own account"
impossible to express, because the bypass wins before the policy is consulted. Every
grant is written out in the policy that owns it.

**Fixed while doing this:** `JobController::show()` had no authorization at all — any
visitor with a job reference could read another customer's name, phone and payment. Job
references are five digits, so that was enumerable. It now calls `Gate::authorize('view',
$job)`.

Four roles: Guest, Customer, Shop Owner, Administrator. `users.role` holds
`customer|shop_owner|admin`; the `shop_users` pivot carries the per-shop
`owner|staff` role. Policies on every model so a shop owner can only touch their own
records, and only an administrator can approve shops or edit site content.

**Acceptance:** a customer cannot open `/shop`; a shop owner cannot edit another
shop's price matrix; only an administrator can change `page_sections`.

**Proven by:** `AuthorizationTest` — 10 tests, asserted against the Gate rather than a
controller, plus the HTTP layer (404 from the scoped query, 403 from the policy, redirect
for a guest).

---

## 10. CMS — site content resource ✅ done

**Labels:** `admin-panel` `p1`
**Requirement:** C
**State:** built

Filament resource over `page_sections` and `site_settings`. Six sections: homepage
hero, announcement banner, featured shops, testimonials, FAQ, footer. Editing copy
and display order only — layout stays in Blade and Vue.

**Acceptance:** editing the hero headline in the panel changes the landing page after
a refresh, with no deploy. Deleting a section hides it from the site rather than
breaking the page.

---

## 11. PayMongo webhook + payout ledger ✅ done

**Labels:** `integration` `p0`
**State:** built

Checkout creates a payment intent; the webhook is the only thing that may mark a job
as paid. Idempotent on `payment_intent_id` so a replayed webhook cannot double-credit.
Writes the commission split to the payout ledger, using the shop's own rate.

Sandbox keys only. A failed webhook must not leave a job stuck in `pending` — retry
with backoff, and surface it on the admin dashboard if it fails three times.

**Acceptance:** replaying the same webhook twice creates one payment row, and the
payout ledger shows gross − the shop's plan rate = net.

---

## 12. Billing: plans, subscriptions, invoices ✅ done

**Labels:** `billing` `p1`
**State:** built

Three tiers — Starter (free, 5%), Growth (₱599, 3%), Pro (₱1,499, 1.5%). Crossover
points: Growth wins above ₱29,950 monthly GMV, Pro above ₱60,000.

`shops.plan` and `shops.commission_rate` already exist, so this task adds `plans`,
`subscriptions` and `invoices` and a panel page showing the current plan, the
crossover math, and an upgrade path. Upgrading updates `shops.plan` and
`shops.commission_rate` and nothing else — past `payments.commission` rows are
immutable.

**Acceptance:** upgrading changes the commission rate applied to the *next* payout,
the invoice history survives a plan change, and no historical payment row changes.

---

## 17. Shop dashboard: stats, due today, last 7 days, needs attention ✅ done

**Labels:** `shop-panel` `p0`
**State:** built

The shop panel's front door. `/shop` was Filament's stock dashboard with a single
"Welcome, Kevin Cruz" card — the one screen from the prototype that did not exist at
all. Four cards, then today's queue beside the week's takings, then everything stuck.

| Card | Source |
|---|---|
| Revenue today | `SUM(payments.amount)` where `paid_at` is today, against yesterday |
| Open jobs | `COUNT(jobs)` where status is not `collected`/`cancelled`, plus how many are due today |
| Avg. job value | `AVG(jobs.total)` over the last 30 days, cancelled excluded |
| On-time rate | collected jobs where `collected_at <= due_at` over all collected |
| Due today | jobs due inside today's calendar day, unfinished, oldest first |
| Last 7 days | one `SUM()` per day, bucketed in PHP, today's bar emphasised |
| Needs attention | proof unapproved > 6h, quote unconfirmed > 24h, ready > 48h, or past `due_at` |

`jobs.collected_at` did not exist and `job_activities` is a log, not a fact about the
job — so the migration adds the column and `JobWorkflow::transition()` stamps it, in
the only place a status change can happen.

Two decisions worth arguing about later:

- **No "New job" button.** A job is a priced snapshot the storefront produces; a
  hand-keyed job would have to invent a price outside `PriceCalculator`.
- **No `AccountWidget`.** It rendered a full-width "Welcome" card on a screen made of
  figures, and the identity is already in the topbar user menu.

**Acceptance:** every figure equals a plain `SUM()`/`COUNT()` over the same rows; only
this shop's jobs appear; a proof sent an hour ago is not chased; a late collection
lowers the on-time rate and an on-time one raises it.

**Proven by:** `tests/Feature/ShopDashboardTest.php` (12 tests).

---

## 16. Profile screen, as the second Vue island ✅ done

**Labels:** `shop-panel` `p1`
**State:** built

The prototype had a profile screen and the application had none — no route, no view, no
way to change your own name, number or password. Everything else in the account area
existed; this was the one hole.

Two cards, both saving without a page reload:

- **Personal details** — name, mobile, email, preferred pickup area. The save button
  stays disabled until the values actually differ from what the server sent, so retyping
  the same name is not treated as a change.
- **Password** — current, new, confirm. `users.password_changed_at` is new; the card
  shows "Last changed 3 months ago" from it.

The email uniqueness rule ignores the current user (`Rule::unique(...)->ignore($user->id)`),
otherwise saving your own address would fail as a duplicate. A wrong current password
returns **422 with a field error**, not a 403 — it is a validation failure, and the form
should say which box is wrong rather than claim the request was forbidden.

**Acceptance:** both cards save in place, the page is reachable at `/account/profile`
behind `auth`, and a guest gets neither the page nor either endpoint.

---

## 17. The last five gaps — geocoding, picker, inbound mail, integrations, checkout ✅ done

**Labels:** `shop-panel` `storefront` `p1`
**State:** built (#63)

The five items §11 of the SRS had been carrying since v1.1. Each was a place where the
platform worked but did not finish the sentence it started.

**No geocoding.** A shop's coordinates were seeded and a customer positioned themselves by
browser location or by tapping the map; there was no way to type an address.
`App\Services\Geocoder` speaks Nominatim — no key, which is why it is the default — caches
for 30 days including misses, sends an honest `User-Agent`, and validates the coordinates
before storing them. `/shops?address=` redirects to `?near=lat,lng&src=address&place=…`, so
the resolved origin is a URL that survives a refresh or a share. `GEOCODER_ENABLED=false`
removes the box rather than leaving a control that hangs.

**The shop picker was a form post.** Now `App\Livewire\ShopPicker`: re-checks membership
and trading status at the moment of the change, refuses with a notification instead of a
silent no-op, and compares the return URL's host to the request's before redirecting —
`returnTo=https://evil.example` falls back to `/shop`.

**No inbound mail.** Every notification left with no `Reply-To`, so a customer who answered
one was writing to a mailbox that did not exist. `ReplyAddress` tags the job reference into
the local part (`reply+TL-48213@…`), all three mailables and the shop nudge carry it, and
`POST /webhooks/inbound` verifies an HMAC over the raw body, normalises Mailgun / Postmark /
plain payload shapes, matches the job by tag then by subject, and appends a
`job_activities` row with `source = email`. It never moves a job: mail is unauthenticated,
so an answer is a note the shop reads and approval stays behind the signed-in link.

**The integrations screen was informational.** It also read
`services.paymongo.public_key` / `secret_key` while the config defines `public` / `secret`,
so it reported "Not configured" on a deployment that was taking payments. Now an
`integrations` table holds one encrypted JSON blob per provider, `Credentials` reads database
first and environment second, and the PayMongo keys and inbound mailbox are editable from
the card that reports on them. Mail, queue and storage stay in the environment on purpose.

**Checkout was a Blade form.** A rejected field reloaded the page and threw away the other
two panels; a slow intent looked like a button that did nothing. `CheckoutForm.vue` posts
JSON and shows errors under the input that caused them, the controller answers
`expectsJson()` with 201/422/409/500 and everything else with the redirect it always
returned, and the island sends `expected_total` so the server can refuse to charge a total
the customer never saw.

**Also fixed:** `withoutMiddleware(VerifyCsrfToken::class)` on the PayMongo webhook excluded
nothing — the `web` group registers `ValidateCsrfToken`, a different class name — so every
live callback would have been answered with a 419. No test caught it because the middleware
short-circuits under `runningUnitTests()`. CSRF is now waived by path, with a test that
asserts the exclusion.

**Proven by:** `GeocodingTest` (13), `InboundMailTest` (27), `IntegrationsTest` (22),
`CheckoutIslandTest` (11), and `ShopSwitchTest` (10) rewritten against the component.

---

## #66 — The shop had two shells, and a click changed between them

**Symptom.** Signing in as a shop owner landed on `/console` — a paper-white sidebar, a
queue, four stat cards. Clicking *Jobs* landed on `/shop/jobs`, which is the Filament panel:
a different sidebar, different navigation groups, a different page header, a different type
scale. Every link out of the dashboard changed the product underneath the click.

**Cause.** The console was built as one screen, not as a surface. Its shell lived inside
`ShopDashboard.vue`, so it existed exactly once; the other seven screens were still Filament
resources and the sidebar linked into them as a deliberate "bridge" while the port was half
done. `ConsoleTest` even asserted the bridge, which is how a temporary state becomes a
permanent one.

**Fix.** The console is the shop surface, and the shell is a layout
(`resources/views/layouts/console.blade.php`) rather than a component, so a screen cannot
invent its own chrome. All seven remaining screens moved:

| Screen | Was | Now |
|---|---|---|
| Jobs list + detail | `JobResource` (Filament) | `/console/jobs` — filters, search, upload proof, change status |
| Production board | `ProductionBoard` page | `/console/production-board` — same `JobWorkflow` transitions, drag or the job screen |
| Price matrix | `PriceMatrix` page | `/console/price-matrix` — same `PriceCalculator` preview |
| Reports | `Reports` page | `/console/reports` — same payment-derived totals, ledger, CSV |
| Billing | `Billing` page | `/console/billing` — same crossover maths |
| Services | `ServiceResource` | `/console/services` — tiers and add-ons on one form |
| Quantity discounts | `QuantityDiscountResource` | `/console/quantity-discounts` |

The dashboard is server-rendered too. It had one piece of state — the mobile drawer — and a
Vue runtime for that was 115 kB the shop did not need; the console now loads only
`console.css` and fifteen lines of inline script.

**The shop switcher moved with it.** It only existed in the panel's topbar, so an owner of
two shops would have had to go back to the panel to change shops — the last click that
changed the shell. `POST /console/shop` stores the session preference, checks the posted id
against the user's own active shops, and refuses a `return_to` that is not a `/console` path.

**Also fixed:** `ServiceController`'s tier/option sync. `Relation::__call` forwards to the
underlying builder and mutates it, so `$relation->whereKey($id)->first()` left `id = 1` on
the relation; the cleanup delete then ran `id = 1 AND id not in (1)`, matched nothing, and
silently kept every tier the owner removed. It builds a fresh query per operation now.

**Proven by:** `ConsoleTest` (21) rewritten against the rendered page rather than a props
blob, `ConsoleScreensTest` (35) walking every screen including tenancy, permissions and the
switcher, and `ShopStaffTest` / `SignOutTest` updated where they decoded Vue props.

**Still true, deliberately:** the Filament shop panel is registered and its tests still
pass. Nothing links to it any more, but deleting it — with the ten test files that reference
`/shop` — is its own change, and a half-deleted panel would be worse than an unlinked one.

---

## #68 — The "Open a shop account" button could not be clicked

**Symptom.** The owner band at the bottom of the homepage had a working-looking primary
button. Hovering changed nothing, clicking changed nothing, and no error appeared in the
console. It was not disabled, not covered visually, not missing an `href`.

**Cause.** `.cta-band .grid-paper` is the decorative grid, stretched over the band with
`position:absolute; inset:0`. A positioned element paints above static siblings, so the
overlay sat on top of the anchor. The `h2` and `p` in the same band had already been lifted
with `position:relative` — they were losing the same fight and were patched individually —
so the button was the one element left underneath. The click was landing on a `<div>`.

**Fix.** `pointer-events:none` on the base `.grid-paper` rule. Decoration should never be a
target, and stating that once on the layer itself holds wherever the grid is reused; the
`.cta-band` override sets position, inset, opacity, colour and background-image only, so it
does not undo it.

```css
.grid-paper{
  /* Decoration, never a target. Stretched over a band it is a sibling of the
     content, and a positioned element paints above static siblings — so without
     this the "Open a shop account" button is visible, styled, and unclickable. */
  pointer-events:none;
}
```

**Proven by:** `AssetBuildTest > the decorative grid never swallows a click`, which reads the
**built** stylesheet. That is the only layer where a stacking defect exists — a DOM
assertion that the anchor has an `href` and the right text would have passed on the broken
build, which is why the bug reached a screenshot before it reached a test. The test also
asserts no later rule clears the property.

**Lesson.** `position:relative` on the text was a symptom being treated one element at a
time. Fix the overlay once, or every element added to that band afterwards has to defend
itself and the one that forgets is silently dead.

---

## #71 — Retiring the shop panel, and the two things it was still holding

**Symptom.** Nothing. That was the problem: `/shop` had been registered, themed,
permissioned and tested since #66 moved every screen out of it, and no click in the product
led there. An unlinked duplicate of the entire shop surface, kept alive by its own test
suite.

**Why it could not just be deleted.** Two capabilities lived only in the panel.

*The nudge.* `NeedsAttention::nudge()` — the button that emails a customer about a job that
is waiting on them — existed on the panel's dashboard widget and nowhere else. The console
rendered the same *Needs attention* list with no action on it, so deleting the panel would
have removed the feature without a single failing test.

*The attention copy.* The panel's `classify()` splits `quoted` by payment — *Paid, proof not
sent yet* at six hours, *Unpaid, payment not confirmed* at twenty-four — which is what #32
fixed. The console's copy read *"Quote sent, customer hasn't ordered"*: the exact wording
#32 removed, because `quoted` is the state a job is born in at checkout and there is no
"ordering" step left. The port in #66 reintroduced the regression, and the three tests that
pinned it were not carried across, so nothing caught it.

**Fix.** Both moved first, then the panel went.

| Moved | To |
|---|---|
| The nudge | `App\Services\JobNudge` + `JobNudged` mailable; `POST /console/jobs/{job}/nudge`; buttons on the attention rows and the job page |
| The paid/unpaid split | `DashboardController::classify()` |

The nudge keeps the order that made it honest: the activity row is written first, the mail
is best-effort through `Notifier`, and the banner says which of the two happened rather
than claiming a send that failed. `Mail::raw` became a mailable, so it is `Mail::assertSent`
now instead of untestable.

**Deleted:** `app/Filament/Shop/**` (31 files), `resources/views/filament/shop/**` (9),
`ShopPanelProvider`, `ShopPicker` and its topbar hook, the `/shop/plan-and-billing`
redirect, and the `'shop'` branch of `User::canAccessPanel()`.

One file moved rather than died: `filament/shop/partials/theme.blade.php` was being
included by three *admin* screens, so it is `filament/partials/theme.blade.php` now. Deleting
the shop panel took the admin dashboard down with it until that was spotted — the tests said
`View [filament.shop.partials.theme] not found`, which is exactly the kind of coupling an
"unused" directory hides.

**Proven by:** `JobNudgeTest` (9) for the carried-across feature; three restored
classification tests and two arithmetic tests in `ConsoleTest`; board tenancy, the
price-matrix refusal path and two switcher behaviours in `ConsoleScreensTest`; and two
guards in `SmokeTest` — no route may be registered on the `shop` prefix, and no console
page may link into it. The first was verified to be non-vacuous by running the same filter
against `admin`, which finds sixteen routes.

`ShopPanelTest` became `ShopWorkflowTest` and kept only what was never about the panel: the
transition rules and the matrix-version bump. `ShopDashboardTest` and `ShopSwitchTest` were
deleted once their coverage existed on the console.

**Verified:** 448 tests / 2136 assertions, `npm run build` clean, `route:list` shows zero
`shop/*` routes.

---

## #73 — A shop could not ask to join, and the page said it could

**Reported as a question.** "If I want to open a shop account for my shop, how can I request
to admin?" The answer was: you can't.

**What the page promised.** `/open-a-shop` — where the homepage CTA points — said: *"Create
an account, then send us your business name, counter address and the services you want to
list. We review the application, and once it is approved the shop console opens."*

**What existed.**

- `/register` creates customers only. `RegisteredUserController` hard-codes
  `'role' => 'customer'` and never reads a role from the form.
- No "send us your details" form, address or queue. Nothing received an application.
- The admin panel could list, view, edit, approve, reject and suspend a shop — but had **no
  create page**. `ShopResource::getPages()` is `index` / `view` / `edit`.
- `ShopApproval::approve()` flips the *shop* to `active`; it never touched `users.role`. So
  even a manually created pending shop attached to a customer account would still be 403 at
  the console door, because `worksInShop()` reads `users.role`.
- The only place a shop was ever created was `DatabaseSeeder`.

So the front door for shop owners led to instructions nobody could follow, and the approval
queue could only ever contain shops a developer had seeded.

**The flow, as specified.** Request → admin approves → admin's approval creates the account →
owner is given access. A request is **not** an account and **not** a pending shop.

| Stage | What happens |
|---|---|
| Request | `POST /open-a-shop` writes a `shop_requests` row. No user, no shop. Rate-limited, with a honeypot that answers exactly like a person. |
| Review | `/admin/shop-requests` — oldest first, `new` by default, unanswered count on the nav. |
| Approve | `ShopOnboarding::approve()` creates the `shop_owner` account and the shop in one transaction, on the free Starter plan via `Billing`, attaches the pivot, and records the resulting `shop_id` on the request. |
| Hand over | A password-reset token is minted and emailed. No plaintext password is stored, generated-and-told, or emailed. |

**Why its own table.** A `shops` row with `status = pending` and no owner would appear in the
shop list, in the pending-approval count, and in every query that assumes a shop has somebody
who runs it. The request is its own record until an administrator decides.

**Two edges worth naming.** An email that already belongs to a customer is reused and
promoted rather than duplicated — two rows for one person is how a shop ends up unable to see
its own history. An email belonging to an administrator is refused outright, because `role`
holds one value and approving would demote them.

**The link is shown, not just sent.** This deployment's mailer is `log`, so `Notifier::send()`
succeeds while nothing arrives. The approve action therefore flashes the link onto the
request page, where it is copyable for the administrator — otherwise "approved" would mean
"created an account nobody can log into".

**Also corrected:** the seeded copy on `/open-a-shop` told the applicant to create an account
first, which the flow no longer needs. It now describes what actually happens.

**Proven by:** `ShopRequestTest` (16) — the form creates a request and *nothing else* (user
and shop counts asserted unchanged), the honeypot stores nothing, the throttle returns 429 on
the sixth submission, approval creates the owner + shop + pivot + plan and sends the invite,
an existing customer is reused and promoted, an admin address is refused, a second approval
is refused, and the invitation link is followed end to end through password reset to a
working sign-in that lands on `/console`.

---

## #75 — An approved shop could lose its only way in

**Found while answering a question.** "Paano malalaman ng nagrequest na na-approve na ang
request niya?" The answer is that the invitation email *is* the notification — which is also
how much the handover depends on a single message.

**What it relied on.** Approving mints a password-reset token, emails it, and flashes it onto
the request page for exactly one request. The token is stored **hashed**, so the plaintext
link cannot be rebuilt from the database. If the administrator closed the page, or the hour
ran out, the shop was live and the owner had no way in. The panel offered two actions:
`approve` and `reject`.

**What was added.** `ShopOnboarding::reinvite()` and a `Resend invitation` action on approved
requests, in the header of the request page and on the queue row.

- Mints a fresh token through `Password::broker()->createToken()` — the same path as the
  first handover, so it adds no new surface.
- `createToken()` **overwrites** the row, so the previous link stops working. One account,
  one way in, never a growing set of live links.
- Creates nothing: the account and the shop already exist.
- Refuses when the shop behind the request is gone — `shop_id` is `nullOnDelete`, so an
  approved request can outlive its shop — rather than mailing a link to a console that would
  404 on arrival.
- Flashes the new link onto the page exactly as approval does, so it still works when
  `MAIL_MAILER=log`.

**Not built, and why.** Rejection still emails nobody. That is a different silence — the
applicant is told nothing at all — and it needs a decision about what the message says before
it needs code.

**Proven by:** `ShopRequestTest` (5 new) — a re-send mints a different link and mails again
without creating a user or a shop; the old link is dead and the new one sets a working
password; the action is hidden on an unapproved request and visible once approved; a request
whose shop is gone is refused.

---

## #77 — The applicant creates the account, and approval activates it

**The decision.** Requiring a real mailbox to demo the product was the symptom. The cause was
that the applicant never had a password of their own: approval created the account and handed
over a set-password link, so mail delivery was load-bearing. With `MAIL_MAILER=log` — this
deployment's default — an approved shop was one closed tab away from having an owner who could
not sign in, and #73's request #3 was found in exactly that state.

So the flow inverts: **the applicant registers as they apply, and approval activates that
account.**

| Stage | What happens |
|---|---|
| Apply | `/open-a-shop` collects account details (name, email, password) and shop details in one submit. It creates the **account and the request**, never a shop, and signs them in. |
| Wait | `/console/waiting` reports the state of the request. Every other console URL redirects there. |
| Approve | The shop is created and attached to the account that applied. **Nothing is emailed** and no token is minted. |
| Use | The owner signs in with the password they chose. |

**Why the role is safe to set early.** An applicant is created as `shop_owner` before anything
is approved, and it grants nothing: `ConsoleController::shop()` admits only a user attached to
an *active* shop, and an applicant has none. The role is a label; the shop attachment is the
permission. "Not legal yet" was already enforced — what was missing was an explanation, which
is the waiting room.

**Two silences removed.** A pending owner used to get a 403 from every console URL, and after
signing in was sent to `/account` — an empty customer job list with nothing to say why. Both
now land on a page that says which state they are in: waiting, refused with the reason, or
paused.

**One open request per account.** `user_id` makes it cheap to ask, so a second submission
while one is waiting is refused. This is what let the same person become two shops in #73's
dev data.

**The older shape survives.** A request with no `user_id` still gets an account created on
approval and a set-password link, and `Resend invitation` still applies to it — hidden for
self-registered requests, where there is no invitation to re-send.

**Proven by:** `ShopRequestTest` (30 in the file) — applying creates one account and one
request and no shop, the account is signed in and lands in the waiting room, every console URL
redirects a pending owner there, approval attaches the shop and sends nothing while minting no
token, the chosen password signs the owner in and lands on `/console`, a signed-in customer is
reused without a password, a second request is refused, an admin cannot apply, and a rejected
request shows its reason.

---

## #79 — The Profile page repeated a "My jobs" button the app bar already had

Pointed out from a screenshot of `/account/profile`: the page head carried a ghost button
back to `/account`, which is the same destination as the **My jobs** item already in the app
bar on that screen — where **Profile** is the one marked active. Two controls, one job, and
the second one in a place a button does not belong.

Removed. Nothing is stranded: the app bar keeps **My jobs** and the breadcrumb keeps **Home**.
`.page-head` is `display:flex; justify-content:space-between`, so a single child simply
left-aligns and no CSS changed.

**Proven by:** `ProfileTest` + `CustomerNavTest` (22) — and the rendered page, where `My jobs`
now appears once instead of twice.

---

## #81 — The processor fee was recorded on every payment and deducted from nobody

Found while preparing the revenue-model question for the defense: `processing_fee` is written
at checkout (`CheckoutController:272`, `2.5% + ₱15`) and then **read by nothing**. Every
screen that showed the shop its money computed `amount − commission`, so the shop was
credited the processor's cut as if it had kept it.

**Who bears it.** Asked and answered: **the shop does.** The processor takes its cut out of
the money as it arrives, so the shop was never owed that part of the gross. Net is now
`amount − commission − processing_fee`.

**What changed, in one place.** `Payment::net()` is the definition. It had been duplicated
in three screens, so the fix had to be a single expression or the four copies would drift
again — `Payment::netFrom()` exists for the CSV export, which streams flat rows through a
cursor and never builds a model.

| Surface | Before | Now |
|---|---|---|
| `/console/reports` | Fee column was showing commission | Separate Commission and Fee columns; footer reads `gross − commission − fee = net` |
| Payout CSV | `Gross, Commission, Net` | `Gross, Commission, Fee, Net` |
| Admin dashboard | "Commission earned" | ~~"Platform margin" = `commission − fee`~~ — **reverted by #82**, see below |

**The number that makes the case.** Platform-wide the fee is nearly as large as the
commission: ₱2,066.78 kept against ₱2,004.92 paid out. Calling the commission "earnings"
looked like overstating it by a factor of thirty-three.

**That reasoning was wrong, and #82 corrected it.** Once the shop bears the fee, the platform
receives the gross, pays the shop the net, and *never pays the processor* — so the commission
**is** what the platform keeps. `commission − fee` is the arithmetic of the option that was
rejected, and it understated platform revenue by the entire fee volume. The label is
"Commission earned" again, and the fee is reported as a deduction the shop sees. What survives
from this issue is the part that was right: the shop's net includes the fee.

**Break-even tickets.** Because the fee is `2.5% + ₱15` and the commission is a percentage,
the shop's payout per job is reduced by a fixed amount regardless of ticket size:

| Tier | Rate | Fee exceeds commission below |
|---|---|---|
| Starter | 5% | ₱600 |
| Growth | 3% | ₱3,000 |
| Pro | 1.5% | never profitable — the rate is below the processor's |

Solving `r × a = 0.025 × a + 1500` gives `a = 1500 / (r − 0.025)`. Below that line the *shop*
still nets a positive amount, but the platform's cut no longer covers the fee it is passing
through — which is why Pro at 1.5% is a loss on every card payment.

**Proven by:** `ReportsTest`, `ConsoleScreensTest`, `DataIntegrityTest` (renamed
`…_splits_into_commission_fee_and_net`, plus an assertion that the split adds back to gross),
`CheckoutFlowTest`, `PayMongoWebhookTest`, `AdminDashboardTest` — and the rendered
`/console/reports` for Inkwell: net ₱23,364.11 = 24,914.41 − 747.43 − 802.87.

---

## #82 — A refunded payment could not be recorded, and the policy promised one

`payments.status` has carried a `refunded` value since #11 and nothing ever wrote it. So a job
the shop could not produce had no way out of the ledger — while the site's own published policy
said, in two places, *"a job can be cancelled for a full refund any time before you approve the
proof"* and *"a refund may be partial"*. The promise was on the page; the button was not.

**A status cannot hold an amount.** Partial is the normal case, so the refund is stored as two
accumulating numbers — `refunded_amount` and `commission_refunded` — and `status` flips to
`refunded` only once the whole payment has gone back. That keeps BR-4: the amounts the payment
was settled at are never rewritten.

**The commission reverses; the processor's fee does not.** We did not earn a commission on money
the customer did not keep, so it comes off in proportion. The processor keeps its fee on a
refund, and by #81's decision the shop bears that. Which means a fully refunded payment nets a
small **negative** rather than zero:

| | ₱1,000 payment, ₱50 commission, ₱40 fee |
|---|---|
| Customer gets back | ₱1,000 |
| Commission kept | ₱0 |
| Fee the processor keeps | ₱40 |
| **Net to shop** | **−₱40** |

That is the shop's loss to carry, and it is why the refunded row has to stay in the ledger.
Filtering it out — the obvious implementation — would make the loss disappear instead of being
netted off the next payout.

**Who may refund.** The customer may cancel, and be refunded in full, only while the job is
`quoted` or `awaiting_proof`. Past the proof the material is committed and the policy hands the
decision to the shop, so the customer's route closes at the **route**, not merely at the hidden
button. A shop or an admin refunds explicitly, in whole or in part, from the admin payouts
screen. Cancelling from the console after approval cancels the job and moves no money.

**Rounding.** The reversal is computed as a cumulative target, not a per-refund slice: three
refunds of ₱333.33 against a ₱1,000 payment reverse the whole ₱10.00 of commission, not ₱9.98.
A `min()` guards the last one, because rounding must never reverse more commission than was
charged.

**Also fixed, because a refund exposed it.** The admin dashboard's "Platform margin" from #81
was wrong. Once the shop bears the fee, the platform receives the gross, pays the shop the net,
and never pays the processor — so the commission *is* what the platform keeps. `commission − fee`
was the arithmetic of the option that was rejected, and it understated revenue by the whole fee
volume: ₱61.86 shown against ₱2,066.78 actually earned. It is "Commission earned" again, with
the fee reported as a deduction the shop sees.

**Proven by:** `RefundTest` (13) — a full refund leaves the shop carrying the fee, a partial
reverses the commission in proportion, repeated partials stop at the gross, rounding never
overshoots, an unsettled payment cannot be refunded, the refund is written onto the job's
history with its reason, a customer cancelling pre-proof is refunded, the same customer is
refused after approval, a stranger gets 404 rather than a confirmation, the shop cancelling
after approval refunds nothing, and the refunded row still appears on both the reports page and
the admin payouts screen.

**Verified live:** signed in as a customer, cancelled `TL-32638` (₱702.00) from the order page.
The job went `cancelled`, the payment `refunded`, and Studio Sampaguita's ledger showed
`₱702.00 −₱702.00 — −₱32.55 −₱32.55 Refunded` — net **−₱32.55**, the fee, exactly as designed.

---

## #83 — Subscription revenue was invoiced once and never collected, and never again

`Billing::subscribe()` issues an invoice when a shop moves onto a paid plan. Nothing ever
issued another one, and nothing ever recorded a payment against the first. So the product had
a revenue line on paper and none in the bank, and an `open` invoice from any month ago was
still sitting there looking collectable.

**Why it could not be fixed with a cron.** The invoice table had no idea *which month* it was
billing. `status = 'open'` plus `issued_at` cannot answer "does this shop already have this
month's bill?", so a recurring command would either duplicate every period or skip every
period. The period had to become a column first.

| Added | Why |
|---|---|
| `period_start` / `period_end` | The question "is this period billed?" needs an answer that does not depend on when the job last ran |
| `due_at` | Terms can change without silently restating how late every historical invoice was |
| `paid_by` | A subscription is settled by GCash or a bank transfer — "paid" is a person's claim, so it carries their name |

**Recurring, and safe to run daily.** `billing:issue` runs at 01:10 from a `scheduler`
service. Daily rather than monthly: a monthly schedule gets one chance to run, and a machine
that is asleep on the 1st leaves every shop unbilled with nothing to say so. It is idempotent
by construction — the period is derived from the subscription's anniversary, and it asks
whether that period already has an invoice — so thirty runs produce one bill and the
thirty-first produces none.

Periods follow the day the shop signed up, and `addMonthNoOverflow` keeps a subscription
started on the 31st from rolling into March.

**Overdue is computed, not stored.** `open` plus a past `due_at`. A stored `overdue` would
need a nightly job to keep it true and would be wrong every night between the deadline and
the run — which is exactly when somebody is looking at it.

**Void is not a free month.** A voided invoice does not suppress its period: the next run
re-bills it. Void means "this bill was wrong", not "skip this one".

**A mid-month switch was double-billing.** Found while testing: switching Growth → Pro in the
same month left both invoices `open`, so the shop was collectable twice and the superseded
bill was unpayable forever. `subscribe()` now voids the open invoices for the month the
switch lands in — and only that month, because an unpaid invoice from an earlier month is
arrears and upgrading does not erase arrears. Four such rows were sitting in the dev database
and were voided.

**Who collects.** An administrator, on `/admin/invoices`, which stamps `paid_by`. There is no
card on file to charge, and pretending otherwise would produce invoices marked paid by nobody.
The action refuses to collect a bill twice, to collect a void one, or to void a paid one.

**Deployment changed.** `DEPLOY.md` said "no scheduled tasks", which is no longer true and
would have meant the command never ran in production. There is now a `scheduler` service in
`docker-compose.yml` and a `scheduler` case in the entrypoint.

**Proven by:** `BillingInvoiceTest` (12) — one invoice per period however often the command
runs, a new period on the month roll, free and suspended shops skipped, due date seven days
after the period starts, overdue computed and clearing the moment it is paid, recording a
payment twice refused, a void invoice never collected, a voided period re-billed, a mid-month
switch voiding the superseded bill, the command's own output, and the admin screen listing and
collecting a row.

**Verified live:** `billing:issue` issued `INV-202611-0005` for Inkwell Press (₱599.00, due
30 Nov) and the second run answered "Nothing due". `/console/billing` shows
**Outstanding ₱599.00** with the superseded invoices reading `Void`.

---

## #90 — The pre-deploy MySQL run pointed at a database that did not exist

`DEPLOY.md` has recommended, since #88, that the suite be run against MySQL before the first
deploy, and prints a one-liner to do it. Running it for the first time on the live stack
showed the one-liner cannot work as written: `DB_DATABASE=tarpalink_test` is a database the
compose stack never creates, `DB_USERNAME=root DB_PASSWORD=secret` are not the credentials
`.env.docker.example` asks for, and the stack does not publish MySQL to the host at all, so
nothing on the host can reach it. The command fails — and a check that fails the first time
it is run is a check that gets struck off the list rather than fixed.

**What it found once it could run.** `CheckoutFlowTest` asserted `$job->matrix_version ===
6612`. On MySQL the same code produced `8113`, and nothing was wrong: the fingerprint is
`crc32` over `tier.id` values, which the database assigns, so it is only meaningful inside
the installation that produced it. The test had frozen one database's row ids into an
assertion and called it a business rule. It was green on SQLite every day of the project's
life and would have been red on the first real deploy.

**The fix is not a looser assertion.** `matrixVersion()` moved from `PriceCalculator` to
`Service`, where it belongs — it is a property of the price list, the console prints it on a
job (`v6612`), the receipt prints it, and a test that wants to say *the job recorded the
matrix it was priced against* should be able to ask the service the same question the
calculator asked. `PriceCalculator` now calls `$service->matrixVersion()` and keeps no
second copy, so there is still exactly one definition. The assertion compares the job
against the service: it holds on any database and still fails if a job ever stores a stale
version.

**One diagnosis was wrong, and is written down so it is not re-derived.** The first reading
was that `artisan test` swallows exported variables — Collision's `TestCommand::clearEnv`
does clear every key of `.env` from the parent environment, which made the theory look
supported. It is irrelevant: running with `DB_PORT=3399` fails with "actively refused",
which proves the export reaches PHPUnit intact. The failure was the database name, not the
mechanism. This mattered, because the wrong diagnosis would have shipped a second
`phpunit.mysql.xml` to work around a bug that did not exist; the file was deleted once the
run proved it was unnecessary.

**The trap that is left.** `phpunit.xml` pins `DB_DATABASE=:memory:`, so a reader who exports
only `DB_CONNECTION=mysql` asks MySQL for a database called `:memory:` and gets an error
that reads like a misconfiguration rather than half an override. The four variables have to
move together, and `DEPLOY.md` now says so in the comment above the command.

**Proven by:** the full suite on both engines — 505 tests, 2,438 assertions, green on SQLite
(3 min) and on MySQL 8.4 (21 min). On MySQL the reports query's `only_full_group_by` and
every `TIMESTAMP` read go through the production code path, which SQLite never exercises.

**Verified live:** `tarpalink_test` held 27 tables in migration batch 1 after the documented
command ran, so the connection demonstrably reached MySQL rather than falling back to the
in-memory default — which is the check the old one-liner could not have offered.

---

## #94 — A payout could be released for a job that was never delivered

The Pricing page says it three times: *"You pay commission on completed jobs only"*, *"5%
commission per completed job"*, and *"Payouts are released from the platform ledger **after
the job is collected**"*. The payouts screen enforced none of it. Both the row action and the
bulk action guarded on exactly two things — not already paid, not fully refunded — and never
looked at the job.

**Why that is money, not tidiness.** The customer is charged at checkout: before the proof,
before the press, before anything is carried out of a shop. So `payments.status = paid` says
the platform is holding money, not that the shop earned it. And a job cancelled from
`approved` refunds nothing — that was the decision on #82, because the material is already
committed — which leaves a paid payment with `payout_status = pending`, sitting on the queue
looking exactly like a deliverable one.

The end state of that path is worth saying plainly: the customer paid and got nothing, the
shop is owed the full net, and the platform keeps its commission on a job that was never
printed. The only thing standing between that and the bank was an administrator noticing.

**The seeded data was already two-thirds wrong.** Of the fourteen rows awaiting payout, five
were collected and nine were not:

| Job status | Rows | Payable? |
|---|---|---|
| `collected` | 5 | yes |
| `in_production` | 4 | no |
| `ready` | 3 | no |
| `approved` | 2 | no |

A bulk "mark selected as paid out" would have released money for four jobs still on the press
and two that had only just been approved, and nothing on the screen said so.

**One rule, one place.** `Payment::mayBeReleased()` returns a bool and
`releaseBlockedReason()` returns the sentence behind it, because "the job was cancelled" and
"the job is still in production" are different answers that lead to different actions. The
badge, the row action and the bulk action all read the same method — the alternative was
three copies of `status === 'collected'` drifting apart, which is how the fee arithmetic
drifted in #81.

**Disabled, not hidden.** A row that cannot be released still shows the button that would
release it, greyed out, with the reason in a tooltip. Hiding it would make a blocked row
indistinguishable from one already dealt with — the same mistake as filtering refunded
payments out of the ledger, which #82 rejected.

**The badge gained a fourth state.** "Pending" now means payable; money that is captured but
waiting on delivery reads **"Awaiting collection"** in grey. Without that, the queue said
"Pending" fourteen times and meant it five times.

**Proven by:** `PayoutSettlementTest` (11) — a payout is not released before collection, the
button is disabled with the reason, a collected job's button is enabled, a cancelled job is
never paid out and says why, the bulk action releases the collected row and leaves the other
alone, and the five pre-existing guarantees still hold: only an admin sees the screen, a shop
owner cannot settle their own payout, settling never changes the money, an already-paid row
keeps its date, and the screen lists captured money only.

**Verified against the seeded database:** the same fourteen rows now split five `Pending` and
nine `Awaiting collection`, and the test asserts that second label reaches the screen rather
than only the model.

**Still open:** the commission. A cancelled-after-approval job keeps its commission today,
which contradicts "commission on completed jobs only" in the same way. Fixing it properly
means moving the commission's trigger from settlement to collection — a larger change than
this one, and the reason #94 is filed as the payout half only.

---

## #96 — `composer dev` could not start on Windows, and it took the whole stack down

The `dev` script ran four processes under `concurrently --kill-others`: the web server, the
queue worker, Pail, and Vite. Three of those work on Windows. Pail does not — it calls
`pcntl_fork()` to tail the log, and PHP does not build `pcntl` for Windows at all. So the
fourth pane exited within a second of launch, and `--kill-others` did exactly what it says:
it killed the three that were working.

**The symptom is misleading.** Nothing in the terminal names the missing extension; the
window fills with four panes and then closes. The only record is a log line, written by
`EnsurePcntlIsAvailable` the moment the process starts:

```
local.ERROR: The [pcntl] extension is required to run Pail.
```

**Why `--kill-others` is still right.** The flag exists so Ctrl+C stops the stack instead of
leaving a queue worker and a Vite process orphaned on the ports. The bug is not the flag, it
is putting a process that cannot run on the target platform inside a group that dies
together.

**The fix.** `dev` runs the three portable processes; `dev:logs` runs Pail on its own. The
README says why Pail was moved out, because the obvious reading of the diff is "somebody
deleted the log pane" and the natural correction is to put it back — which would restore the
bug for every Windows clone.

**Verified on Windows 10, PHP 8.2.33, Node 22.22.2:** all three panes report ready and stay
up — `Server running on [http://127.0.0.1:8000]`, `Processing jobs from the [default] queue`,
`VITE v7.3.8 ready in 1103 ms` — and `/` and `/admin/login` both answer 200 while it runs.
`php -m` confirms `pcntl` is absent, so the original script could not have worked here.

---

## #98 — A killed dev server left `public/hot` behind and turned the suite red

`public/hot` is Vite's marker that a dev server is running: a one-line file holding its URL.
`npm run dev` writes it on boot and deletes it on a clean exit. Kill the process instead —
which is what a task runner does, and what I did twice while verifying #96 — and the file
survives, pointing at a port nothing is listening on.

**Two symptoms from one file.** A browser gets every page with its stylesheet and bundle
addressed to `http://[::1]:5173`, which is dead, so the page renders unstyled. And
`ConsoleTest > the console has its own stylesheet and no storefront shell` fails, because it
asserts `/build/assets/console-` appears in the HTML and a hot file swaps that for the
dev-server URL.

**Why the test is right and the environment is wrong.** That assertion is not incidental: it
is how the suite knows `console.css` reached the build. Relaxing it to accept a dev-server
URL would let a genuine regression — the console stylesheet missing from the build — pass
whenever a developer happened to have Vite open. The application should keep obeying the hot
file, because that is how Laravel ships; a test suite is not a browser, so during a test run
the file can only be stale.

**Fix.** `TestCase::setUp()` removes a leftover hot file before the app boots, so the suite is
deterministic regardless of what the developer last ran. The README carries the human-facing
half — delete `public/hot` when a page comes up unstyled — because the failure looks like a
broken template and nothing on the page names the cause.

**Verified:** with the file planted the test fails and the page serves dead asset URLs; the
guard removes it, the same test passes, and `public/hot` is gone afterwards. Full suite with
no hot file: **509 passed, 2,458 assertions**.

---

## Creating these on GitHub

All of these are on GitHub at
`https://github.com/pasinosvictorianodyanna-hub/TarpaLink/issues` with their labels.
This file is the source of truth; if you edit an issue there, edit it here too.
