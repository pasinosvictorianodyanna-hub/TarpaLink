# Software Requirements Specification — TarpaLink

**Version 1.19 · Web Systems and Technologies 2 · System Integration and Architecture 1**

> **Written against:** current `main`. Version 1.0 was written against `main` plus the
> profile island (#16), before the shop dashboard was merged. Version 1.1 recorded the
> directory map, the customer navigation, the admin integrations screen, customer
> registration, and the timezone fix. Version 1.2 recorded the seller-side pass: the
> shop staff role, proof upload and customer approval, the multi-shop picker, and a
> `job_activities` trail that no longer records a nudge as a status change. Version 1.3
> closed the security and durability gaps: sign-in throttling, password recovery, a delete
> that can no longer reach settled money, and the notifications the workflow was missing.
> Version 1.4 closed the five functional gaps v1.3 still carried — geocoding, the shop
> picker, inbound mail, an editable integrations screen, and checkout as a Vue island.
> Version 1.5 was structural rather than functional: the shop surface got **one shell**.
> The dashboard had a console layout of its own while the other seven screens were still
> Filament, so every link out of the dashboard changed the product underneath the click.
> All eight screens moved into the console (§5.9).
> Version 1.6 finished that job — the panel the screens came from is deleted, so Filament
> is the admin side only, and the two capabilities that lived only in the panel were
> carried across before it went (§11).
> Version 1.7 adds the missing first step: a shop can now **ask** for an account (§5.10).
> The public page had always described that step and nothing implemented it, so the only
> way a shop could join was for a developer to seed one.
> Version 1.8 adds the handover's second chance: an approved request can be **re-invited**,
> because the first link is shown once, expires in an hour, and is stored hashed (§5.10).
> Version 1.9 inverts who creates the account: the applicant registers as they apply, and
> approval **activates** that account instead of creating one and mailing a link (§5.10).
> Version 1.10 makes the processor fee a deduction from somebody. It had been recorded on
> every payment since #7 and subtracted from nobody, so the shop was credited money the
> processor had already taken (FR-66, BR-3).
> Version 1.11 closes the loop the fee opened: a payment can now be **refunded**, in whole
> or in part, which the published policy had promised since the site was written and no code
> ever did (§5.6, FR-104/FR-105). It also corrects v1.10's own error — the fee comes off the
> shop's payout, so the platform never pays it and "commission − fee" understated what the
> platform earns by a factor of thirty.
> Version 1.12 makes subscription revenue real: invoices now recur, carry the period they
> bill and a due date, and can be collected and voided (§5.6, FR-106/FR-107). Before this a
> shop was invoiced once on switching plan and never again, and nothing ever recorded a
> payment against one.
> Version 1.13 adds the step the whole product was missing: the customer can **send the
> design file** to the shop they ordered from (§5.2, FR-108/FR-109). The privacy policy
> listed "the artwork file you upload" as collected data, the terms made the customer
> responsible for it, and the shop-facing pitch promised jobs arriving "with the artwork
> attached" — while the only way to tell a shop anything was a 500-character notes field.
> Version 1.14 corrects four things that only appeared when the pages were opened. The job
> page printed one instruction twice, the progress rail called the shop's proof the customer's
> "design file", and a job cancelled before any artwork still asked for one in yellow. The
> fourth was a test that had been passing for the wrong reason, and had started deleting a
> live file to keep passing.
> Version 1.15 stops the job page dating work that has not been scheduled. A quote is unpaid
> and unstarted, so it now shows the shop's turnaround instead of a pickup date — in all
> three places the old page showed one, and which disagreed with each other. It also fixes
> `Shop::estimatedReadyAt()` moving the date it was handed (§5.4).
> Version 1.16 corrects a counter and a claim. The dashboard's "needs your action" counted
> *whether* a design was missing rather than how many were, so it read "1" above a list
> showing two. And every shop page said payment is released "only after you confirm pickup" —
> an action the customer never takes, because the shop marks the job collected (§5.2).
> Version 1.17 fixes four ways the revenue model leaked, all of them on paths the suite did
> not walk. Cancelling from `approved` refunded nothing while the commission stayed booked
> (FR-105); commission was counted on jobs that were never collected, so a cancelled job left
> the platform holding revenue it had given back (FR-111); the processor's fee was a hardcoded
> guess stamped at checkout and never corrected from the webhook (FR-112); and a refund issued
> after the payout had gone out was accepted with no record that the shop now owes it back
> (FR-113).
> Version 1.18 makes the customer's design visible to the shop. The file could be sent since
> v1.13 and the shop could read it on the job page, but nothing said it had *arrived* — the
> upload sent an email and mail is best-effort, so a shop had to open every job to find out. The
> queue and the production board now say so (FR-114), and a paid job with no design is no longer
> labelled "proof not sent yet", which blamed the shop for a file only the customer can send.
> Version 1.19 is deployment rather than product, and it closes the two gaps §11 had been
> carrying. Uploads go to Cloudflare R2 and the schema to TiDB Cloud, so the free tier of a
> platform that offers neither persistent storage nor MySQL can run the application (NFR-10).
> The database half found a defect rather than a setting: `config/database.php` handed PDO a CA
> but gave no way to relax the peer-name check, so a certificate without a SAN could never
> connect — and a bare `array_filter` discarded `false` on the way in, which is precisely the
> value the switch needs to carry. One pass, two lines of configuration, and a verification
> that a TLS-only server actually negotiates TLS.

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
| **Artwork** | The file the customer wants printed, sent to the shop that will print it. Append-only: a corrected file is a new row, and the newest one is current. |
| **Proof** | The file the shop sends back showing what it is about to print. The customer approves or rejects it. |

---

## 3. Actors

| Actor | Description | Enters through |
|---|---|---|
| **Customer** | Buys print jobs. May browse without an account; must sign in to check out. | Storefront (`/`) |
| **Shop owner** | Runs one or more print shops. Maintains the price matrix, works the queue, releases payouts. | Shop panel (`/shop`) |
| **Shop staff** | Works the queue and the board, cannot change prices or billing. | Shop panel (`/shop`) |
| **Administrator** | Approves shops, manages users and roles, edits site content, monitors money. | Admin panel (`/admin`) |
| **PayMongo** | Payment processor. Creates intents; calls our webhook to confirm them. | `POST /webhooks/paymongo` |
| **Mail provider** | Delivers our notifications, and forwards a customer's reply back to us. | `POST /webhooks/inbound` |

A user has exactly one `users.role` — `customer`, `shop_owner`, `staff`, or `admin` — but a
shop owner is linked to shops through `shop_users`, not through the role, so one person can
own two shops and a shop can have two staff. `shop_users.role` (`owner` / `staff`) is the
per-shop role; `users.role` is the platform-wide one. Price and billing screens require
`shop_users.role = owner`, so a staff member is inside the panel but outside the money.

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
| FR-15 | The system shall throttle repeated failed sign-ins per email **and** per caller, refusing further attempts until the window expires, and shall not reveal whether an address is registered while throttled. | #61 |
| FR-16 | The system shall let a user reset a forgotten password by email link, shall invalidate existing "remember me" sessions on success, and shall answer identically whether or not the address is registered. | #61 |

### 5.2 Directory and storefront — FR-20 …

| ID | Requirement | Issue |
|---|---|---|
| FR-20 | The system shall list approved shops only, searchable by shop name, description or service name and filterable by city, each showing rating and completed jobs. | #2 |
| FR-21 | The system shall render a shop profile with its services and starting prices, computed from that shop's live tiers. | #2 |
| FR-22 | The system shall let a visitor open the job builder for a shop and configure size, quantity and options. | #4 |
| FR-23 | The system shall render the landing page from CMS content, so an editor can change the hero, banner, FAQ, testimonials and footer blurb without a deploy. | #10 |
| FR-24 | The system shall resolve a typed address to coordinates and show the directory measured from it, and shall say so plainly when the lookup fails or is switched off rather than leaving the map at a default. | #63 |
| FR-25 | The system shall complete checkout without a page reload, shall show a rejected field beside the input that caused it, and shall keep working as a plain form submission when the bundle does not load. | #63 |

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
| FR-37 | The system shall send the total it displayed to the server with the order, and shall refuse to place the order — before any row is written — if the server's fresh quote disagrees, returning the new total so the customer can confirm it deliberately. | #63 |

### 5.4 Job lifecycle — FR-40 … FR-46, FR-110 …

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

### 5.5 Shop operations — FR-50 …

| ID | Requirement | Issue |
|---|---|---|
| FR-50 | The system shall give the shop a Kanban board with one column per working status, where dropping a card moves the job through `JobWorkflow`. | #6 |
| FR-51 | The system shall refuse a board drop into an illegal column, and shall refuse a drop of another shop's job. | #6 |
| FR-52 | The system shall scope every shop-panel query to the signed-in user's current shop, so a shop can never see another shop's jobs, services or payments. | #3 |
| FR-53 | The system shall let a shop owner edit a service's tiers and options, and shall increment the matrix version so the next quote reflects the change. | #4 |
| FR-54 | The system shall show the shop a dashboard with today's revenue, open jobs, jobs due today, average job value, and a seven-day chart. | #17 |
| FR-55 | The system shall surface jobs that have waited too long — proof unapproved 6 h, quote unconfirmed 24 h, ready but uncollected 48 h, or past due — longest wait first. | #17 |
| FR-56 | The system shall let an owner attached to more than one shop switch between them without a full page reload, shall re-check membership and trading status at the moment of switching, and shall not follow a return address that points at another host. | #63 |

### 5.6 Money — FR-60 …

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

FR-104 to FR-113 are numbered after §5.10's requirements because numbers follow the order
issues were adopted, not the order the sections appear in.

### 5.7 Administration — FR-70 …

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

### 5.8 Notifications — FR-80 …

| ID | Requirement | Issue |
|---|---|---|
| FR-80 | The system shall email the customer when a proof is sent, and shall email the shop when that proof is approved or sent back with a reason. | #61 |
| FR-81 | The system shall email the customer a receipt when a payment settles, and shall send it once even if the webhook is replayed. | #61 |
| FR-82 | The system shall treat every notification as best-effort: a mail failure shall be reported and shall not undo the record it describes, fail the request, or retry money logic. | #61 |
| FR-83 | The system shall put a reply address on every notification that names the job it is about, so a customer who answers files their reply on that job instead of writing to a mailbox nobody reads. | #63 |
| FR-84 | The system shall accept an inbound reply only when it carries a valid signature over the exact bytes received, and shall refuse every delivery when no signing secret is configured. | #63 |
| FR-85 | The system shall file an inbound reply once, deduplicated by the provider's message id, and shall answer a message it cannot match with **202** rather than a 4xx, so a provider does not retry it forever. | #63 |
| FR-86 | The system shall never move a job on the strength of an inbound email: mail is unauthenticated, so it appends a note and nothing else. | #63 |

### 5.9 The shop console — FR-87 …

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

### 5.10 Shop admission — FR-94 …

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

A request is **not** a shop. It has no owner, no matrix and no storefront, so it lives in its
own table until an administrator decides — otherwise every enquiry would put an unowned row in
the shop list and in every count that says "shops".

**Who creates the account.** Since v1.9 the applicant does, by registering as they apply. That
is what removes mail delivery from the critical path: the owner already knows their password,
so approval only has to attach a shop. Before that, approval created the account and handed
over a set-password link, which meant a deployment with no real mailer could approve a shop
and still leave its owner locked out — and the link, being a stored-hash token, could not be
recovered. Both shapes still work: a request with no account takes the older path.

**The role is a label, not a permission.** An applicant is created as `shop_owner` before
anything is approved, and that grants nothing. `ConsoleController::shop()` admits only a user
attached to an *active* shop; an applicant has none. "Not legal yet" is enforced by the shop
attachment, which is also why the waiting room exists — the account is real, and what it
cannot do needs explaining rather than a 403.

Re-sending exists for the older shape only, and for a reason that has not changed: the link is
flashed once, expires in an hour, and is stored **hashed**, so nothing can read it back.

---

## 6. Business rules

These are invariants. They are enforced in one place each, and a change to them is a
design decision, not a bug fix.

**BR-1 — Money is integer centavos, everywhere.** No float, no decimal, no `round()`
after the fact. Formatting happens once, in the view. Rates are basis points.

**BR-2 — The shop is always an argument.** There is no global price. A shop that has not
configured a service has no price, and the system says so.

**BR-3 — The customer pays the shop's price.** The platform adds nothing on top.
Commission and the processor's fee are both deducted from the shop's payout, never charged
to the customer. The shop therefore nets `gross − commission − fee`. The platform's own
revenue is the **commission alone**, because it receives the gross, pays the shop the net and
never pays the processor. On a refund the commission comes back in proportion but the fee
does not — the processor keeps it — so a fully refunded payment nets the shop a small
negative rather than zero, and that is the shop's loss to carry, not a cost to pass on.

**BR-4 — History is append-only.** A settled payment, an issued invoice and a recorded
transition are facts. They are never updated to reflect a new policy. A refund is recorded by
**accumulating** onto the payment — how much went back, and how much commission came with it —
rather than by editing the amounts the payment was settled at.

**BR-5 — One place per decision.** Prices in `PriceCalculator`. Transitions in
`JobWorkflow`. Plan changes in `Billing`. Approval in `ShopApproval`. Payment capture in
`PayMongo`. If a second place starts deciding the same thing, the code is wrong.

**BR-6 — No blanket admin bypass.** There is no `Gate::before`. Sixteen explicit policies
answer every question, so "can an admin do this?" is written down rather than implied.

**BR-7 — Tenant isolation is a query condition, not a UI filter.** Scoping is applied to
the query, so a forged URL returns 404 rather than someone else's data.

**BR-8 — The server decides the price, the state, and the money.** The client may preview
and may optimistically render, but it never becomes the source of truth.

**BR-9 — Money is released on delivery, not on payment.** A captured payment is money the
platform holds; it becomes money the shop has earned only when the job is `collected`. The
published policy says so — *"Payouts are released from the platform ledger after the job is
collected"* — and `Payment::mayBeReleased()` is where that sentence is enforced. A job
cancelled after approval is refunded in full (#108), so its payment no longer looks
deliverable — but the release still has to be gated on delivery, because a paid job in progress
is indistinguishable from a finished one by status alone.

**BR-10 — Revenue is recognised at collection, not at payment.** The platform earns its
commission when the customer collects the job, because until then the money is still
refundable and a cancelled job would book revenue that was handed back. `Payment::onCollectedJobs()`
is the single definition of "earned"; every figure that claims to be revenue — the dashboard
tile, the stats widget, the reports — goes through it. Money on an unfinished job is still
counted, but as **held**, and shown as such.

---

## 7. Non-functional requirements

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

---

## 8. Data requirements

Seventeen domain tables, sixteen models (`shop_users` is a pivot with no model of its
own), sixteen policies. The full dictionary, enumerations and invariants are in
[SCHEMA.md](SCHEMA.md).

---

## 9. External interfaces

| Interface | Direction | Protocol | Failure behaviour |
|---|---|---|---|
| **PayMongo** | out (create intent) / in (webhook) | HTTPS + JSON, signature header | Unsigned and stale signatures rejected; failed processing retried with backoff and flagged after three attempts |
| **SMTP** | out | SMTP | Proof sent, proof decided, payment receipt, password reset and the shop's nudge. Every send is best-effort: a failure is reported and never undoes the record it describes — see FR-82 |
| **Maps** | out | Leaflet tiles (OpenStreetMap), browser geolocation | Tiles degrade to a labelled offline panel; no address-to-coordinate lookup — see §11 |
| **File storage** | `proofs` and `artwork` disks | Filesystem, or S3-compatible object storage when configured | Proof and artwork files live on disks of their own and are served only through a checked route, so a **private** bucket is enough: nothing needs a presigned URL or a public hostname. `PROOF_DISK_DRIVER=s3` moves them without a code change, and Cloudflare R2 needs only `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` and the region `auto` on top of that |
| **Database** | out | MySQL protocol, TLS when the server requires it | TiDB Cloud refuses a plaintext connection and signs with its own CA. `MYSQL_ATTR_SSL_CA` takes a *path*, so `DB_SSL_CA_PEM` carries the certificate's contents and the boot writes them to a file before the config cache is built; `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` relaxes the peer-name check for a certificate that carries no SAN |

---

## 10. Traceability

Every requirement is proven by a test that exists in this repository. The file name is
given so a reader can open it; several issues are covered by more than one file.

| Issue | Requirements | Tests |
|---|---|---|
| #2 | FR-30 … FR-34 | `CheckoutSmokeTest` (size outside every tier, shop with no catalogue) |
| #3 | FR-11, FR-52 | `AuthorizationTest`, `ShopStaffTest` (staff inside the console, outside the money), `ConsoleScreensTest` |
| #4 | FR-22, FR-35, FR-36, FR-53 | `ShopWorkflowTest::editing_a_tier_changes_the_next_quote_and_the_matrix_version`, `ConsoleScreensTest` (the preview is the real quote), `CheckoutSmokeTest` |
| #5 | FR-40 … FR-43, FR-46 | `ShopWorkflowTest` (illegal skip, legal transition recorded, cancel blocked on press), `ProofExchangeTest`, `JobNudgeTest` (a nudge is a note, not a move) |
| #6 | FR-50, FR-51 | `ConsoleScreensTest` (board drop, illegal column, another shop's job) |
| #7 | FR-64 … FR-68 | `ReportsTest`, `ExportTest` (streamed ledger, query count flat) |
| #8 | FR-70 … FR-72 | `AdminDashboardTest` |
| #9 | FR-10, FR-73 | `AuthorizationTest` (incl. `there_is_no_blanket_administrator_bypass`) |
| #10 | FR-23, FR-74 | `CmsTest` |
| #11 | FR-60 … FR-63 | `PayMongoWebhookTest` |
| #12 | FR-69 | `BillingTest` |
| #13 | FR-45 | `AuthorizationTest::a_customer_cannot_read_another_customers_job` |
| #16 | FR-12 … FR-14 | `ProfileTest` |
| #17 | FR-44, FR-54, FR-55 | `ShopDashboardTest` |
| #53 | FR-20, FR-21 | `DirectoryTest` — real map, customer position, nearest-first ordering |
| #55 | FR-35, FR-60 … FR-63 | `CheckoutSafetyTest`, `TimezoneTest`, `PayMongoWebhookTest` |
| — | FR-11 (multi-shop) | `ShopSwitchTest` — one owner, two shops, and no way for the session to pick a third |
| — | FR-10 (registration) | `RegistrationTest` |
| #61 | FR-15, FR-16 | `LoginThrottleTest`, `PasswordResetTest` |
| #61 | FR-75 | `DeleteSafetyTest` — the database refuses, and the panel agrees with it |
| #61 | FR-80 … FR-82 | `TransactionalMailTest` — including mail failure leaving the record intact |
| #63 | FR-83 … FR-86 | `InboundMailTest` — signature, provider shapes, dedupe, and mail never moving a job |
| #66 | FR-87 … FR-93 | `ConsoleTest`, `ConsoleScreensTest` — every screen, every permission, and no nav link out of the console |
| #73 | FR-94 … FR-99 | `ShopRequestTest` — a request creates no shop, approval creates the shop and attaches the owner, and the invite link really sets a working password |
| #75 | FR-100, FR-101 | `ShopRequestTest` — a re-send mints a new link, the old one stops working, and an approved request with no shop is refused |
| #77 | FR-94, FR-96, FR-97, FR-102, FR-103 | `ShopRequestTest` — applying creates the account and the request but no shop, the chosen password signs the owner in after approval, a pending owner waits instead of hitting a 403, and the legacy path still invites |
| #81 | FR-66 | `ReportsTest`, `DataIntegrityTest` — the fee comes off the payout, and the split adds back to the gross |
| #83 | FR-106, FR-107 | `BillingInvoiceTest` — one invoice per period however often the command runs, a new period on the month roll, void re-billed, and no double collection |
| #82 | FR-66, FR-104, FR-105 | `RefundTest` — a full refund leaves the shop carrying the fee, a partial reverses the commission in proportion, refunds stop at the gross, and the customer's cancel closes after the proof |
| #108 | FR-105, FR-111, FR-112, FR-113 | `RevenueIntegrityTest` — the fee is an estimate until the webhook reports one, commission counts only on collected jobs, and a refund after release leaves a visible debt. `RefundTest` — the shop cancelling after approval returns the money. `SubscriptionRevenueTest` — every paid-plan shop has a subscription and a bill |
| #113 | FR-114 | `ArtworkTest` — the queue says whether the design has arrived and stops nagging about a closed job, the board marks a job waiting on the customer's file, and `ConsoleTest` — a paid job with a design is the shop's delay, a paid job without one waits on the customer |
| #117 | NFR-10, §9 | **Proven by booting the image, not by PHPUnit** — a configuration claim needs a server that can refuse it. MySQL 8.4 with `require_secure_transport=ON` rejects the app with `[3159] insecure transport are prohibited` when no CA is given, rejects it with `Cannot connect to MySQL using SSL` when the CA is given but the certificate has no SAN, and accepts it — cipher `TLS_AES_256_GCM_SHA384` — with `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=false`. The CA path survives `config:cache`, and an explicit `MYSQL_ATTR_SSL_CA` overrides `DB_SSL_CA_PEM` |

Two honest notes about this table:

- **The console's coverage is one test file per claim, not one per screen.** `ConsoleTest`
  owns the dashboard's arithmetic and `ConsoleScreensTest` owns everything else; both walk
  the routes as an owner, as staff and as a customer, because the failure that matters is
  a screen that opens for the wrong account, not a screen that fails to render.
- **`AuthorizationTest` is the spine of the traceability claim.** It is the file that
  proves BR-6 (no `Gate::before`) and BR-7 (404, not 403, across tenants) rather than
  assuming them — including a test literally named
  `there_is_no_blanket_administrator_bypass`.

---

## 11. Known gaps

Stated plainly, because a specification that claims completeness is not credible. Version
1.4 closed five items this section used to carry — geocoding, the form-post shop picker,
inbound mail, the read-only integrations screen, and checkout as a Blade form. Two remain,
and neither is a bug; v1.12 adds a third that is a business rule rather than a defect:

- **Mail is sent inline, not queued.** Proof sent, proof decided, payment receipt, password
  reset, the shop's nudge and the inbound acknowledgement all leave through `Notifier`,
  which sends synchronously and swallows failures so a mail problem can never break the
  workflow that triggered it. The queue driver is `database` with no worker guaranteed, so
  queueing would have meant mail sitting in a table forever — which looks exactly like a
  bug. With `MAIL_MAILER=log` nothing leaves the machine at all; that is a deployment
  setting, not a code path.
- **Proof storage defaults to the container's disk.** Proofs have a disk of their own
  (`PROOF_DISK_DRIVER`) so moving artwork to object storage is a configuration change
  rather than a code change, and the read and write paths both go through it. Customer
  artwork follows the same setting (`ARTWORK_DISK_DRIVER` overrides it), because the two
  files travel together and a deployment that moved one and not the other would be storing
  a job's input and its output in two places with two lifetimes. The default is still
  `local`, because that is correct on a laptop and a developer should not need a bucket to
  run the suite — but a deployment must set it to `s3` before a shop uploads anything it
  needs back, and since v1.19 the Render blueprint ships with it already set.

Version 1.12 made subscription revenue collectable, and that leaves a third gap which is a
business rule rather than a defect: **nothing enforces payment.** A shop can be invoiced every
month, never pay, and go on trading — there is no card on file to charge and no automatic
suspension. Overdue invoices are visible to the administrator (`/admin/invoices`) and to the
shop (`/console/billing` shows what is outstanding), and that is the whole of it. Suspending
a shop for non-payment is a decision a person makes on the shop's record, deliberately, in
the same way that approving one is.

Version 1.17 closes the fourth gap it found, and leaves a smaller one in its place: **the fee
is an estimate until the processor says otherwise.** Checkout writes a fee from
`PAYMONGO_FEE_RATE_BP` and `PAYMONGO_FEE_FLAT` and marks the row `fee_is_estimate`, because
the real fee is only known after capture. The webhook replaces both the amount and the mark
when the event carries a fee (FR-112), and the payout screen appends `est.` to any row still
carrying a guess. A deployment that never receives a fee-bearing webhook — or a processor that
changes its schedule without a config change — leaves estimates standing. They are labelled,
not hidden, which is the difference between a known approximation and a wrong number.

Version 1.6 retired the **Filament shop panel**. It had been registered at `/shop` with
nothing linking to it since §5.9 moved every screen to the console, and it is gone now —
along with its provider, its seventeen routes, the `ShopPicker` topbar component and the
`/shop/plan-and-billing` redirect. Two capabilities had to be carried across first, because
they lived only there: the **nudge** on a stuck job (`JobNudge`, reachable from the
dashboard's Needs attention list and from the job page) and the **paid/unpaid split in the
attention copy**, which the console port had quietly reverted to wording §5.9 had already
corrected. A route assertion in `SmokeTest` fails if a panel is ever registered on that
prefix again.

Filament now serves the admin panel only, which is what §1 said it was for.

Three things are deliberately *not* gaps, and are recorded here so they are not read as
omissions later:

- **Geocoding is one provider.** `Geocoder` speaks Nominatim because it needs no key, and
  a project that requires a card on file to place a pin is a project that ships with seeded
  coordinates. Results are cached for 30 days and the address box is a deliberate submit,
  which is what the usage policy requires. Swapping in a commercial geocoder means one
  class, not one architecture.
- **The shop picker still navigates.** It is a plain form POST to `/console/shop`, and the
  choice is re-checked server-side against the account's own active shops. Switching shops
  reloads rather than patching the current screen, because every figure on every screen is
  scoped to the shop it was rendered for. Patching them in place would be a smaller
  experience built on a much larger lie.
- **Inbound mail needs a forwarder.** `/webhooks/inbound` takes a signed JSON body; it does
  not speak IMAP. A provider's inbound rule (Mailgun, Postmark, or a five-line relay) has to
  post to it with an `X-Inbound-Signature` HMAC. That is deliberate: an application that
  polls a mailbox is an application with a second, unversioned copy of its own state.

---

## 12. Acceptance

A requirement is done when all four hold:

1. The behaviour exists and is reachable by the actor who needs it.
2. A test asserts the behaviour **independently** of the implementation — a payout test
   compares against a plain `SUM()`, not against the ledger's own arithmetic.
3. The rule lives in one place, and that place is named in BR-5.
4. The screen matches `design/prototype.html`, or the difference is deliberate and
   written down.
