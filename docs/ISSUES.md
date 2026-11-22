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

## Creating these on GitHub

All of these are on GitHub at
`https://github.com/pasinosvictorianodyanna-hub/TarpaLink/issues` with their labels.
This file is the source of truth; if you edit an issue there, edit it here too.
