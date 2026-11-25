# 6. Functional requirements

Each requirement is numbered, stated as a testable sentence, and traced to the issue that
delivered it. "The system shall" is deliberate: a requirement that cannot be asserted by a test is
a wish, and chapter 11 names the test for every row below.

There are **89** functional requirements in ten groups. The group numbers are not sequential
because they follow the order issues were adopted, not the order the chapters appear; §6.6 carries
FR-104 to FR-113 for that reason.

## 6.1 Accounts and access

| ID | Requirement | Issue |
|---|---|---|
| FR-10 | The system shall let a customer register and sign in with email and password, and shall keep the session across requests. | #9 |
| FR-11 | The system shall assign exactly one role per user and shall refuse panel access on role mismatch, returning **403** rather than redirecting to a login page. | #3 |
| FR-12 | The system shall let a signed-in user view and change their own name, mobile number, email, and pickup area without a page reload. | #16 |
| FR-13 | The system shall let a signed-in user change their password by supplying the current one, and shall record `password_changed_at`. | #16 |
| FR-14 | The system shall refuse a duplicate email at registration **and** at profile update, while allowing a user to keep their own address. | #16 |
| FR-15 | The system shall throttle repeated failed sign-ins per email **and** per caller, refusing further attempts until the window expires, and shall not reveal whether an address is registered while throttled. | #61 |
| FR-16 | The system shall let a user reset a forgotten password by email link, shall invalidate existing "remember me" sessions on success, and shall answer identically whether or not the address is registered. | #61 |

## 6.2 Directory and storefront

| ID | Requirement | Issue |
|---|---|---|
| FR-20 | The system shall list approved shops only, searchable by shop name, description or service name and filterable by city, each showing rating and completed jobs. | #2 |
| FR-21 | The system shall render a shop profile with its services and starting prices, computed from that shop's live tiers. | #2 |
| FR-22 | The system shall let a visitor open the job builder for a shop and configure size, quantity and options. | #4 |
| FR-23 | The system shall render the landing page from CMS content, so an editor can change the hero, banner, FAQ, testimonials and footer blurb without a deploy. | #10 |
| FR-24 | The system shall resolve a typed address to coordinates and show the directory measured from it, and shall say so plainly when the lookup fails or is switched off rather than leaving the map at a default. | #63 |
| FR-25 | The system shall complete checkout without a page reload, shall show a rejected field beside the input that caused it, and shall keep working as a plain form submission when the bundle does not load. | #63 |

## 6.3 Quoting

| ID | Requirement | Issue |
|---|---|---|
| FR-30 | The system shall price a job **only** through `PriceCalculator::quote()`; no controller, view or panel widget shall compute a price. | #2 |
| FR-31 | The system shall price by the tier that contains the requested size, and shall reject a size that falls outside every tier with `ServiceUnavailable` rather than guessing. | #2 |
| FR-32 | The system shall multiply `unit_price` by area when the service unit is `sqft`, and by quantity when it is `pc`. | #2 |
| FR-33 | The system shall apply the single largest quantity threshold the order qualifies for, and shall never produce a negative total. | #2 |
| FR-34 | The system shall return line items, subtotal, discount, total, and `matrix_version` from every quote. | #2 |
| FR-35 | The system shall expose `POST /api/quote` for live preview, and shall recompute the price server-side at checkout; if the two differ, the server's number shall win and the client shall be told. | #4 |
| FR-36 | The system shall record `matrix_version` on the job so that a later price edit does not alter an agreed price. | #4 |
| FR-37 | The system shall send the total it displayed to the server with the order, and shall refuse to place the order — before any row is written — if the server's fresh quote disagrees, returning the new total so the customer can confirm it deliberately. | #63 |

FR-37 is the requirement that makes the other seven matter. A preview that is merely "usually
right" is worse than no preview, because the customer has already decided on the number. So the
displayed total travels with the order, and a disagreement is a refusal rather than a silent
correction — the customer is shown the new total and asked to confirm it (the `409` in
`CheckoutController`), which is the only honest way to change a price someone has already seen.

## 6.4 Job lifecycle

