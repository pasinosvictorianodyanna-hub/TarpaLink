# 1. Introduction

## 1.1 Purpose

This document is the requirements and design record for **TarpaLink**, a job-order platform for
small tarpaulin and print shops in the Philippines. It has two parts. Part I states what the
system must do and how that was decided. Part II states how the system is built and why it is
built that way.

It is written to be read by four people: a panel assessing whether the work is complete, an
adviser checking whether the requirements are testable, a developer who needs the shape of the
code before opening it, and a shop owner who wants to know what the software will and will not do
for them.

## 1.2 The problem it answers

A small print shop in the Philippines takes orders through a chat window. The customer sends a
photo of a design, asks for a 4 × 6 ft tarpaulin, and is quoted a number by a person who has
quoted a thousand of them. The file is forwarded to whoever runs the printer. The job is written
on a whiteboard. The customer comes back two days later to ask whether it is ready, and the
answer depends on who is at the counter.

Four things go wrong repeatedly in that arrangement, and every one of them is a record problem
rather than a printing problem:

1. **The price is a conversation, not a rule.** Two customers are quoted differently for the same
   size because the quote depends on who answered the phone. When the shop raises its material
   cost, nothing records which orders were priced at the old rate.
2. **The file has no home.** It lives in a chat thread, on a phone, or in a folder named after the
   customer's first name. Nobody can say which version was actually printed.
3. **The money is not reconciled.** The shop knows what it collected and roughly what the platform
   took, but not per job, and not after a refund.
4. **"Is it ready?" has no answer that does not require a person.** The whiteboard is the state
   machine, and it is only readable from one room.

TarpaLink replaces the conversation with a **job**: one customer, one shop, one service, one
specification, one price agreed before production starts, one file to print, one proof approved
before the press runs, and one payment whose split is computed rather than remembered.

## 1.3 Intended audience

| Reader | What they should take from this document |
|---|---|
| Panel and adviser | That every requirement is numbered, testable, traced to a test, and that the gaps are stated rather than hidden — chapter 11 and chapter 12 exist for this |
| A developer joining the project | The five services that own the decisions (chapter 17), the transition table (chapter 18), and the schema (chapter 20) |
| A future maintainer | Why a given rule lives where it does, because a later change that moves it is a defect, not a refactor |
| A shop owner or administrator | Chapter 3, which states plainly what the software does not do |

## 1.4 How this document is organised

| Part | Chapters | Content |
|---|---|---|
| **I — Requirements** | 1–13 | Introduction, objectives, scope, actors, functional requirements, business rules, non-functional requirements, data, interfaces, traceability, known gaps, acceptance |
| **II — Design** | 14–23 | Context, use cases, architecture, services, lifecycle, data model, schema, wireframes, deployment, decisions |

Identifiers are stable and are used by the tests, the issue tracker and the source:

| Prefix | Meaning | Example |
|---|---|---|
| `FR-nn` | A functional requirement, stated as *the system shall …* | FR-37 |
| `BR-n` | A business rule, an invariant enforced in one place | BR-1 |
| `NFR-n` | A non-functional requirement, with the mechanism that meets it | NFR-10 |
| `UC-nn` | A use case, expanded in chapter 15 | UC-03 |
| `SO-n` | A specific objective, stated in chapter 2 | SO-3 |

A requirement is not "done" because the screen exists. Chapter 13 states the four conditions a
requirement must satisfy, and chapter 11 names the test file that proves each one.

## 1.5 Conventions

Money is stored as **integer centavos** and written in this document in pesos with the stored
value beside it the first time, because the difference between the two is the point of half the
design. ₱2,036.80 is `203680`. A rate is stored in **basis points**: `300` is 3%.

A class name is written as it appears in the source, for example `PriceCalculator`. A table or
column is written in lower case, for example `payments.payout_status`. The phrase "one place"
means exactly one callable site in the codebase.

# 2. Objectives

## 2.1 General objective

To build a web-based job-order system that lets a customer configure, price, pay for and approve
a print job with a shop that has never spoken to them, and lets that shop run the work — queue,
proof, production, collection, payout — from one screen, without a developer in the loop for
either side.

## 2.2 Specific objectives

