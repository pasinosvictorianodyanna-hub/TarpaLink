# Backlog

Issues for the second developer, plus the two spine tasks that must be done together
before either half starts. Copy each block into a GitHub issue, or bulk-create them
with `gh` (see the bottom of this file).

Labels used: `spine` · `shop-panel` · `admin-panel` · `integration` · `billing` ·
`p0` (blocks someone) · `p1` (normal)

---

## 1. Schema: 12 tables, models and relationships

**Labels:** `spine` `p0`
**Blocks:** everything

The whole database in one migration set, so neither half invents its own version.

Tables: `users`, `shops`, `shop_users`, `services`, `price_tiers`, `price_options`,
`jobs`, `job_lines`, `proofs`, `payments`, `page_sections`, `site_settings`.

Rules that are not negotiable:

- Every shop-scoped table carries `shop_id`. Multi-tenancy is enforced by a global
  scope on the model, not by remembering to add `where` in every query.
- Money columns are `unsignedBigInteger` in centavos. Never `decimal`, never `float`.
- `job_lines` stores `unit_price`, `qty` and `matrix_version` — a snapshot, so a
  historical job never changes when the shop edits its matrix.
- `proofs` is versioned: `job_id`, `version`, `file_path`, `status`, `approved_at`.

**Acceptance:** `php artisan migrate:fresh --seed` runs clean on a fresh clone, and
`SHOW TABLES` returns exactly 12.

---

## 2. PriceCalculator + seeders

**Labels:** `spine` `p0`
**Blocks:** order builder (Dev A), price matrix (Dev B)

One service class matching `docs/CONTRACT.md` exactly. No controller computes prices
inline; everything goes through this.

Seeders must produce **six shops** with believable, *different* matrices — one cheap
tarpaulin shop, one premium, one that only does stickers, etc. Plus ~8 customers,
~15 jobs spread across every status, and a few proofs. The seed data is what lets the
customer side be built before the shop panel exists.

**Acceptance:** a tinker session calling `PriceCalculator::quote()` on a seeded shop
returns the numbers in `CONTRACT.md`; the order builder can be built without touching
the database.

---

## 3. Scaffold Filament: two panels, two guards

**Labels:** `shop-panel` `p0`

Two separate Filament panels in one app:

- `AdminPanel` at `/admin` — guard `web`, global scope
- `ShopPanel` at `/shop` — guard `shop`, scoped to the logged-in user's `shop_id`

Separate login pages, separate branding (admin = neutral, shop = shop's own name in
the top bar). A shop owner must not be able to reach `/admin`, and an admin must not
be silently dropped into a shop's scope.

**Acceptance:** logging in at `/shop` as a seeded shop owner shows only that shop's
records; hitting `/admin` returns 403.

---

## 4. Price matrix resource

**Labels:** `shop-panel` `p0`
**Unblocks:** Dev A's order builder

The most important task in this backlog. A Filament resource where a shop owner edits
their own prices: size tiers per service, per-unit rates, finishing options (eyelets,
hemming, lamination), quantity discount bands, and minimum order value.

Saving must bump `matrix_version` so old jobs keep their snapshot.

**Acceptance:** change the tarpaulin tier-B rate in the panel, reload the storefront,
and the order builder quotes the new price with no deploy and no cache clear.

---

## 5. Jobs resource + status transitions

**Labels:** `shop-panel` `p1`

Filament resource listing this shop's jobs. Read-only on price, editable on status.
Allowed transitions: `quoted → awaiting_proof → approved → in_production → ready →
collected`, plus `cancelled` from any pre-production state.

Illegal transitions are rejected server-side, not just hidden in the UI. Each change
writes to an activity log and fires the customer notification.

**Acceptance:** a job cannot jump from `quoted` to `collected`; the attempt returns a
validation error and leaves the row untouched.

---

## 6. Production board

**Labels:** `shop-panel` `p1`

Custom Filament page — a kanban of the shop's active jobs by status. Drag between
columns writes the same transition rules as task 5.

**Acceptance:** dragging a job from *On press* to *Finishing* updates the status, the
customer sees it on the job status page, and dropping into an illegal column snaps back.

---

## 7. Reports + payouts

**Labels:** `shop-panel` `p1`

Revenue by week, top services by revenue, and a payout ledger: gross, platform
commission, net, status, payout date. Export to CSV.

**Acceptance:** the ledger sums to gross − commission = net for every row, and the
month total matches a manual `SUM()` on `payments`.

---

## 8. Admin dashboard + shop approvals

**Labels:** `admin-panel` `p1`

Platform GMV, jobs, active shops, commission earned. A pending-approvals queue where
an admin approves or rejects a new shop (rejected shops cannot log in to the shop
panel). A live feed of the latest jobs across all shops.

**Acceptance:** approving a seeded pending shop lets that owner log in; rejecting
leaves them locked out with a reason.

---

## 9. Users, roles, policies

**Labels:** `admin-panel` `p1`

Four roles: Guest, Customer, Shop Owner, Administrator. Policies on every model so a
shop owner can only touch their own records, and only an administrator can approve
shops or edit site content.

**Acceptance:** a customer cannot open `/shop`; a shop owner cannot edit another
shop's price matrix; only an administrator can change `page_sections`.

---

## 10. CMS — site content resource

**Labels:** `admin-panel` `p1`
**Requirement:** C

Filament resource over `page_sections` and `site_settings`. Six sections: homepage
hero, announcement banner, featured shops, testimonials, FAQ, footer. Editing copy
and display order only — layout stays in Blade.

**Acceptance:** editing the hero headline in the panel changes the landing page after
a refresh, with no deploy. Deleting a section hides it from the site rather than
breaking the page.

---

## 11. PayMongo webhook + payout ledger

**Labels:** `integration` `p0`

Checkout creates a payment intent; the webhook is the only thing that may mark a job
as paid. Idempotent on `payment_intent_id` so a replayed webhook cannot double-credit.
Writes the commission split to the payout ledger.

Sandbox keys only. A failed webhook must not leave a job stuck in `pending` — retry
with backoff, and surface it on the admin dashboard if it fails three times.

**Acceptance:** replaying the same webhook twice creates one payment row, and the
payout ledger shows gross − 5% commission = net.

---

## 12. Billing: plans, subscriptions, invoices

**Labels:** `billing` `p1`

Three tiers — Starter (free, 5%), Growth (₱599, 3%), Pro (₱1,499, 1.5%). Adds
`plans`, `subscriptions`, `invoices`. A shop panel page showing the current plan, the
crossover math, and an upgrade path.

**Acceptance:** upgrading changes the commission rate applied to the next payout, and
the invoice history survives a plan change.

---

## Creating these on GitHub

With `gh` installed and authenticated:

```bash
gh auth login
# then paste each issue body as it is written above, or drive it from a loop
```

Without `gh`, create them by hand at
`https://github.com/<you>/<repo>/issues/new` — the headings above are the titles, and
the `Labels:` line maps to a label you create once under *Issues → Labels*.
