# Software Requirements Specification — TarpaLink

**Version 1.0 · Web Systems and Technologies 2 · System Integration and Architecture 1**

> **Written against:** `main` plus the profile island (#16). Requirements delivered by
> the shop-dashboard branch (#17) are marked in the Issue column and land with it; every
> other requirement in this document is implemented in this repository today.

---

## 1. Purpose and scope

TarpaLink is a job-order platform for small print shops in the Philippines. A customer
finds a shop, configures a print job against that shop's own price matrix, pays, approves
a proof, and collects the finished print at the counter. The shop gets a queue, a
production board, and a payout ledger that tells it exactly what the platform owes it.

**It is not e-commerce.** There is no cart, no shipping, no inventory, no SKU. The unit of
work is a **job**: one customer, one shop, one service, one specification, one price
agreed before production starts. A tarpaulin is quoted by the square foot; stickers,
flyers and shirts are quoted per piece. Both are priced from tables the shop maintains
itself, not from a national price list.

**In scope:** the customer storefront, the quoting engine, the job lifecycle, the shop
panel, the admin panel, payment capture through PayMongo, the payout ledger, subscription
billing, and the CMS that drives the public pages.

**Out of scope:** delivery and courier integration, a mobile app, multi-currency,
marketplace-wide promotions, and any form of stock control.

---

## 2. Definitions

| Term | Meaning |
|---|---|
| **Job** | A single print order. The primary entity of the system. |
| **Service** | What the shop prints: tarpaulin, stickers, flyers, DTF shirts. |
| **Price tier** | A size band for one service, with a unit price. A 3×3 ft sticker is priced by the tier that contains it, not by a formula. |
| **Price option** | An add-on with a flat price: eyelets, hemming, a rush fee. |
| **Matrix version** | A short fingerprint of a shop's tier rows at the moment of quoting. Stored on the job, so a later price change does not rewrite an agreed price. |
| **Centavo** | The unit of money in the database. All amounts are integer centavos; ₱2,036.80 is stored as `203680`. |
| **Basis point** | The unit of a rate. `300` = 3%. Commission rates are basis points, never decimals. |
| **Settlement** | Paying the shop. Tracked separately from the customer paying us — see §6.5. |
| **Proof** | The artwork file the shop uploads for the customer to approve before printing. |

---

## 3. Actors

| Actor | Description | Enters through |
|---|---|---|
| **Customer** | Buys print jobs. May browse without an account; must sign in to check out. | Storefront (`/`) |
| **Shop owner** | Runs one or more print shops. Maintains the price matrix, works the queue, releases payouts. | Shop panel (`/shop`) |
| **Shop staff** | Works the queue and the board, cannot change prices or billing. | Shop panel (`/shop`) |
| **Administrator** | Approves shops, manages users and roles, edits site content, monitors money. | Admin panel (`/admin`) |
| **PayMongo** | Payment processor. Creates intents; calls our webhook to confirm them. | `POST /webhooks/paymongo` |

A user has exactly one `users.role` — `customer`, `shop_owner`, or `admin` — but a shop
owner is linked to shops through `shop_users`, not through the role, so one person can
own two shops and a shop can have two staff.

---

## 4. System context

TarpaLink sits between three humans and one payment processor. The diagrams are in
[ARCHITECTURE.md](ARCHITECTURE.md); the data model is in [SCHEMA.md](SCHEMA.md).

---

## 5. Functional requirements

Each requirement is numbered and traced to the issue that delivered it and the test that
proves it. "The system shall" language is deliberate: these are testable statements, not
feature names.

### 5.1 Accounts and access — FR-10 …

| ID | Requirement | Issue |
|---|---|---|
| FR-10 | The system shall let a customer register and sign in with email and password, and shall keep the session across requests. | #9 |
| FR-11 | The system shall assign exactly one role per user and shall refuse panel access on role mismatch, returning **403** rather than redirecting to a login page. | #3 |
| FR-12 | The system shall let a signed-in user view and change their own name, mobile number, email, and pickup area without a page reload. | #16 |
| FR-13 | The system shall let a signed-in user change their password by supplying the current one, and shall record `password_changed_at`. | #16 |
| FR-14 | The system shall refuse a duplicate email at registration **and** at profile update, while allowing a user to keep their own address. | #16 |

### 5.2 Directory and storefront — FR-20 …

| ID | Requirement | Issue |
|---|---|---|
| FR-20 | The system shall list approved shops only, searchable by shop name, description or service name and filterable by city, each showing rating and completed jobs. | #2 |
| FR-21 | The system shall render a shop profile with its services and starting prices, computed from that shop's live tiers. | #2 |
| FR-22 | The system shall let a visitor open the job builder for a shop and configure size, quantity and options. | #4 |
| FR-23 | The system shall render the landing page from CMS content, so an editor can change the hero, banner, FAQ, testimonials and footer blurb without a deploy. | #10 |

### 5.3 Quoting — FR-30 …

| ID | Requirement | Issue |
|---|---|---|
| FR-30 | The system shall price a job **only** through `PriceCalculator::quote()`; no controller, view or panel widget shall compute a price. | #2 |
| FR-31 | The system shall price by the tier that contains the requested size, and shall reject a size that falls outside every tier with `ServiceUnavailable` rather than guessing. | #2 |
| FR-32 | The system shall multiply `unit_price` by area when the service unit is `sqft`, and by quantity when it is `pc`. | #2 |
| FR-33 | The system shall apply the single largest quantity threshold the order qualifies for, and shall never produce a negative total. | #2 |
| FR-34 | The system shall return line items, subtotal, discount, total, and `matrix_version` from every quote. | #2 |
| FR-35 | The system shall expose `POST /api/quote` for live preview, and shall recompute the price server-side at checkout; if the two differ, the server's number shall win and the client shall be told. | #4 |
| FR-36 | The system shall record `matrix_version` on the job so that a later price edit does not alter an agreed price. | #4 |

### 5.4 Job lifecycle — FR-40 …

| ID | Requirement | Issue |
|---|---|---|
| FR-40 | The system shall move a job only along the transitions in `JobWorkflow::TRANSITIONS`: quoted → awaiting_proof → approved → in_production → ready → collected, with `cancelled` reachable from the first three. | #5 |
| FR-41 | The system shall reject any illegal transition with an exception, not a silent no-op. | #5 |
| FR-42 | The system shall refuse cancellation once a job is `in_production`, because material has been consumed. | #5 |
| FR-43 | The system shall write one `job_activities` row per transition, recording who moved it, from what, to what, and when. | #5 |
| FR-44 | The system shall stamp `collected_at` when and only when a job reaches `collected`, and shall derive on-time rate from `collected_at <= due_at`. | #17 |
| FR-45 | The system shall show a customer their own jobs only, and shall return **404** — not 403 — for another customer's job. | #13 |
| FR-46 | The system shall record each proof as a numbered version with a status, and shall tell the customer on their account page when a proof is waiting for them. | #5 |

### 5.5 Shop operations — FR-50 …

| ID | Requirement | Issue |
|---|---|---|
| FR-50 | The system shall give the shop a Kanban board with one column per working status, where dropping a card moves the job through `JobWorkflow`. | #6 |
| FR-51 | The system shall refuse a board drop into an illegal column, and shall refuse a drop of another shop's job. | #6 |
| FR-52 | The system shall scope every shop-panel query to the signed-in user's current shop, so a shop can never see another shop's jobs, services or payments. | #3 |
| FR-53 | The system shall let a shop owner edit a service's tiers and options, and shall increment the matrix version so the next quote reflects the change. | #4 |
| FR-54 | The system shall show the shop a dashboard with today's revenue, open jobs, jobs due today, average job value, and a seven-day chart. | #17 |
| FR-55 | The system shall surface jobs that have waited too long — proof unapproved 6 h, quote unconfirmed 24 h, ready but uncollected 48 h, or past due — longest wait first. | #17 |

### 5.6 Money — FR-60 …

| ID | Requirement | Issue |
|---|---|---|
| FR-60 | The system shall create a PayMongo payment intent at checkout and store it against the job before redirecting the customer. | #11 |
| FR-61 | The system shall verify the webhook signature and shall reject an unsigned or wrongly signed request. | #11 |
| FR-62 | The system shall be idempotent: replaying the same webhook shall create exactly one `payments` row and change no amount. | #11 |
| FR-63 | The system shall record a failed attempt with its error and count, and shall flag a payment for attention after three failures. | #11 |
| FR-64 | The system shall store the commission as an absolute centavo amount on the payment row at settlement time. | #7 |
| FR-65 | The system shall track payout as a **second, independent** status: the customer paid us (`payments.status`) and we paid the shop (`payments.payout_status`). | #7 |
| FR-66 | The system shall compute a payout as gross − commission = net, and the ledger shall balance to zero discrepancy. | #7 |
| FR-67 | The system shall never rewrite a settled payout when a shop changes plan; only future payments use the new rate. | #7 |
| FR-68 | The system shall let a shop export its payout ledger as CSV, and shall refuse the export to anyone who does not own that shop. | #7 |
| FR-69 | The system shall bill subscriptions on three tiers — Starter (free, 5%), Growth (₱599, 3%), Pro (₱1,499, 1.5%) — and shall show the crossover point where the next tier becomes cheaper. | #12 |

### 5.7 Administration — FR-70 …

| ID | Requirement | Issue |
|---|---|---|
| FR-70 | The system shall let an administrator approve, reject with a reason, suspend and reinstate a shop, writing the reason and the acting user in one place. | #8 |
| FR-71 | The system shall block a pending or suspended shop's owner from the shop panel while leaving their account intact. | #8 |
| FR-72 | The system shall give an administrator a dashboard of platform-wide money and volume, and a list of payments needing attention. | #8 |
| FR-73 | The system shall let an administrator create and edit users and assign roles. | #9 |
| FR-74 | The system shall let an administrator edit CMS sections and settings, and hiding or deleting a section shall remove the block from the page rather than leave a gap. | #10 |

---

## 6. Business rules

These are invariants. They are enforced in one place each, and a change to them is a
design decision, not a bug fix.

**BR-1 — Money is integer centavos, everywhere.** No float, no decimal, no `round()`
after the fact. Formatting happens once, in the view. Rates are basis points.

**BR-2 — The shop is always an argument.** There is no global price. A shop that has not
configured a service has no price, and the system says so.

**BR-3 — The customer pays the shop's price.** The platform adds nothing on top.
Commission is deducted from the shop's payout, never charged to the customer.

**BR-4 — History is append-only.** A settled payment, an issued invoice and a recorded
transition are facts. They are never updated to reflect a new policy.

**BR-5 — One place per decision.** Prices in `PriceCalculator`. Transitions in
`JobWorkflow`. Plan changes in `Billing`. Approval in `ShopApproval`. Payment capture in
`PayMongo`. If a second place starts deciding the same thing, the code is wrong.

**BR-6 — No blanket admin bypass.** There is no `Gate::before`. Sixteen explicit policies
answer every question, so "can an admin do this?" is written down rather than implied.

**BR-7 — Tenant isolation is a query condition, not a UI filter.** Scoping is applied to
the query, so a forged URL returns 404 rather than someone else's data.

**BR-8 — The server decides the price, the state, and the money.** The client may preview
and may optimistically render, but it never becomes the source of truth.

---

## 7. Non-functional requirements

| ID | Requirement | How it is met |
|---|---|---|
| NFR-1 | **Security.** Every panel route is authorised by a policy; every write is CSRF-protected except the signed webhook. | 16 policies, `VerifyCsrfToken` excepted only for `/webhooks/paymongo` |
| NFR-2 | **Tenant isolation.** No query in the shop panel may cross shop boundaries. | `ScopesToShop`, policy checks, tests that assert 404 on forged ids |
| NFR-3 | **Integrity.** A price, a transition or a payout must not be derivable two ways. | Single-service rule (BR-5), tests comparing against independent SQL |
| NFR-4 | **Performance.** A quote is computed in a single request with no N+1; the dashboard aggregates in SQL. | Eager loading, aggregate queries, no per-row queries in widgets |
| NFR-5 | **Portability.** Local development runs on SQLite; production on MySQL 8, with no code change beyond `DB_CONNECTION`. | Portable migrations, no raw SQL dialect dependencies |
| NFR-6 | **Accessibility.** Forms are keyboard-operable, inputs carry labels, errors are associated with their field, disabled controls remain legible. | Semantic markup, `aria-invalid`, contrast-checked disabled states |
| NFR-7 | **Maintainability.** A new interactive screen is one registry line, not a router. | Vue island registry in `resources/js/app.js` |
| NFR-8 | **Recoverability.** A failed webhook is retried with backoff and surfaced to the administrator rather than lost. | `payments.attempts`, `last_error`, `failed_at`, queue |
| NFR-9 | **Auditability.** Who changed a job's state, who approved a shop, and when, are all recorded. | `job_activities`, `shops.approved_by`, `status_changed_by` |

---

## 8. Data requirements

Seventeen domain tables, sixteen models (`shop_users` is a pivot with no model of its
own), sixteen policies. The full dictionary, enumerations and invariants are in
[SCHEMA.md](SCHEMA.md).

---

## 9. External interfaces

| Interface | Direction | Protocol | Failure behaviour |
|---|---|---|---|
| **PayMongo** | out (create intent) / in (webhook) | HTTPS + JSON, signature header | Unsigned calls rejected; failed processing retried with backoff and flagged after three attempts |
| **SMTP** | out | SMTP | Not yet wired — see §11 |
| **Maps** | out | Tiles / geocoding | Coordinates are stored; no live geocoding yet |
| **File storage** | local disk | Filesystem | Proof files are stored under `storage/app`; S3 is a drop-in |

---

## 10. Traceability

Every requirement is proven by a test that exists in this repository. The file name is
given so a reader can open it; several issues are covered by more than one file.

| Issue | Requirements | Tests |
|---|---|---|
| #2 | FR-30 … FR-34 | `CheckoutSmokeTest` (size outside every tier, shop with no catalogue) |
| #3 | FR-11, FR-52 | `AuthorizationTest`, `ShopPanelTest` |
| #4 | FR-22, FR-35, FR-36, FR-53 | `ShopPanelTest::editing_a_tier_changes_the_next_quote_and_the_matrix_version`, `CheckoutSmokeTest` |
| #5 | FR-40 … FR-43, FR-46 | `ShopPanelTest` (illegal skip, legal transition recorded, cancel blocked on press) |
| #6 | FR-50, FR-51 | `ShopPanelTest` (board drop, illegal column, another shop's job) |
| #7 | FR-64 … FR-68 | `ReportsTest` |
| #8 | FR-70 … FR-72 | `AdminDashboardTest` |
| #9 | FR-10, FR-73 | `AuthorizationTest` (incl. `there_is_no_blanket_administrator_bypass`) |
| #10 | FR-23, FR-74 | `CmsTest` |
| #11 | FR-60 … FR-63 | `PayMongoWebhookTest` |
| #12 | FR-69 | `BillingTest` |
| #13 | FR-45 | `AuthorizationTest::a_customer_cannot_read_another_customers_job` |
| #16 | FR-12 … FR-14 | `ProfileTest` |
| #17 | FR-44, FR-54, FR-55 | `ShopDashboardTest` — on `feature/17-shop-dashboard`, not yet merged |

Two honest notes about this table:

- **There is no rate limiting on login.** It is the one place where a requirement a
  reviewer would expect to find is simply absent, and it is listed in §11 rather than
  quietly dropped from the list.
- **`AuthorizationTest` is the spine of the traceability claim.** It is the file that
  proves BR-6 (no `Gate::before`) and BR-7 (404, not 403, across tenants) rather than
  assuming them — including a test literally named
  `there_is_no_blanket_administrator_bypass`.

---

## 11. Known gaps

Stated plainly, because a specification that claims completeness is not credible:

- **SMTP is not wired.** No transactional email yet: no receipt, no proof notification,
  no password reset mail.
- **Maps are coordinates, not geocoding.** A shop's `lat`/`lng` are seeded; there is no
  address-to-coordinate lookup and no map view.
- **Object storage is local disk.** Fine for one server, not for a fleet.
- **The checkout and account screens are still Blade forms**, not islands. They work;
  they simply reload the page where the builder does not.
- **Admin integrations screen does not exist** — PayMongo settings are environment
  variables, not an editable screen.
- **Deleting a shop cascades to its payments.** No soft deletes exist, so an
  administrator's delete reaches settled money. The operating rule is "suspend, never
  delete"; the schema should enforce it with `restrictOnDelete`.
- **No rate limiting on login.** Repeated password guesses are not throttled.
- **The customer cannot approve a proof in the app.** Proofs are recorded, versioned and
  surfaced ("the proof is waiting"), but approval happens outside the system — a message
  or a phone call — and the shop moves the job on. The lifecycle supports the step; the
  screen for it does not exist.

---

## 12. Acceptance

A requirement is done when all four hold:

1. The behaviour exists and is reachable by the actor who needs it.
2. A test asserts the behaviour **independently** of the implementation — a payout test
   compares against a plain `SUM()`, not against the ledger's own arithmetic.
3. The rule lives in one place, and that place is named in BR-5.
4. The screen matches `design/prototype.html`, or the difference is deliberate and
   written down.