Seven objectives, each with the requirement that answers it and the evidence that it was met.
This is the table a panel should read first.

| ID | Objective | Answered by | Evidence |
|---|---|---|---|
| **SO-1** | Let a customer order print work end to end without a conversation: find a shop, configure a job, see a price, pay, send a file, approve a proof, collect. | FR-20 … FR-25, FR-30 … FR-37, FR-40 … FR-46 | `DirectoryTest`, `CheckoutSmokeTest`, `CheckoutSafetyTest`, `ProofExchangeTest`, `ArtworkTest` |
| **SO-2** | Price every job from the shop's **own** matrix, through one function, and never let a price change rewrite an agreed total. | FR-30 … FR-36, BR-2, BR-5 | `ShopWorkflowTest` — editing a tier changes the next quote and the `matrix_version`, not the last one |
| **SO-3** | Make the money reconcile: commission, processor fee, refunds and payout must be computable from the payment row alone and must never disagree with it. | FR-64 … FR-69, FR-104 … FR-107, FR-111 … FR-113, BR-1, BR-3, BR-9, BR-10 | `ReportsTest` (totals compared against a plain `SUM()`), `RefundTest`, `RevenueIntegrityTest`, `BillingInvoiceTest` |
| **SO-4** | Give the shop a working day rather than a database: a queue, a board, a price matrix, a ledger, and one shell across all of it. | FR-50 … FR-56, FR-87 … FR-93, FR-114 | `ConsoleTest`, `ConsoleScreensTest`, `ShopDashboardTest`, `ArtworkTest` |
| **SO-5** | Admit a shop without a developer: apply, be decided, get an account, start trading — and never hand anyone a password. | FR-94 … FR-103 | `ShopRequestTest` — a request creates no shop, approval creates it and attaches the owner, the invite link really sets a working password |
| **SO-6** | Keep the customer's file and the shop's proof separate, versioned, and readable only by the three parties entitled to them. | FR-46, FR-80, FR-108, FR-109, FR-114 | `ArtworkTest`, `ProofExchangeTest`, `TransactionalMailTest` |
| **SO-7** | Run on a free hosting tier that offers no worker, no scheduler and no disk that survives a restart — and say so rather than pretend. | NFR-10, FR-78, chapter 22 | `render.yaml`; a boot of the production image with Render-shaped environment; `docs/DEPLOY-RENDER.md` records what was verified and what was not |

## 2.3 Why these seven and not others

The objectives are deliberately stated as **properties of the record**, not as features. "Send a
proof" is a feature; "the proof that was approved is the one that was printed, and the file behind
it can only be opened by three parties" is an objective. Features were derived from objectives,
not the reverse, and the difference shows up when a requirement is tested: a feature test asserts
that a screen renders, an objective test asserts that a figure agrees with an independent query.

Two candidate objectives were dropped during the work and are recorded here so their absence is
not read as an oversight:

- **"Charge the customer a platform fee."** Rejected on the business rule that the customer pays
  the shop's price and nothing on top (BR-3). Commission comes out of the shop's payout.
- **"Suspend a shop automatically for non-payment."** Rejected because there is no card on file
  to charge and no authority to withhold service on an unpaid invoice without a person deciding.
  Overdue invoices are visible to both sides; the decision stays human (chapter 12).

## 2.4 Success criteria

The project is complete when all five of the following hold. Four are the acceptance conditions of
chapter 13; the fifth is specific to this project's claim about deployment.

| # | Criterion | Status |
|---|---|---|
| 1 | Every functional requirement is reachable by the actor who needs it | met — chapter 6 lists 89 requirements, all reachable |
| 2 | Every requirement is proven by a test that asserts the behaviour independently of the implementation | met — chapter 11 names the file for each |
| 3 | Every rule lives in one place, and that place is named in BR-5 | met — chapter 17 maps each rule to its service |
| 4 | The screen matches `design/prototype.html`, or the difference is written down | met — the differences are recorded in chapter 21 |
| 5 | The application boots on a free hosting tier with no worker, no scheduler and no persistent disk | met — verified by booting the production image; chapter 22 states what was and was not observed |