| ID | Requirement | Issue |
|---|---|---|
| FR-40 | The system shall move a job only along the transitions in `JobWorkflow::TRANSITIONS`: quoted → awaiting_proof → approved → in_production → ready → collected, with `cancelled` reachable from the first three. | #5 |
| FR-41 | The system shall reject any illegal transition with an exception, not a silent no-op. | #5 |
| FR-42 | The system shall refuse cancellation once a job is `in_production`, because material has been consumed. | #5 |
| FR-43 | The system shall write one `job_activities` row per transition, recording who moved it, from what, to what, and when. A row that records something other than a transition (a nudge, a note) shall carry `kind = note` and no statuses, so the trail never claims a move that did not happen. | #5 |
| FR-44 | The system shall stamp `collected_at` when and only when a job reaches `collected`, and shall derive on-time rate from `collected_at <= due_at`. | #17 |
| FR-45 | The system shall show a customer their own jobs only, and shall return **404** — not 403 — for another customer's job. | #13 |
| FR-46 | The system shall record each proof as a numbered version with a status, and shall tell the customer on their account page when a proof is waiting for them. | #5 |
| FR-110 | The system shall not show a pickup date for work that has not been scheduled: a `quoted` job shall show its shop's published turnaround instead of a date, in every place the page states one, and shall show no timestamp on a lifecycle step it has not reached. | #104 |

FR-110 exists because the job page had begun dating work that had not been scheduled. A quote is
unpaid and unstarted; printing "ready by Friday" on it is a promise the shop never made, and the
page made it in three places that disagreed with each other. The fix was to state the shop's
turnaround until a date is real, and to stop stamping steps the job has not reached.

## 6.5 Shop operations

| ID | Requirement | Issue |
|---|---|---|
| FR-50 | The system shall give the shop a Kanban board with one column per working status, where dropping a card moves the job through `JobWorkflow`. | #6 |
| FR-51 | The system shall refuse a board drop into an illegal column, and shall refuse a drop of another shop's job. | #6 |
| FR-52 | The system shall scope every shop-panel query to the signed-in user's current shop, so a shop can never see another shop's jobs, services or payments. | #3 |
| FR-53 | The system shall let a shop owner edit a service's tiers and options, and shall increment the matrix version so the next quote reflects the change. | #4 |
| FR-54 | The system shall show the shop a dashboard with today's revenue, open jobs, jobs due today, average job value, and a seven-day chart. | #17 |
| FR-55 | The system shall surface jobs that have waited too long — proof unapproved 6 h, quote unconfirmed 24 h, ready but uncollected 48 h, or past due — longest wait first. | #17 |
| FR-56 | The system shall let an owner attached to more than one shop switch between them without a full page reload, shall re-check membership and trading status at the moment of switching, and shall not follow a return address that points at another host. | #63 |

## 6.6 Money

