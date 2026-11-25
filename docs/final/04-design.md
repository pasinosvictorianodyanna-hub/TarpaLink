# 14. System context

## 14.1 What crosses the boundary

Three kinds of human and four external services cross the boundary. Nothing inside the application
appears on this diagram, which is the point of it: the context exists to fix what the system is
responsible for and what it merely talks to.

[[FIG:context|Figure 14.1 — The system context. The numbered edges are keyed to the table below.]]

| Edge | Party | Direction | What crosses | Protocol |
|---|---|---|---|---|
| 1 | Customer, visitor | both | Search terms, size and quantity, the order, the artwork file, the proof decision | HTTPS, HTML forms and JSON |
| 2 | Shop owner | both | Price matrix edits, status changes, proof uploads, plan choice, payout release | HTTPS, HTML forms |
| 3 | Shop staff | both | Queue and board only — no prices, no billing | HTTPS, HTML forms |
| 4 | Administrator | both | Approvals, suspensions, roles, content, rotating credentials | HTTPS, Livewire |
| 5 | PayMongo | out, then in | Payment intent out; signed webhook back (`paid`, `failed`, `expired`) | HTTPS JSON, signature header |
| 6 | SMTP relay | out | Six notifications: proof sent, proof decided, receipt, reset, nudge, acknowledgement | SMTP |
| 7 | Nominatim and OSM tiles | out | Address text in, coordinates back; tile images for the map | HTTPS |
| 8 | Cloudflare R2 | both | Proof and artwork objects, written and streamed back through a checked route | S3 API over HTTPS |

## 14.2 What stays outside

No courier, no delivery tracking, no inventory, no accounting package, no analytics service, no
search engine. Each would add an integration and a failure mode for a capability chapter 3
excludes.

The map is drawn as a boundary item rather than an internal component because the system does not
host it. It requests tiles and renders them; the tiles belong to someone else, and the diagram says
so.

One edge deserves a note. **Edge 8 is the only one whose failure is silent.** A refused SMTP
connection raises a reported error; a failed geocode degrades to a message. A storage write that
succeeds locally and disappears on the next deploy leaves no trace at all, which is why the storage
disk is configurable per environment and why the administrative panel carries a probe that writes,
reads and deletes a file on the configured disk and reports the result.

# 15. Use cases

Twenty-one use cases in two figures. They are split because one diagram carrying all of them would
be a wall of ellipses with no readable associations; the split is by surface, not by importance.

## 15.1 The customer and the shop

