# Backlog

Issues for the second developer, plus the two spine tasks that must be done together
before either half starts.

> **Status:** every issue **#1**–**#12** is built. They stay here as the reference for
> what exists — read them, do not rebuild them.
> **Next up: the documentation set** (SRS, ER / use case / context / architecture
> diagrams, schema), which the brief requires and which is still outstanding.
> **#13**–**#17** are tracked on their own branches; **#16** is recorded below because it
> added a second Vue island and a schema column worth knowing about.

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

## Creating these on GitHub

All twelve are already on GitHub at
`https://github.com/pasinosvictorianodyanna-hub/TarpaLink/issues` with their labels.
This file is the source of truth; if you edit an issue there, edit it here too.
