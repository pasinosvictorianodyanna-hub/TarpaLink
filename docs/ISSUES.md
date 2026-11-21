# Backlog

Issues for the second developer, plus the two spine tasks that must be done together
before either half starts.

> **Status:** all twelve issues are built. #1–#2 landed in `51b4ee3`, #3–#6 in the
> shop-panel commit (panels, price matrix, jobs, production board), and #7–#12 in
> the billing/panels commit (reports, payouts, approvals, policies, CMS, webhook,
> billing). They stay here as the reference for what exists — read them, do not
> rebuild them.

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
| Shops (each with a different matrix) | 6 |
| Services | 16 |
| Price tiers | 40 |
| Quantity-discount bands | 14 |
| Users (1 admin, 1 shop owner, 5 customers) | 7 |
| Jobs, spread across every status | 21 |
| Payments (approved and later) | 15 |
| Proofs | 5 |
| CMS page sections | 6 |

Every shop gets at least 2 jobs so the production board has something to drag, and
collected jobs carry past dates so reports have history. Shops are seeded on different
plans (Starter 5%, Growth 3%, Pro 1.5%) so the payout ledger is not all one rate.

**Acceptance:** a tinker session calling `PriceCalculator::quote()` on a seeded shop
returns the numbers in `CONTRACT.md`; the order builder can be built without touching
the database.

---

## 3. Scaffold Filament: two panels, one guard ✅ done

**Labels:** `shop-panel` `p0`
**State:** built

`AdminPanelProvider` at `/admin` and `ShopPanelProvider` at `/shop`, both on the
`web` guard. `User implements FilamentUser`; `canAccessPanel()` checks the panel
id, and the shop panel additionally requires an **active** shop, so a suspended
or pending shop locks its owner out even with the right role.

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

`/shop/reports` (revenue by week, top services, two CSV exports) and
`/shop/payouts` (month totals, the ledger, filterable, CSV export). Both read
`payouts`, whose rows are written by settlement — never recomputed at render time.
Admins move rows from pending to scheduled to paid on `/admin/payouts`; the
ledger amounts themselves are never editable.

Revenue by week, top services by revenue, and a payout ledger: gross, platform
commission, net, status, payout date. Export to CSV.

The commission rate is **not** a constant. Read it from `shops.commission_rate`
(basis points) via `$shop->commissionLabel()`; `payments.commission` already holds the
absolute centavo amount, snapshotted at the time of payment. Inkwell Press seeds at 3%
(Growth), Halftone Hub at 1.5% (Pro), the rest at 5% (Starter).

**Acceptance:** the ledger sums to gross − commission = net for every row, and the
month total matches a manual `SUM()` on `payments`. Changing a shop's plan does not
alter any existing payout row.

---

## 8. Admin dashboard + shop approvals ✅ done

**Labels:** `admin-panel` `p1`

Four dashboard widgets (`PlatformStats`, `PendingApprovals`, `FailedPayments`,
`LatestJobs`) plus the `ShopResource` table, which carries the same approve and
reject actions — both defined once in `ShopApprovalActions`. Rejection requires a
reason, stored on `shops.status_note` and cleared on approval. A seeded pending
shop (`karatula-print`, owner `nina@karatula.ph`) makes the queue non-empty on a
fresh clone.

Platform GMV, jobs, active shops, commission earned. A pending-approvals queue where
an admin approves or rejects a new shop (rejected shops cannot log in to the shop
panel). A live feed of the latest jobs across all shops.

Approval is `shops.status` — `pending → active`, or `suspended`. Commission earned is
the sum of `payments.commission`, not GMV × one rate, because shops sit on different
plans.

**Acceptance:** approving a seeded pending shop lets that owner log in; rejecting
leaves them locked out with a reason.

---

## 9. Users, roles, policies ✅ done

**Labels:** `admin-panel` `p1`

`app/Policies/` holds a policy per model over a shared `BasePolicy`
(`isAdmin` / `belongsToShop` / `managesShop`). Auto-discovery maps
`App\Models\X` to `App\Policies\XPolicy`; no registration needed.

The user form can attach a shop to a `shop_owner` through the `shop_users` pivot
(role `owner`). Without that row the panel refuses them — which is correct, and
is why the field exists.

Four roles: Guest, Customer, Shop Owner, Administrator. `users.role` holds
`customer|shop_owner|admin`; the `shop_users` pivot carries the per-shop
`owner|staff` role. Policies on every model so a shop owner can only touch their own
records, and only an administrator can approve shops or edit site content.

**Acceptance:** a customer cannot open `/shop`; a shop owner cannot edit another
shop's price matrix; only an administrator can change `page_sections`.

---

## 10. CMS — site content resource ✅ done

**Labels:** `admin-panel` `p1`
**Requirement:** C

`PageSectionResource` binds the `content` JSON column to a `Group` with
`statePath('content')`, and shows only the fields that belong to the section's
key. There is no create and no delete: hiding a section sets `status = hidden`,
which makes `PageSection::content()` fall back to the Blade defaults.

Filament resource over `page_sections` and `site_settings`. Six sections: homepage
hero, announcement banner, featured shops, testimonials, FAQ, footer. Editing copy
and display order only — layout stays in Blade and Vue.

**Acceptance:** editing the hero headline in the panel changes the landing page after
a refresh, with no deploy. Deleting a section hides it from the site rather than
breaking the page.

---

## 11. PayMongo webhook + payout ledger ✅ done

**Labels:** `integration` `p0`

`POST /webhooks/paymongo` → `PayMongoWebhookController` (HMAC signature check,
open only outside production when no secret is set) → `PayMongoWebhookHandler`.
Two idempotency layers: `payment_webhooks.event_id` unique, and
`payouts.payment_id` unique. Failures are recorded, retried by
`RetryPaymentWebhook` after 1 and 5 minutes, and surfaced by the
`FailedPayments` widget once three attempts have failed.

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

`plans` / `subscriptions` / `invoices` tables, `Billing` service, an admin
`PlanResource` + `InvoiceResource`, and `/shop/billing` for the owner. Crossover
points are computed from the fee and rate, not typed in — the tests assert
₱29,950 and ₱60,000, which is where the documented numbers come from.

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

## Creating these on GitHub

All twelve are already on GitHub at
`https://github.com/pasinosvictorianodyanna-hub/TarpaLink/issues` with their labels.
This file is the source of truth; if you edit an issue there, edit it here too.
