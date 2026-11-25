# 19. Data model

## 19.1 The core

Fifteen tables. The diagram groups them by what they are about — identity, tenancy, catalogue, the
job, and money — because a diagram that groups by nothing is a diagram of boxes.

[[FIG:er|Figure 19.1 — The core model: identity, tenancy, catalogue, the job, and the audit trail.]]

## 19.2 Billing, content and configuration

The remaining four tables do not join the core. The billing chain is a small model of its own — a
plan, the subscription that holds a shop to it, and the invoices that follow — and the three
content and configuration tables are keyed by string and reference nothing.

[[FIG:er-satellite|Figure 19.2 — The billing chain, and the tables that are keyed by string.]]

## 19.3 The relationships

| Parent | Child | Cardinality | What it means |
|---|---|---|---|
| `users` | `shop_users` | 1 : N | An account may be attached to several shops |
| `shops` | `shop_users` | 1 : N | A shop may have several staff |
| `shops` | `services` | 1 : N | What this shop prints |
| `shops` | `quantity_discounts` | 1 : N | This shop's own discount bands |
| `shops` | `jobs` | 1 : N | The work this shop has taken |
| `shops` | `subscriptions` | 1 : N | One live subscription per shop in practice, kept as a history |
| `shops` | `invoices` | 1 : N | Every bill raised against the shop |
| `services` | `price_tiers` | 1 : N | The size bands that price this service |
| `services` | `price_options` | 1 : N | The add-ons that price this service |
| `services` | `jobs` | 1 : N | What kind of work the job is |
| `users` | `jobs` | 1 : N | Who ordered it |
| `users` | `job_activities` | 1 : N | Who moved it, or who wrote the note |
| `jobs` | `job_lines` | 1 : N | The frozen price breakdown |
| `jobs` | `proofs` | 1 : N | Numbered versions the shop sent back |
| `jobs` | `artworks` | 1 : N | Append-only versions the customer sent |
| `jobs` | `payments` | 1 : N | Normally one; a re-attempt after a failure is a second row |
| `jobs` | `job_activities` | 1 : N | The audit trail |
| `plans` | `subscriptions` | 1 : N | Which tier a subscription is on |
| `subscriptions` | `invoices` | 1 : N | Which period each bill covers |
| `shop_requests` | `shops` | N : 1 | A request becomes at most one shop |
| `shop_requests` | `users` | N : 1 | The applicant's account, when one exists |

Three of these carry a rule that the diagram cannot show:

- **`jobs → payments` is one-to-many, but the system treats it as one.** A second row exists only
  when a first attempt failed and was retried; two *settled* payments on one job would be a defect,
  and `PayMongoWebhookTest` asserts that a replayed webhook changes nothing (FR-62).
- **`jobs → artworks` is append-only.** The newest row is the file to print, which is why the index
  is `(job_id, id)` and not `(job_id, created_at)`: two uploads a second apart are normal, and a
  timestamp cannot order them.
- **`shop_requests → shops` is nullable in both directions at different times.** Before a decision
  the request has no shop; after one, the shop exists and the request points at it. That is why the
  request is not simply a shop with `status = pending`.

# 20. Database schema

## 20.1 Identity and tenancy

| Table | Purpose | Key columns | Constraints |
|---|---|---|---|
| `users` | Every account, whatever its surface | `name`, `email` (unique), `password`, `role`, `phone`, `city`, `password_changed_at` | `role` is one value: `customer`, `shop_owner`, `staff`, `admin` |
| `password_reset_tokens` | Single-use reset links | `email` (primary), `token` (hashed), `created_at` | The token is a hash; nothing can read it back |
| `shop_requests` | Applications that are not yet shops | `email`, `shop_name`, `city`, `status`, `user_id`, `shop_id`, `approved_by`, `reason` | One open request per account; a refused one keeps its reason |
| `shops` | The trading entity | `name`, `slug` (unique), `city`, `status`, `plan`, `commission_rate`, `approved_by`, `approved_at`, `status_reason`, `status_changed_by` | `status`: `pending` · `active` · `suspended`; `commission_rate` in basis points |
| `shop_users` | The attachment that grants access | `shop_id`, `user_id`, `role` | Composite unique; `role` is `owner` or `staff` |

The attachment is the permission. `users.role = shop_owner` grants nothing on its own; the console
admits a user only when a `shop_users` row points at an **active** shop. That is why an approved
applicant can trade the moment the transaction commits, and why a pending one waits in a room
rather than receiving a 403 (FR-102).

