# Architecture — TarpaLink

**How the system is put together, and why it is put together that way.**

The one-line version: **Vue renders, Filament edits, Laravel decides.** Every diagram
below is an elaboration of that sentence.

---

## 1. System context

Who uses TarpaLink, and what crosses the boundary.

```mermaid
flowchart LR
    C(["Customer<br/>browses, orders,<br/>approves proof"])
    O(["Shop owner<br/>prices, queue, payouts"])
    A(["Administrator<br/>approvals, users, CMS"])

    T["<b>TarpaLink</b><br/>storefront · quoting · job lifecycle<br/>settlement · billing"]

    P["PayMongo<br/>sandbox"]
    M["Maps / tiles<br/>(not yet wired)"]
    S["SMTP<br/>(not yet wired)"]

    C -->|"configure job, check out,<br/>approve proof, track status"| T
    O -->|"maintain price matrix,<br/>work the board, export ledger"| T
    A -->|"approve shops, assign roles,<br/>edit site content"| T

    T -->|"create payment intent"| P
    P -->|"signed webhook: paid / failed"| T
    T -.->|"geocode, map view"| M
    T -.->|"receipts, proof notices"| S
```

Dotted edges are designed for but **not implemented** — they are drawn so the gap is
visible rather than discovered during a demo.

---

## 2. Containers

One Laravel application serving three surfaces. The split is by *audience*, not by
service: a customer never loads Filament, and a shop owner never loads the storefront
bundle.

```mermaid
flowchart TB
    subgraph Browser["Browser"]
        direction LR
        SB["Storefront<br/>Blade + 2 Vue islands"]
        SP["Shop panel<br/>Filament v4 / Livewire"]
        AP["Admin panel<br/>Filament v4 / Livewire"]
    end

    subgraph App["Laravel 12 application"]
        direction TB
        R["Routes<br/>web · api · panels"]
        POL["16 policies<br/>no Gate::before"]
        SVC["Services<br/>the decisions"]
        FIL["Filament resources<br/>the editing"]
        BL["Blade views<br/>the rendering"]
    end

    DB[("SQLite (dev)<br/>MySQL 8 (prod)")]
    Q[["Queue<br/>database driver"]]
    FS[["Disk<br/>proof files"]]

    SB -->|"form posts + JSON"| R
    SP -->|"Livewire"| R
    AP -->|"Livewire"| R
    R --> POL --> SVC
    SVC --> FIL
    SVC --> BL
    SVC --> DB
    R --> DB
    Q --> SVC
    SVC --> FS
```

**Why one application and not three services.** The three surfaces share one price
engine, one lifecycle and one set of tables. Splitting them would mean either duplicating
`PriceCalculator` — the exact failure BR-5 exists to prevent — or turning a single
in-process call into a network call for no benefit at this scale.

**Why Filament on two surfaces and Blade on the third.** Filament is an admin toolkit: it
is excellent at "list these rows, edit this one, gate it by policy", and it is the wrong
tool for a public page that must look like a print shop rather than a database client.
The storefront is hand-written Blade for that reason.

---

## 3. The spine: five services

Everything that could be decided in two places is decided in one.

```mermaid
flowchart LR
    subgraph Callers
        c1["CheckoutController"]
        c2["QuoteController"]
        c3["Filament JobResource"]
        c4["Board drop"]
        c5["PayMongoWebhookController"]
        c6["Billing page"]
        c7["Admin approval"]
    end

    PC["<b>PriceCalculator</b><br/>the only place a price is computed"]
    JW["<b>JobWorkflow</b><br/>the only place a status changes"]
    PM["<b>PayMongo</b><br/>intents + signature check"]
    BL["<b>Billing</b><br/>plan changes, never rewrites history"]
    SA["<b>ShopApproval</b><br/>approve / reject / suspend"]

    c1 --> PC
    c2 --> PC
    c3 --> PC
    c1 --> JW
    c4 --> JW
    c5 --> PM
    c6 --> BL
    c7 --> SA
```

| Service | Owns | Never does |
|---|---|---|
| `PriceCalculator` | tier lookup, area vs piece multiplication, quantity discounts, matrix version | touch a job or a payment |
| `JobWorkflow` | legal transitions, activity log, `collected_at` | compute a price |
| `PayMongo` | intent creation, webhook signature, failure marking | decide what a job is worth |
| `Billing` | plan change, commission rate update, invoice issue | rewrite a settled payment |
| `ShopApproval` | status, reason, acting user | delete a shop's data |

---

## 4. Runtime behaviour

### 4.1 Quoting — the number on screen and the number in the database

The builder asks on every change. The server answers. The checkout asks again, and the
server's answer is the one that becomes a job.

```mermaid
sequenceDiagram
    autonumber
    participant U as Customer
    participant V as OrderBuilder.vue
    participant Q as POST /api/quote
    participant PC as PriceCalculator
    participant DB as shops / tiers / options

    U->>V: changes size, qty or option
    V->>Q: { service_id, width_in, height_in, qty, options }
    Q->>PC: quote(shop, spec)
    PC->>DB: tiers for this service and shop
    DB-->>PC: rows
    PC-->>Q: lines, subtotal, discount, total, matrix_version
    Q-->>V: JSON
    V-->>U: live total

    U->>V: Check out
    V->>Q: same spec, again
    Q->>PC: quote(shop, spec)
    PC-->>Q: possibly a different number
    Note over Q: server number wins — client is told if it moved
    Q-->>V: job created with total + matrix_version
```