| ID | Requirement | Issue |
|---|---|---|
| FR-60 | The system shall create a PayMongo payment intent at checkout and store it against the job before redirecting the customer. | #11 |
| FR-61 | The system shall verify the webhook signature and shall reject an unsigned or wrongly signed request. | #11 |
| FR-62 | The system shall be idempotent: replaying the same webhook shall create exactly one `payments` row and change no amount. | #11 |
| FR-63 | The system shall record a failed attempt with its error and count, and shall flag a payment for attention after three failures. | #11 |
| FR-64 | The system shall store the commission as an absolute centavo amount on the payment row at settlement time. | #7 |
| FR-65 | The system shall track payout as a **second, independent** status: the customer paid us (`payments.status`) and we paid the shop (`payments.payout_status`). | #7 |
| FR-66 | The system shall compute a payout as gross − refunds − commission + reversed commission − processor fee = net, on one row, so the ledger's totals are a plain `SUM()` over the payments themselves and cannot disagree with them. | #7 · #81 · #82 |
| FR-67 | The system shall never rewrite a settled payout when a shop changes plan; only future payments use the new rate. | #7 |
| FR-68 | The system shall let a shop export its payout ledger as CSV, and shall refuse the export to anyone who does not own that shop. | #7 |
| FR-69 | The system shall bill subscriptions on three tiers — Starter (free, 5%), Growth (₱599, 3%), Pro (₱1,499, 1.5%) — and shall show the crossover point where the next tier becomes cheaper. | #12 |
| FR-104 | The system shall refund a settled payment in whole or in part, accumulating the refunded amount on the payment row, reversing the commission **in proportion** to what went back, and shall never let the refunds on one payment exceed its gross. The processor's fee is not returned and remains the shop's. | #82 |
| FR-105 | The system shall separate **who may cancel** from **what happens to the money**. The customer may cancel only while the job is unpaid-for-work — quoted or awaiting proof — and shall be refused that route once the proof is approved, when the material is committed and cancellation is the shop's decision. Whichever side cancels, every centavo paid and not yet returned shall be refunded. | #82 · #108 |
| FR-106 | The system shall issue a subscription invoice for each billing period of every active paid subscription, deriving the period from the day the subscription started, and shall issue each period **exactly once** however often the issuing command runs. | #83 |
| FR-107 | The system shall let an administrator record an invoice as paid, stamping who recorded it, and shall refuse to collect an invoice twice, to collect a void one, or to void a paid one. | #83 |
| FR-108 | The system shall let the customer attach the design file for their order — PNG, JPEG, WebP or PDF, up to 20 MB — keep every version they send with the name, type, size and time it arrived, treat the newest as the file to print, and record each arrival on the job's activity trail. | #100 |
| FR-109 | The system shall serve an artwork file only to the customer who ordered the job, the shop printing it, and an administrator, through a route that checks the policy rather than a public URL, and shall refuse a new file once the proof is approved or the job is cancelled. | #100 |
| FR-111 | The system shall recognise commission as revenue only on jobs the customer has collected. A payment on a job that was cancelled, or is still in progress, shall not count toward the platform's earned commission, though it stays in the ledger. | #108 |
| FR-112 | The system shall estimate the processor's fee at checkout from configured basis points and a flat amount, mark the row as an estimate, and replace both the amount and the mark with the fee the processor reports in its webhook. | #108 |
| FR-113 | The system shall record that a refund issued after the payout was released leaves the shop owing the platform the difference, and shall show that debt on the payout screen — because the platform has already paid out money it has now returned. | #108 |