The suite stands at **567 tests and 2,644 assertions, zero failures**, run on SQLite. Two files of
it — `ReportsTest` and `AdminDashboardTest` — were additionally run against the live TiDB cluster
that the deployment uses, because they are the two that would break first on a database that is
not MySQL 8.

# 3. Scope

## 3.1 What the system does

TarpaLink is a **job-order system**, not an online store. There is no cart, no shipping, no
inventory and no SKU. The unit of work is a job: one customer, one shop, one service, one
specification, one price agreed before production starts.

| Capability area | What it covers | Requirements |
|---|---|---|
| **Directory and storefront** | A searchable list of approved shops, a shop profile with live starting prices, a landing page driven by CMS content, and an address lookup that measures distance | FR-20 … FR-25 |
| **Quoting** | One function that turns a service, a size, a quantity and a set of options into a priced job, with the matrix version recorded on the job | FR-30 … FR-37 |
| **Job lifecycle** | Six working states and one terminal state, enforced in one transition table, with an append-only trail | FR-40 … FR-46, FR-110 |
| **Shop operations** | A queue, a production board, a price matrix, a dashboard, a stuck-job list, and one shell across all of it | FR-50 … FR-56, FR-87 … FR-93, FR-114 |
| **Money** | Payment capture through PayMongo, a two-status settlement model, refunds, a payout ledger with CSV export, and recurring subscription billing | FR-60 … FR-69, FR-104 … FR-107, FR-111 … FR-113 |
| **Administration** | Shop approval and suspension, user and role management, CMS editing, encrypted credentials, and platform-wide figures | FR-70 … FR-78 |
| **Shop admission** | A public application that creates an account and a request but no shop, decided by an administrator in one transaction | FR-94 … FR-103 |
| **Notifications** | Six transactional emails, best-effort, plus a signed inbound path that files a reply on the job it is about and can never move a job | FR-80 … FR-86 |

## 3.2 What the system does not do

Each exclusion below was a decision, not an omission. The reason matters more than the list.

| Out of scope | Why |
|---|---|
| **Cart, catalogue, inventory, SKU** | A tarpaulin is made to order from a specification. There is nothing to hold in stock, and a cart would imply a price that exists before the customer describes the work. |
| **Delivery and courier integration** | Every job in scope is collected at the counter. A courier is a second address, a second failure mode and a second promise about time, for a step the shops in this market do not use. |
| **Mobile application** | The three surfaces are responsive and the storefront ships almost no JavaScript. A native client would be a fourth surface with its own release cycle and no capability the web does not have. |
| **Multi-currency** | One currency, one country, one tax treatment. A second currency changes every stored amount from an integer to a pair. |
| **Marketplace-wide promotions** | Discounts belong to the shop that pays for them (`quantity_discounts` is per shop). A platform-wide promotion would be the platform setting a price, which BR-2 forbids. |
| **Automatic suspension for non-payment** | See §2.3. There is no card on file, and withholding a shop's livelihood is a decision a person makes on the record. |
| **Accounting-package export** | The payout ledger exports as CSV, which is what a bookkeeper asked for. A general-ledger integration is a project of its own. |

## 3.3 Delimitations and assumptions

These bound the claims the document makes.

1. **The deployment target is a free tier.** No queue worker, no scheduler, no disk that survives a
   restart, and no SMTP. Each of those is met by moving the work somewhere that does survive
   (chapter 22), not by assuming the platform provides it.
2. **Mail is best-effort.** Every notification is sent inline through one class and a failure is
   reported without undoing the record it describes. A deployment that leaves `MAIL_MAILER=log`
   sends nothing, which is a configuration, not a defect.
3. **One geocoding provider.** Address lookup speaks Nominatim because it needs no key. Results are
   cached for thirty days. Swapping providers is one class.
4. **The map is drawn, not hosted.** Tiles belong to OpenStreetMap; the application requests them
   and degrades to a labelled panel when they do not arrive.
5. **The shop is always an argument.** There is no global price list, so a shop that has not
   configured a service has no price and the system says so (BR-2).
6. **The deployment has been verified as an image, not as a Render service.** The container was
   booted with the environment Render would supply; the platform's own behaviours — spin-down,
   cold start, egress — are documented, not observed (chapter 22).