## 20.2 Catalogue

| Table | Purpose | Key columns | Constraints |
|---|---|---|---|
| `services` | What a shop prints | `shop_id`, `name`, `unit`, `active` | `unit`: `sqft` or `pc` — it decides whether the price multiplies by area or by quantity |
| `price_tiers` | The size bands | `service_id`, `label`, `min_width`, `max_width`, `min_height`, `max_height`, `unit_price` | `unit_price` in centavos; a size outside every band is refused, not extrapolated |
| `price_options` | Flat add-ons | `service_id`, `name`, `price` | `price` in centavos |
| `quantity_discounts` | Per-shop volume bands | `shop_id`, `min_qty`, `percent` | `percent` is a whole percent, not basis points — the one place that is true, and the column is named for it |

`quantity_discounts.percent` being a whole percent while every rate elsewhere is basis points is a
deliberate inconsistency, recorded here so it is not "fixed" by a reader who assumes it is a bug: a
discount band is entered by a shop owner as "10%", and storing 1000 basis points would have meant
converting on both the form and the calculator for a value that never needs sub-percent precision.

## 20.3 The job

| Table | Purpose | Key columns | Constraints |
|---|---|---|---|
| `jobs` | The order | `reference` (unique), `shop_id`, `user_id`, `service_id`, `status`, `spec`, `subtotal`, `discount`, `total`, `matrix_version`, `pickup_city`, `notes`, `due_at`, `collected_at` | `status` is the seven-value enum; `collected_at` is stamped by `JobWorkflow` and by nothing else |
| `job_lines` | The frozen breakdown | `job_id`, `label`, `qty`, `unit_price`, `amount` | `SUM(amount)` over a job must equal `jobs.total` — asserted, not assumed |
| `proofs` | What the shop sent back | `job_id`, `version`, `file_path`, `status`, `note`, `approved_at` | `(job_id, version)` unique; `status`: `sent` · `approved` · `rejected` |
| `artworks` | What the customer sent | `job_id`, `uploaded_by`, `file_path`, `original_name`, `mime`, `bytes` | Append-only; index `(job_id, id)` |
| `job_activities` | The audit trail | `job_id`, `user_id`, `kind`, `source`, `from_status`, `to_status`, `note`, `body`, `source_ref` | A `kind = transition` row is written only by `JobWorkflow` and always names a legal move; a `kind = note` row carries no statuses |

`jobs.spec` is JSON — width, height, quantity and option ids as the customer configured them — while
the priced result is frozen into `subtotal`, `discount` and `total`. The specification can be
re-read to reprint a job; the total cannot be recomputed, because the tiers may have changed since
(FR-36).

## 20.4 Money

`payments` is the table to understand, because it carries **two lifecycles**: whether the customer
paid us, and whether we paid the shop.

| Column | Type | Notes |
|---|---|---|
| `job_id` | FK | |
| `provider` | string | `paymongo` |
| `intent_id` | string, unique | The idempotency key. A replayed webhook finds this row and stops |
| `amount` | integer | What the customer paid, centavos — the "gross" of BR-3 |
| `refunded_amount` | integer | How much of `amount` has gone back, cumulatively. 0 until a refund |
| `processing_fee` | integer | What the processor kept. The **shop** bears it: it comes off the payout, like commission |
| `commission` | integer | What the platform kept — an absolute amount, written at settlement |
| `commission_refunded` | integer | The part of `commission` given back with the refunds |
| `status` | string | Did the **customer** pay: `pending` · `paid` · `failed` · `refunded`. `refunded` means the whole amount went back; a partial refund leaves this at `paid` |
| `payout_status` | string | Did **we** pay the shop: `pending` · `paid` |
| `refunded_at`, `payout_date` | timestamps | |
| `attempts`, `last_error`, `failed_at` | integer / text / timestamp | Three attempts flags the row on the admin dashboard |

Net to the shop:

```
net = (amount − refunded_amount) − (commission − commission_refunded) − processing_fee
```

Because both deductions live on this row, the subtraction is written once — `Payment::net()`, and
`Payment::netFrom()` for the payout CSV, which streams flat rows through a cursor and never builds
a model. The reports screen, the export and every per-payment figure go through those two, which is
why a test can compare the ledger's total against a plain `SUM()` and expect them to agree.