Four of these were added in one pass (#108) after the revenue model was audited against its own
published policy, and each closed a way the ledger could lie:

- **FR-111** — commission was counted on every payment that had moved, including jobs that were
  cancelled afterwards. The platform booked revenue it had given back. Revenue is now recognised at
  **collection**, which is also when the money stops being refundable (BR-10).
- **FR-112** — the processor's fee was a hardcoded guess stamped at checkout and never corrected.
  It is now an estimate, marked as one, and replaced when the webhook reports the real figure.
- **FR-113** — a refund issued after the payout had gone out was accepted with no record that the
  shop now owes the money back. The platform had paid out money it then returned.
- **FR-105** — cancelling from `approved` refunded nothing while the commission stayed booked.

## 6.7 Administration

| ID | Requirement | Issue |
|---|---|---|
| FR-70 | The system shall let an administrator approve, reject with a reason, suspend and reinstate a shop, writing the reason and the acting user in one place. | #8 |
| FR-71 | The system shall block a pending or suspended shop's owner from the shop console while leaving their account intact. | #8 |
| FR-72 | The system shall give an administrator a dashboard of platform-wide money and volume, and a list of payments needing attention. | #8 |
| FR-73 | The system shall let an administrator create and edit users and assign roles. | #9 |
| FR-74 | The system shall let an administrator edit CMS sections and settings, and hiding or deleting a section shall remove the block from the page rather than leave a gap. | #10 |
| FR-75 | The system shall refuse any delete that would destroy a payment, and shall refuse to delete a shop that still has jobs, at the database rather than only in the panel. | #61 |
| FR-76 | The system shall let an administrator change a rotating credential — the PayMongo keys, the inbound mailbox — from the screen that reports on it, shall store it encrypted, and shall fall back to the environment value when none is saved. | #63 |
| FR-77 | The system shall not expose a stored credential in the page it is edited on when the value came from the environment, and shall state which of the two sources is in force. | #63 |
| FR-78 | The system shall keep deployment-shape settings — mail transport, queue driver, storage disk — out of the browser, because a form that can switch the queue to `sync` is a form that can stop payments settling. | #63 |

## 6.8 Notifications

| ID | Requirement | Issue |
|---|---|---|
| FR-80 | The system shall email the customer when a proof is sent, and shall email the shop when that proof is approved or sent back with a reason. | #61 |
| FR-81 | The system shall email the customer a receipt when a payment settles, and shall send it once even if the webhook is replayed. | #61 |
| FR-82 | The system shall treat every notification as best-effort: a mail failure shall be reported and shall not undo the record it describes, fail the request, or retry money logic. | #61 |
| FR-83 | The system shall put a reply address on every notification that names the job it is about, so a customer who answers files their reply on that job instead of writing to a mailbox nobody reads. | #63 |
| FR-84 | The system shall accept an inbound reply only when it carries a valid signature over the exact bytes received, and shall refuse every delivery when no signing secret is configured. | #63 |
| FR-85 | The system shall file an inbound reply once, deduplicated by the provider's message id, and shall answer a message it cannot match with **202** rather than a 4xx, so a provider does not retry it forever. | #63 |
| FR-86 | The system shall never move a job on the strength of an inbound email: mail is unauthenticated, so it appends a note and nothing else. | #63 |

## 6.9 The shop console

| ID | Requirement | Issue |
|---|---|---|
| FR-87 | The shop surface shall present one shell across every screen: the sidebar, the top bar, the page header and the type scale shall not change when the owner moves from one screen to another. | #66 |
| FR-88 | The shell shall be rendered by the server, so the navigation is usable before any JavaScript runs and a screen needs no client runtime to exist. | #66 |
| FR-89 | The console shall own every screen a shop needs to run its day — dashboard, jobs, production board, price matrix, reports, billing — and shall not link into the administrative CMS for any of them. | #66 |
| FR-90 | The console shall let the shop read, filter and search its jobs, open one, send a proof and change its status, and shall refuse a status change the workflow does not allow. | #66 |
| FR-91 | The console shall let the shop edit its catalogue — services, size tiers, finishing add-ons and quantity-discount bands — and shall apply an edit to the next quote without changing jobs already priced. | #66 |
| FR-92 | The console shall let an account that owns more than one shop switch between them, and shall accept a shop only when it belongs to the account and is trading. | #66 |
| FR-93 | The console shall hide the price, catalogue and billing screens from shop staff and shall refuse them at the route, so hiding the link is a courtesy and not the rule. | #66 |
| FR-114 | The console shall show, without opening the job, whether the customer's artwork has arrived — a badge on the job queue and a mark on the production board — and shall name a paid job with no artwork as waiting on the customer rather than as a proof the shop has not sent. | #113 |

## 6.10 Shop admission

| ID | Requirement | Issue |
|---|---|---|
| FR-94 | The system shall let a prospective shop register from `/open-a-shop`, collecting account credentials and shop details in one submission, creating the **account and the request** and **no shop**. | #73, #77 |
| FR-95 | The system shall list requests in the administrative panel oldest-first, shall count the unanswered ones in the navigation, and shall keep handled requests out of the default view. | #73 |
| FR-96 | The system shall, on approval, create the shop in **one transaction**, place it on the free Starter plan, attach the applicant's account as owner, and record on the request which shop it became and who approved it. A request with no account shall have one created first. | #73, #77 |
| FR-97 | The system shall never create, store or transmit a plaintext password. An applicant who registered chose their own and is sent nothing on approval; an account created *by* approval is handed a single-use, expiring set-password link. | #73, #77 |
| FR-98 | The system shall rate-limit the public request form and shall silently discard a submission whose hidden field is filled, answering the same way it answers a person. | #73 |
| FR-99 | The system shall reuse and promote an existing account that matches the applicant's email rather than create a second one, and shall refuse to approve a request whose email belongs to an administrator. | #73 |
| FR-100 | The system shall let an administrator re-send the invitation for an approved request, minting a fresh single-use link that replaces the previous one and creating or altering no account and no shop. | #75 |
| FR-101 | The system shall refuse to re-send an invitation when the account or the shop behind the request no longer exists, and shall say which. | #75 |
| FR-102 | The system shall hold a shop account with no trading shop in a waiting room that reports the state of its request — waiting, refused with the reason, or paused — and shall send it there from every console URL rather than refusing it with a 403. | #77 |
| FR-103 | The system shall accept at most one open request per account, and shall refuse to let an administrator account apply. | #77 |

A request is **not** a shop. It has no owner, no matrix and no storefront, so it lives in its own
table until an administrator decides — otherwise every enquiry would put an unowned row in the shop
list and in every count that says "shops".

**Who creates the account.** Since v1.9 the applicant does, by registering as they apply. That is
what removes mail delivery from the critical path: the owner already knows their password, so
approval only has to attach a shop. Before that, approval created the account and handed over a
set-password link, which meant a deployment with no real mailer could approve a shop and still
leave its owner locked out — and the link, being a stored-hash token, could not be recovered. Both
shapes still work: a request with no account takes the older path.

# 7. Business rules

These are invariants. They are enforced in one place each, and a change to one is a design
decision, not a bug fix. Chapter 17 names the class that owns each.

**BR-1 — Money is integer centavos, everywhere.** No float, no decimal, no `round()` after the
fact. Formatting happens once, in the view. Rates are basis points.

**BR-2 — The shop is always an argument.** There is no global price. A shop that has not
configured a service has no price, and the system says so.

**BR-3 — The customer pays the shop's price.** The platform adds nothing on top. Commission and
the processor's fee are both deducted from the shop's payout, never charged to the customer. The
shop therefore nets `gross − commission − fee`. The platform's own revenue is the **commission
alone**, because it receives the gross, pays the shop the net and never pays the processor. On a
refund the commission comes back in proportion but the fee does not — the processor keeps it — so a
fully refunded payment nets the shop a small negative rather than zero, and that is the shop's loss
to carry, not a cost to pass on.

**BR-4 — History is append-only.** A settled payment, an issued invoice and a recorded transition
are facts. They are never updated to reflect a new policy. A refund is recorded by **accumulating**
onto the payment — how much went back, and how much commission came with it — rather than by
editing the amounts the payment was settled at.

**BR-5 — One place per decision.** Prices in `PriceCalculator`. Transitions in `JobWorkflow`. Plan
changes in `Billing`. Approval in `ShopApproval`. Payment capture in `PayMongo`. If a second place
starts deciding the same thing, the code is wrong.

**BR-6 — No blanket admin bypass.** There is no `Gate::before`. Sixteen explicit policies answer
every question, so "can an admin do this?" is written down rather than implied.

**BR-7 — Tenant isolation is a query condition, not a UI filter.** Scoping is applied to the
query, so a forged URL returns 404 rather than someone else's data.

**BR-8 — The server decides the price, the state, and the money.** The client may preview and may
optimistically render, but it never becomes the source of truth.

**BR-9 — Money is released on delivery, not on payment.** A captured payment is money the platform
holds; it becomes money the shop has earned only when the job is `collected`. The published policy
says so — *"Payouts are released from the platform ledger after the job is collected"* — and
`Payment::mayBeReleased()` is where that sentence is enforced. A job cancelled after approval is
refunded in full (#108), so its payment no longer looks deliverable — but the release still has to
be gated on delivery, because a paid job in progress is indistinguishable from a finished one by
status alone.

**BR-10 — Revenue is recognised at collection, not at payment.** The platform earns its commission
when the customer collects the job, because until then the money is still refundable and a
cancelled job would book revenue that was handed back. `Payment::onCollectedJobs()` is the single
definition of "earned"; every figure that claims to be revenue — the dashboard tile, the stats
widget, the reports — goes through it. Money on an unfinished job is still counted, but as **held**,
and shown as such.

# 8. Non-functional requirements

| ID | Requirement | How it is met |
|---|---|---|
| NFR-1 | **Security.** Every console and panel route is authorised by a policy; every write is CSRF-protected except the signed webhooks. | 16 policies; `ValidateCsrfToken` waived by path for `webhooks/*` only, because the signature is the credential |
| NFR-2 | **Tenant isolation.** No query in the shop console may cross shop boundaries. | Every screen scopes through `$shop->jobs()` / `$shop->services()`, and route-model binding is re-checked (`abort_unless($job->shop_id === $shop->id, 404)`); tests assert 404 on forged ids |
| NFR-3 | **Integrity.** A price, a transition or a payout must not be derivable two ways. | Single-service rule (BR-5), tests comparing against independent SQL |
| NFR-4 | **Performance.** A quote is computed in a single request with no N+1; the dashboard aggregates in SQL. | Eager loading, aggregate queries, no per-row queries in widgets |
| NFR-5 | **Portability.** Local development runs on SQLite; production on MySQL 8, with no code change beyond `DB_CONNECTION`. | Portable migrations, no raw SQL dialect dependencies |
| NFR-6 | **Accessibility.** Forms are keyboard-operable, inputs carry labels, errors are associated with their field, disabled controls remain legible. | Semantic markup, `aria-invalid`, contrast-checked disabled states |
| NFR-7 | **Maintainability.** A new interactive screen is one registry line, not a router. | Vue island registry in `resources/js/app.js` |
| NFR-8 | **Recoverability.** A failed webhook is retried with backoff and surfaced to the administrator rather than lost. | `payments.attempts`, `last_error`, `failed_at`, queue |
| NFR-9 | **Auditability.** Who changed a job's state, who approved a shop, and when, are all recorded. | `job_activities`, `shops.approved_by`, `status_changed_by` |
| NFR-10 | **Deployability.** The application runs on a single container with no worker, no scheduler and no disk that survives a restart, by moving each one's work somewhere that does: the webhook settles inside the request (`QUEUE_CONNECTION=sync`), billing runs on the boot hook (`RUN_BILLING`), and uploads go to object storage (R2). | `render.yaml`; the boot hook in `docker/entrypoint.sh`; both disks read the `AWS_*` block, so nothing in the code changes |

NFR-10 is the one worth defending, because it is the one a panel is most likely to read as a
compromise. It is not: it is a statement about where the work happens. A queue worker exists to
move work out of the request, and the only queued job in this system is the webhook settlement,
which is fast and must not be deferred — a payment that settles "eventually" is a payment the
customer cannot see. A scheduler exists to run periodic work, and the only periodic work is monthly
invoicing, which the boot hook performs idempotently by period (FR-106). A persistent disk exists
to hold files between requests, and files belong in object storage on any deployment that expects
to survive a restart.

# 9. Data requirements

Nineteen domain tables, eighteen Eloquent models (`shop_users` is a pivot with no model of its
own), and sixteen policies — one per model a panel can address. `Integration` has none on purpose:
credentials are reached through `/admin/integrations`, which is gated by panel access, and a
resource with a list page would put every stored key one click from a table.

| Group | Tables | What they hold |
|---|---|---|
| **Identity** | `users`, `password_reset_tokens`, `shop_requests` | Accounts, reset tokens, and applications that are not yet shops |
| **Tenancy** | `shops`, `shop_users` | The shop record and the many-to-many attachment of accounts to shops |
| **Catalogue** | `services`, `price_tiers`, `price_options`, `quantity_discounts` | What a shop prints and what it charges, per shop |
| **Work** | `jobs`, `job_lines`, `proofs`, `artworks`, `job_activities` | The order, its priced lines, the two files, and the trail |
| **Money** | `payments`, `plans`, `subscriptions`, `invoices` | One payment per job with its split, and the platform's own billing |
| **Content** | `page_sections`, `site_settings` | The CMS that drives the public pages |
| **Configuration** | `integrations` | Rotating credentials, encrypted |

The full dictionary, enumerations and invariants are in chapter 20. Three requirements shape the
model more than any other:

1. **Money is an integer** (BR-1), so every amount column is `bigint` centavos and every rate is
   `integer` basis points. There is no `decimal` anywhere in the schema, and no `float`.
2. **History is append-only** (BR-4), so `payments` carries `refunded_amount` and
   `reversed_commission` as accumulating columns rather than a status that overwrites the original
   settlement. A refund never edits what was settled; it adds to what was returned.
3. **A job's price is frozen at quote time** (FR-36), so `jobs.matrix_version` records the
   fingerprint of the shop's tiers that produced the total. Editing a tier changes the next quote
   and nothing else.