# 4. Definitions

| Term | Meaning |
|---|---|
| **Job** | A single print order. The primary entity of the system, with a reference, a shop, a customer, a service, a specification, a price and a status. |
| **Service** | What the shop prints: tarpaulin, stickers, flyers, DTF shirts. |
| **Price tier** | A size band for one service, with a unit price. A 3 × 3 ft sticker is priced by the tier that contains it, not by a formula. |
| **Price option** | An add-on with a flat price: eyelets, hemming, a rush fee. |
| **Matrix version** | A short fingerprint of a shop's tier rows at the moment of quoting. Stored on the job, so a later price change does not rewrite an agreed price. |
| **Centavo** | The unit of money in the database. All amounts are integer centavos. |
| **Basis point** | The unit of a rate. `300` = 3%. Commission rates are basis points, never decimals. |
| **Gross** | What the customer paid, before anything is deducted. |
| **Commission** | The platform's revenue, stored as an absolute centavo amount on the payment at settlement, not recomputed later. |
| **Processor fee** | What PayMongo keeps. Deducted from the shop's payout, never charged to the customer. |
| **Net** | `gross − refunds − commission + reversed commission − fee`. The amount the shop is owed. |
| **Settlement** | Paying the shop. Tracked as a second, independent status from the customer paying us. |
| **Artwork** | The file the customer wants printed, sent to the shop that will print it. Append-only: a corrected file is a new row, and the newest one is current. |
| **Proof** | The file the shop sends back showing what it is about to print. The customer approves or rejects it. |
| **Shop request** | An application to open a shop. It has no owner, no matrix and no storefront, and is not a shop. |
| **Waiting room** | The screen an account with no trading shop is sent to, which reports the state of its request instead of refusing it with a 403. |

# 5. Actors and roles

## 5.1 The actors

| Actor | Description | Enters through |
|---|---|---|
| **Customer** | Buys print jobs. May browse without an account; must sign in to check out. | Storefront (`/`) |
| **Shop owner** | Runs one or more print shops. Maintains the price matrix, works the queue, releases payouts. | Shop console (`/console`) |
| **Shop staff** | Works the queue and the board. Cannot change prices or billing. | Shop console (`/console`) |
| **Administrator** | Approves shops, manages users and roles, edits site content, monitors money. | Admin panel (`/admin`) |
| **PayMongo** | Payment processor. Creates intents; calls our webhook to confirm them. | `POST /webhooks/paymongo` |
| **Mail provider** | Delivers our notifications, and forwards a customer's reply back to us. | `POST /webhooks/inbound` |

## 5.2 Two roles, not one

A user has exactly one `users.role` — `customer`, `shop_owner`, `staff`, or `admin` — but a shop
owner is linked to shops through `shop_users`, not through the role. One person can own two shops;
one shop can have two staff.

| Column | Scope | What it decides |
|---|---|---|
| `users.role` | Platform-wide | Which surface the account may enter at all |
| `shop_users.role` | Per shop | Whether the account may see prices and billing for **that** shop |

The distinction is load-bearing. Price and billing screens require `shop_users.role = owner`, so a
staff member is inside the console but outside the money (FR-93). And because the role is a label
rather than a permission, an applicant is created as `shop_owner` before anything is approved and
that grants nothing: admission is decided by the shop attachment, which is why an account with no
trading shop waits in a room rather than receiving a 403 (FR-102).

## 5.3 What each actor can and cannot do

| Actor | Can | Cannot |
|---|---|---|
| Customer | Order, pay, send artwork, approve or reject a proof, read a receipt, cancel before the proof is approved | Read another customer's job (404, not 403), send artwork after approval, cancel once material is committed |
| Shop owner | Edit the matrix, work the queue and board, send proofs, read reports, export the ledger, choose a plan, switch shops | See another shop's rows, change a settled payout, approve their own shop |
| Shop staff | Work the queue and the board | Open the price matrix, reports or billing; change a status the workflow forbids |
| Administrator | Approve and suspend shops, manage users, edit content, rotate credentials, read platform money | Bypass a policy (there is no `Gate::before`), delete a payment, delete a shop that still has jobs |