`matrix_version` is a `crc32 % 10000` fingerprint of the tier rows. It is stored on the
**job**, not the line, because the whole job was priced against one version of the matrix.
If the shop raises a price tomorrow, yesterday's job still shows what was agreed.

### 4.2 Settlement — two independent facts

The mistake this design avoids: treating "the customer paid" and "we paid the shop" as one
field. They happen days apart and one can fail without the other.

```mermaid
sequenceDiagram
    autonumber
    participant C as Customer
    participant P as PayMongo
    participant W as POST /webhooks/paymongo
    participant DB as payments
    participant L as Payout ledger

    C->>P: pays the intent
    P->>W: signed webhook
    W->>W: verify signature
    alt signature invalid
        W-->>P: 400, nothing written
    else signature valid
        W->>DB: find payment by intent_id
        alt already paid
            W-->>P: 200, no-op (idempotent)
        else first delivery
            W->>DB: status = paid, paid_at = now
            W->>DB: commission = round(amount × rate / 10000)
            W->>DB: payout_status = pending
        end
    end

    Note over L: gross − commission = net<br/>payout_status is a second lifecycle
    L->>DB: payout_status = paid, payout_date = today
```

Commission is written as an **absolute centavo amount** at settlement time. That single
decision is what makes FR-67 possible: a plan change updates `shops.commission_rate`, and
no settled row reads that column again.

### 4.3 The job lifecycle

```mermaid
stateDiagram-v2
    [*] --> quoted: checkout
    quoted --> awaiting_proof: shop uploads proof
    awaiting_proof --> approved: customer approves
    note right of awaiting_proof
        approval happens off-system today
        there is no screen for it yet
    end note
    approved --> in_production: shop starts
    in_production --> ready: printed
    ready --> collected: picked up, collected_at stamped
    collected --> [*]

    quoted --> cancelled
    awaiting_proof --> cancelled
    approved --> cancelled
    in_production --> cancelled: refused — material used
```

`cancelled` is reachable from the first three states only. Once the press has run, the
money is spent and the honest answer is "no", not a refund policy the system cannot
enforce.

---

## 5. Data model

Seventeen domain tables. The entity-relationship diagram, the full dictionary and the
invariants live in [SCHEMA.md](SCHEMA.md) — kept separate because the diagram changes
when the schema changes, and this document should not.

Three relationships carry most of the meaning:

- `shops 1─* services 1─* price_tiers` — a shop's price matrix. Every price in the system
  comes from here.
- `jobs 1─* job_lines` — the priced lines frozen at checkout.
- `jobs 1─1 payments` — one payment per job, with **two** statuses: did the customer pay,
  and did we pay the shop.

---

## 6. Deployment

```mermaid
flowchart LR
    subgraph Dev["Local development"]
        d1["php artisan serve<br/>127.0.0.1:8000"]
        d2["vite dev server<br/>:5173 (hot reload)"]
        d3[("SQLite<br/>database/database.sqlite")]
        d1 --- d3
        d2 -.->|"public/hot"| d1
    end

    subgraph Prod["Production target"]
        p1["PHP-FPM + nginx"]
        p2["MySQL 8"]
        p3["queue worker"]
        p4["object storage"]
        p1 --> p2
        p3 --> p2
        p1 --> p4
    end
```

`public/hot` is the switch. Present → assets come from Vite with hot reload. Absent →
assets come from `public/build` with a manifest. It is gitignored, which is why a fresh
clone must run `npm run build` before anything looks right.

---

## 7. Decisions worth defending

| Decision | Alternative rejected | Why |
|---|---|---|
| **Blade + Vue islands** | SPA, or Inertia | The landing page and directory ship **no JavaScript**. A router would make every page pay for the one interactive screen. |
| **Filament only for admin and shop** | Filament everywhere | Filament cannot be made to look like a print shop's storefront without fighting it. |
| **Integer centavos + basis points** | decimals, floats | `₱0.1 + ₱0.2` is not `₱0.3`. Money that is stored as a float is money that will disagree with itself. |
| **`matrix_version` on the job** | recompute on read | A price the customer agreed to must not change because the shop raised its rates. |
| **Commission as an absolute amount per payment** | compute from current rate | Otherwise a plan upgrade silently rewrites last month's payouts. |
| **Two statuses on `payments`** | one status | Customer payment and shop payout fail independently and days apart. |
| **16 explicit policies, no `Gate::before`** | admin bypass | "Admins can do everything" is how an admin accidentally reads another shop's ledger. |
| **404 for another tenant's record** | 403 | A 403 confirms the record exists. A 404 does not. |
| **Server recomputes at checkout** | trust the client | The client is a convenience; the server is the authority (BR-8). |
| **One service per decision** | fat controllers | A rule that exists in two places is a rule that will disagree with itself. |

---

## 8. What is deliberately missing

- **No API layer for a mobile client.** `POST /api/quote` and the two profile endpoints
  are the only JSON surfaces; they exist because a Vue island needs them, not because an
  API was designed first.
- **No event sourcing.** `job_activities` is an audit log, not a source of truth.
- **No microservices, no cache tier, no search engine.** At seven shops and a few hundred
  jobs, every one of those would be a cost with no measurable benefit.