[[FIG:use-case-customer|Figure 15.1 — Customer and shop use cases. The dashed rule separates the two surfaces; the staff actor reaches two of the shop's use cases and nothing else.]]

## 15.2 Administration and the platform

[[FIG:use-case-admin|Figure 15.2 — Administrative use cases, and the two the platform initiates on its own behalf.]]

## 15.3 The catalogue

| ID | Use case | Actor | Requirement | Outcome |
|---|---|---|---|---|
| UC-01 | Browse the shop directory | Customer | FR-20, FR-24 | A list of approved shops, nearest first, filterable by city |
| UC-02 | Open a shop profile and see its prices | Customer | FR-21 | Services with starting prices computed from that shop's live tiers |
| UC-03 | Build a job and see the price | Customer | FR-22, FR-30 … FR-35 | A specification and a total, computed by the server on every change |
| UC-04 | Place the order | Customer | FR-37, FR-60 | A job row and a payment intent, or a refusal carrying the new total |
| UC-05 | Pay for the job | Customer, PayMongo | FR-60 … FR-63 | A settled payment, or a recorded failure that counts toward attention |
| UC-06 | Send the design file | Customer | FR-108, FR-109 | A new artwork row; the newest is the file to print |
| UC-07 | Approve or reject the proof | Customer | FR-46, FR-80 | `approved` or `awaiting_proof` with a reason, and the shop is emailed |
| UC-08 | Track the job and read its receipt | Customer | FR-44, FR-45, FR-110 | The progress rail, and a receipt for a settled payment |
| UC-09 | Work the job queue | Shop owner, staff | FR-55, FR-90, FR-114 | A filtered list with what has waited too long at the top |
| UC-10 | Move a job on the production board | Shop owner, staff | FR-50, FR-51 | A transition, or a refusal naming the illegal move |
| UC-11 | Send a proof to the customer | Shop owner | FR-46, FR-80 | A numbered proof version and an email |
| UC-12 | Maintain the price matrix | Shop owner | FR-53, FR-91 | Edited tiers and a new matrix version; earlier jobs unchanged |
| UC-13 | Read reports and export the ledger | Shop owner | FR-54, FR-68 | Figures that agree with the payments table, and a CSV |
| UC-14 | Decide a shop request | Administrator | FR-70, FR-96 … FR-99 | A shop, an owner attachment and a recorded decision — or a refusal with a reason |
| UC-15 | Suspend or reinstate a shop | Administrator | FR-70, FR-71 | A status change with the reason and the acting user |
| UC-16 | Manage users and roles | Administrator | FR-73 | An account with one platform role |
| UC-17 | Edit site content | Administrator | FR-23, FR-74 | A page section that changes without a deploy |
| UC-18 | Change a rotating credential | Administrator | FR-76, FR-77 | An encrypted value, and a screen that says which source is in force |
| UC-19 | Review platform money and volume | Administrator | FR-72 | Earned commission, held money, and payments needing attention |
| UC-20 | Settle a payment | PayMongo | FR-61, FR-62 | Exactly one payment row per intent, however often the webhook arrives |
| UC-21 | File an inbound reply | Mail provider | FR-84 … FR-86 | A note on the job, and never a status change |

## 15.4 Three use cases expanded

The catalogue says what happens; these three say how, because each is where the design decisions
concentrate.

### UC-03 — Build a job and see the price

**Actor:** customer. **Precondition:** the shop is approved and has at least one active service.
**Trigger:** the customer opens the builder for a service.

1. The system loads the service's tiers and options for **that** shop (BR-2).
2. The customer chooses a size, a quantity and any options. Each change posts the specification to
   `POST /api/quote`.
3. `PriceCalculator::quote()` selects the tier that contains the size, multiplies by area or by
   quantity depending on the unit, applies the single largest quantity threshold the order
   qualifies for, and returns the lines, subtotal, discount, total and `matrix_version`.
4. The page renders the returned total. The client never computes a price; it displays one.

**Alternates.** The size falls outside every tier → `ServiceUnavailable`, and the builder says
which sizes the shop does offer rather than guessing a price. The bundle does not load → the same
form submits as a plain POST and the server renders the same total.

**Postcondition:** nothing is written. A quote is a calculation, not a record, which is why the
next use case exists.

### UC-10 — Move a job on the production board

**Actor:** shop owner or staff. **Precondition:** the job belongs to the current shop.
**Trigger:** a card is dropped on a column.

1. The route re-resolves the shop from the session and re-checks the job's `shop_id`; a job from
   another shop is a **404**, not a 403 (BR-7).
2. `JobWorkflow::advance()` looks the move up in `TRANSITIONS`. An illegal move raises rather than
   returning false (FR-41).
3. On success it writes the new status, stamps `collected_at` if the target is `collected`, and
   appends one `job_activities` row naming the actor, the from-state and the to-state.
4. The board re-renders.

**Alternates.** The column is not reachable from the job's state → the drop is refused and the card
returns. The job is `in_production` and the move is a cancellation → refused, because material has
been consumed (FR-42).

**Postcondition:** one new activity row, and no other row changed except the job's status.

### UC-14 — Decide a shop request

**Actor:** administrator. **Precondition:** a request exists and is open.
**Trigger:** the administrator approves it.

1. The request's email is checked against existing accounts. A match is **promoted** to
   `shop_owner` rather than duplicated; an administrator's address is refused outright (FR-99).
2. In one transaction: the shop is created on the free Starter plan, the account is attached as
   owner in `shop_users`, and the request records which shop it became and who approved it (FR-96).
3. No password is created, stored or sent. The applicant registered with their own, so approval
   sends nothing (FR-97).

**Alternates.** The request has no account (the older shape) → one is created first and handed a
single-use, expiring set-password link, which is stored hashed and can never be re-read; a re-send
mints a fresh link and retires the old one (FR-100).

**Postcondition:** the applicant can sign in and trade immediately. There is no interval in which
the shop exists without an owner.

# 16. Architecture

## 16.1 One application, three surfaces

[[FIG:architecture|Figure 16.1 — Containers and layers. Every request enters at the top and is decided in the domain band; nothing above it computes a price and nothing below it renders HTML.]]

| Surface | Technology | Loads JavaScript | Who uses it |
|---|---|---|---|
| Storefront | Blade, with four Vue islands | Only on the pages that need it | Visitor, customer |
| Shop console | Blade, server-rendered shell | One inline drawer toggle | Shop owner, staff |
| Administrative panel | Filament v4 on Livewire | Yes, as Livewire requires | Administrator |

The split matters because a customer never loads the administrative framework, and a shop owner
never loads the storefront bundle. The landing page and the directory ship no JavaScript at all.
Four islands are registered: the order builder, the checkout form, the profile form and the shop
picker.

The console was a hybrid for one release, with a Vue shell on the dashboard and the administrative
framework on the other seven screens. The seam was visible to the user as a different sidebar the
moment they clicked a link. Two frameworks do not share a shell, and a shell that exists once per
screen is not a shell. It is now one Blade layout, rendered on the server, with no client runtime
(FR-87, FR-88).

## 16.2 The four layers

| Layer | What lives here | What it must not contain |
|---|---|---|
| Presentation | Blade views, Vue islands, the console shell, Filament resources | Business rules, direct database access |
| Application | Route handlers, request validation, policy checks, form requests | SQL, HTML |
| Domain | The service classes, model scopes, enums | HTTP concerns, view data |
| Persistence | Migrations, models, query scopes, transactions | Presentation logic, authorisation decisions |

A request enters at the presentation layer, is validated and authorised in the application layer, is
decided in the domain layer, and is written by the persistence layer. The direction is one-way. No
domain service renders HTML, and no view computes a price.

The authorisation decision sits in the application layer because that is where the route is known.
The policies themselves are plain classes, so they can be unit tested without a request.

## 16.3 Why not three services

The three surfaces share one price engine, one lifecycle and one set of tables. Splitting them
would mean either duplicating the pricing class — the exact failure the single-decision rule exists
to prevent — or turning an in-process call into a network call for no benefit at this size.

The system serves seven shops and a few hundred jobs. A service boundary at that scale buys nothing
and costs a deployment, a network failure mode and a second place for the price to be computed.

## 16.4 Why two frameworks

The administrative panel is an administrative toolkit. It is good at listing rows, editing one, and
gating by policy. It is the wrong tool for a public page that has to look like a print shop rather
than a database client.

The storefront and the console are hand-written Blade for that reason. The administrative panel is
the only surface where the framework's own look is acceptable, because the only person who sees it
is an administrator doing administrative work.

# 17. The five services

Five classes own the decisions named in BR-5. Everything else in the domain is a narrower operation
that composes them.

| Service | The one decision it owns | Depends on | Fails by |
|---|---|---|---|
| `PriceCalculator` | What a specification costs, and which tier contains it | The shop's own tiers and options | `ServiceUnavailable` for a size no tier covers |
| `JobWorkflow` | Which moves are legal, and what each one records | `TRANSITIONS` | Raising on an illegal move, never a silent no-op |
| `Billing` | What a shop owes the platform, and which period | `plans`, `subscriptions` | Refusing to bill a period twice |
| `PayMongo` | Whether a payment is captured, and what the split is | The intent and the signed event | Rejecting an unsigned or stale signature |
| `Notifier` | That a message is attempted, and that its failure is not fatal | The mail transport | Reporting, and never undoing the record |

## 17.1 The rule-to-class map

This table is the answer to "where is that enforced?", and it is the table a maintainer should read
before moving anything.

| Rule | Class | Method |
|---|---|---|
| BR-1 — integer centavos | The schema, and `Money` formatting in the view | column types; `number_format` at the edge only |
| BR-2 — the shop is an argument | `PriceCalculator` | `quote(Shop $shop, …)` — there is no other signature |
| BR-3 — the customer pays the shop's price | `Payment` | `net()` and `netFrom()` |
| BR-4 — history is append-only | `Refund` | accumulates onto the payment, never rewrites it |
| BR-5 — one place per decision | this table | — |
| BR-6 — no blanket admin bypass | `AppServiceProvider` | absence of `Gate::before`, asserted by `AuthorizationTest` |
| BR-7 — isolation is a query condition | Every console controller | `$shop->jobs()` and an explicit `abort_unless` on binding |
| BR-8 — the server decides | `CheckoutController` | the displayed total is re-quoted and compared |
| BR-9 — money is released on delivery | `Payment` | `mayBeReleased()` |
| BR-10 — revenue at collection | `Payment` | `scopeOnCollectedJobs()` |

## 17.2 The supporting services

Nine narrower classes. Each exists because the operation has more than one caller and would
otherwise be duplicated — which is the same reason BR-5 gives, applied one level down.

| Class | What it does | Why it is separate |
|---|---|---|
| `Refund` | Reverses a payment in whole or in part, in proportion | Two callers (customer cancel, shop cancel) and four invariants |
| `Cancellation` | Decides who may cancel and refunds the remainder | The authority question is distinct from the money question (FR-105) |
| `ShopApproval` | Creates the shop, attaches the owner, records the decision | Must be one transaction |
| `ShopOnboarding` | The public application, the request row, the invite link | The only path that creates an account without a password |
| `InboundMail` | Verifies the HMAC, dedupes, appends a note | Untrusted input reaching a job |
| `ArtworkIntake` | Validates, stores and versions the customer's file | The only write path to the `artwork` disk |
| `ProofExchange` | Versions a proof, records the decision | The other half of the same pair |
| `JobNudge` | Emails the customer about a stuck job | Writes a note, never a status |
| `Geocoder` | Address to coordinates, cached 30 days | The only outbound call with a third-party usage policy |

# 18. Job lifecycle

## 18.1 The transition table

`JobWorkflow::TRANSITIONS` is the whole of the lifecycle. There is no other place a status is
written.

| From | To | Who may move it | Guard |
|---|---|---|---|
| `quoted` | `awaiting_proof` | the system | The payment settles; the customer has paid, the shop may start |
| `quoted` | `cancelled` | customer or shop | Everything paid and not returned is refunded |
| `awaiting_proof` | `approved` | the customer | A proof exists and the customer accepted it |
| `awaiting_proof` | `cancelled` | customer or shop | Refunded in full |
| `approved` | `in_production` | the shop | — |
| `approved` | `cancelled` | the shop | Refunded in full; the commission is reversed |
| `in_production` | `ready` | the shop | — |
| `ready` | `collected` | the shop | Stamps `collected_at`, and releases the payout (BR-9) |

`cancelled` is terminal. Every other state is reachable only from the one before it: there is no
way to jump from `quoted` to `ready`, and the test that proves it asserts the exception rather than
the absence of a crash.

## 18.2 The diagram

[[FIG:lifecycle|Figure 18.1 — The seven states and the eight moves. Cancellation is reachable from the first three and refused from `in_production` onward.]]

## 18.3 What a move records

Every transition appends one `job_activities` row: who moved it, from what, to what, and when. A
row that records something other than a transition — a nudge, a note from an inbound email — carries
`kind = note` and **no statuses**, so the trail can never claim a move that did not happen (FR-43).

That distinction was not cosmetic. Before it, a nudge wrote a row with the job's current status in
both the from and to columns, and the history read as though the job had been moved by the customer
at the moment the shop was reminded to work on it. The trail is what a dispute is settled with, so a
row that says "moved" when nothing moved is worse than no row at all.

## 18.4 Two files, opposite directions

| | Artwork | Proof |
|---|---|---|
| Who sends it | The customer | The shop |
| What it is | The file to be printed | What the shop is about to print |
| When | Before production, until the proof is approved | Once the job is `awaiting_proof` |
| Storage | `artwork` disk, following `PROOF_DISK_DRIVER` unless overridden | `proofs` disk |
| Versioning | Append-only; the newest is current | Numbered versions with a status |
| Read by | The shop and the administrator | The customer |
| Writable after approval | No | No |

They are two tables rather than one with a `direction` column because they answer different
questions and have different lifetimes. A single table would have needed a nullable foreign key for
each direction and a rule about which one is set — which is the same as two tables with a
constraint removed.