**A refund is an accumulation, not an edit** (BR-4). `refunded_amount` grows and
`commission_refunded` grows with it, so the amounts the payment was settled at are never rewritten.
The commission reverses in proportion, computed as a cumulative target rather than a per-refund
slice, so three thirds of a payment reverse the whole commission and not 99.8% of it.

| Table | Purpose | Key columns |
|---|---|---|
| `plans` | The three tiers | `code` (`starter` · `growth` · `pro`), `name`, `price`, `commission_rate`, `features`, `sort` |
| `subscriptions` | A shop's plan over time | `shop_id`, `plan_id`, `status`, `started_at`, `ends_at` |
| `invoices` | What a shop owes the platform | `shop_id`, `subscription_id`, `number` (unique), `amount`, `period_start`, `period_end`, `status`, `issued_at`, `due_at`, `paid_at`, `paid_by` |

A plan change updates `shops.plan` and `shops.commission_rate` **and** issues an invoice, in one
transaction, because a shop whose rate changed but whose bill did not is a shop being undercharged
by a bug rather than by a decision.

## 20.5 Content and configuration

| Table | Purpose | Key columns | Note |
|---|---|---|---|
| `page_sections` | The CMS blocks that drive the public pages | `key` (unique), `title`, `body`, `visible`, `position` | Hiding a section removes the block rather than leaving a gap (FR-74) |
| `site_settings` | Single values: site name, contact, map defaults | `key`, `value` | Not for secrets — see the next row |
| `integrations` | Rotating credentials | `key`, `payload` (`encrypted:array`), `updated_by` | Encrypted at rest, and the screen states whether the value in force came from here or from the environment |

`integrations` exists because a credential has to be changeable without a redeploy, and because a
secret in `site_settings` would be a secret in a table whose screen lists every row. It is encrypted
with the application key, so a database dump alone does not disclose it.

# 21. Wireframes

Five screens, drawn from the implementation rather than from a designer's intention. Where the
implementation and the original prototype differ, the difference is noted — that is condition 4 of
chapter 13.

## 21.1 The directory

[[FIG:wireframe-directory|Figure 21.1 — The storefront directory: search, city filters, shop cards, and the map measured from the customer's address.]]

The map is a boundary item, not a component: tiles are requested and rendered, and the panel
degrades to a labelled box when they do not arrive (FR-24). The card carries the two figures a
customer actually chooses on — rating and completed jobs — because a shop with no history is a real
choice and should be visibly one.

**Difference from the prototype:** the prototype showed a price range per card. The
implementation shows a starting price only, because a range implied a ceiling the shop had not
promised, and a customer who configured a large job then saw a higher number would have been
misled by the card rather than by the shop.

## 21.2 The builder

[[FIG:wireframe-builder|Figure 21.2 — The shop profile and the job builder. Every change to the specification reprices on the server.]]

This screen is the Vue island that carries the most risk, because it is the one place a number is
displayed before it is committed. Three things in the drawing are requirements rather than design
choices: the quote is labelled as computed on the server (FR-30), the total is re-checked at
checkout (FR-37), and the form works as a plain POST when the bundle does not load (FR-25).

## 21.3 Checkout

[[FIG:wireframe-checkout|Figure 21.3 — Checkout. The total shown here is the total that is compared with a fresh quote before any row is written.]]

The step rail is drawn because it is the only place the customer is told that a proof comes next.
The payment button leaves the site — PayMongo hosts the card form — and the customer returns to
the job, which is why the job reference is in the return address rather than in a session.

## 21.4 The production board

[[FIG:wireframe-board|Figure 21.4 — The shop console's production board: one column per working status, and a design badge on every card (FR-114).]]

**Difference from the prototype:** the prototype had no design badge. It was added because the shop
had to open every job to find out whether the customer's file had arrived, and mail is best-effort —
so "no email" did not mean "no file". The badge answers the question on the card, and a paid job
with no artwork is labelled as waiting on the customer rather than as a proof the shop has not sent.

## 21.5 The administrative dashboard

[[FIG:wireframe-admin|Figure 21.5 — The administrative dashboard: earned commission, held money, payments needing attention, and the request queue.]]

The first two tiles are the visible consequence of BR-10. "Commission earned" counts only collected
jobs; "held on open jobs" counts the rest, and shows it as held rather than as revenue. Before that
distinction existed the dashboard reported a single figure that included money the platform would
have to give back.
