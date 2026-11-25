# 10. External interfaces

Six edges cross the system boundary. Five are live in every request; one is a build-time fact.

| Interface | Direction | Protocol | Failure behaviour |
|---|---|---|---|
| **PayMongo** | out (create intent) / in (webhook) | HTTPS + JSON, signature header | Unsigned and stale signatures rejected; failed processing retried with backoff and flagged after three attempts |
| **SMTP** | out | SMTP | Proof sent, proof decided, payment receipt, password reset and the shop's nudge. Every send is best-effort: a failure is reported and never undoes the record it describes — see FR-82 |
| **Maps** | out | Leaflet tiles (OpenStreetMap), browser geolocation | Tiles degrade to a labelled offline panel; no address-to-coordinate lookup without a provider — see chapter 12 |
| **File storage** | `proofs` and `artwork` disks | Filesystem, or S3-compatible object storage when configured | Proof and artwork files live on disks of their own and are served only through a checked route, so a **private** bucket is enough: nothing needs a presigned URL or a public hostname. `PROOF_DISK_DRIVER=s3` moves them without a code change, and Cloudflare R2 needs only `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` and the region `auto` on top of that |
| **Database** | out | MySQL protocol, TLS when the server requires it | TiDB Cloud refuses a plaintext connection and signs with a public certificate. `MYSQL_ATTR_SSL_CA` takes a *path*, so `DB_SSL_CA_PEM` carries the certificate's contents and the boot writes them to a file before the config cache is built; `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` relaxes the peer-name check for a certificate that carries no SAN |
| **Inbound mail** | in | HTTPS + JSON with an HMAC header | A delivery without a valid signature over the exact bytes received is refused; an unmatched message is answered **202**, not 4xx, so the provider stops retrying |

The two inbound edges are the ones a reader should examine, because both are unauthenticated
channels into a system that moves money. Neither can move a job. The webhook may settle a payment,
because it carries a signature computed over the payload with a shared secret and it is idempotent
(FR-62). An email may append a note, because an email proves nothing about who sent it (FR-86).

# 11. Traceability

Every requirement is proven by a test that exists in this repository. The file name is given so a
reader can open it; several issues are covered by more than one file.

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
| #117 | NFR-10, chapter 10 | **Proven by booting the image, not by PHPUnit** — a configuration claim needs a server that can refuse it. MySQL 8.4 with `require_secure_transport=ON` rejects the app with `[3159] insecure transport are prohibited` when no CA is given, rejects it with `Cannot connect to MySQL using SSL` when the CA is given but the certificate has no SAN, and accepts it — cipher `TLS_AES_256_GCM_SHA384` — with `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=false`. The CA path survives `config:cache`, and an explicit `MYSQL_ATTR_SSL_CA` overrides `DB_SSL_CA_PEM` |

Two honest notes about this table:

- **The console's coverage is one test file per claim, not one per screen.** `ConsoleTest` owns the
  dashboard's arithmetic and `ConsoleScreensTest` owns everything else; both walk the routes as an
  owner, as staff and as a customer, because the failure that matters is a screen that opens for
  the wrong account, not a screen that fails to render.
- **`AuthorizationTest` is the spine of the traceability claim.** It is the file that proves BR-6
  (no `Gate::before`) and BR-7 (404, not 403, across tenants) rather than assuming them — including
  a test literally named `there_is_no_blanket_administrator_bypass`.

## 11.1 What "proven" means here

A test that asserts a screen returns 200 proves that the screen renders. It does not prove that the
figure on it is right. Where a requirement is about arithmetic, the test compares against a query
written independently of the code under test:

```php
// The ledger's total must agree with the payments table, not with the ledger's own sum.
$this->assertSame(
    (int) Payment::query()->where('shop_id', $shop->id)->sum('net'),
    $ledger->totalNet()
);
```

That pattern is what makes SO-3 a real objective rather than a slogan. The same shape appears in
`ReportsTest` (totals), `DataIntegrityTest` (the split adds back to the gross), and
`AdminDashboardTest` (commission is a sum of commissions, not gross times a rate).

# 12. Known gaps

A specification that claims completeness is not credible. Six things are known, and none of them
is a surprise to a reader who reaches this page.

**1. Mail is sent inline, not queued.** Proof sent, proof decided, payment receipt, password reset,
the shop's nudge and the inbound acknowledgement all leave through `Notifier`, which sends
synchronously and swallows failures so a mail problem can never break the workflow that triggered
it. The queue driver is `database` with no worker guaranteed, so queueing would have meant mail
sitting in a table forever — which looks exactly like a bug. With `MAIL_MAILER=log` nothing leaves
the machine at all; that is a deployment setting, not a code path.

**2. Nothing enforces payment of a subscription invoice.** A shop can be invoiced every month,
never pay, and go on trading — there is no card on file to charge and no automatic suspension.
Overdue invoices are visible to the administrator (`/admin/invoices`) and to the shop
(`/console/billing` shows what is outstanding), and that is the whole of it. Suspending a shop for
non-payment is a decision a person makes on the shop's record, deliberately, in the same way that
approving one is.

**3. The processor's fee is an estimate until the processor says otherwise.** Checkout writes a fee
from `PAYMONGO_FEE_RATE_BP` and `PAYMONGO_FEE_FLAT` and marks the row `fee_is_estimate`, because
the real fee is only known after capture. The webhook replaces both the amount and the mark when
the event carries a fee (FR-112), and the payout screen appends `est.` to any row still carrying a
guess. A deployment that never receives a fee-bearing webhook — or a processor that changes its
schedule without a config change — leaves estimates standing. They are labelled, not hidden, which
is the difference between a known approximation and a wrong number.

**4. Storage defaults to the container's disk.** Proofs have a disk of their own
(`PROOF_DISK_DRIVER`) so moving artwork to object storage is a configuration change rather than a
code change, and the read and write paths both go through it. Customer artwork follows the same
setting (`ARTWORK_DISK_DRIVER` overrides it), because the two files travel together and a
deployment that moved one and not the other would be storing a job's input and its output in two
places with two lifetimes. The default is still `local`, because that is correct on a laptop and a
developer should not need a bucket to run the suite — but a deployment must set it to `s3` before a
shop uploads anything it needs back, and the Render blueprint ships with it already set.

**5. A month of zero traffic bills nothing.** Billing runs on the boot hook and bills the period
containing *today*. If a service receives no request for a whole month, that month's invoice is
issued on the next boot rather than during it. Closing this means backfilling missed periods, which
is a change to billing semantics rather than a deployment detail, and it is deliberately not done.

**6. Three things are not gaps, and are recorded so they are not read as omissions.**

- **Geocoding is one provider.** `Geocoder` speaks Nominatim because it needs no key, and a project
  that requires a card on file to place a pin is a project that ships with seeded coordinates.
  Results are cached for 30 days and the address box is a deliberate submit, which is what the
  usage policy requires. Swapping in a commercial geocoder means one class, not one architecture.
- **The shop picker still navigates.** It is a plain form POST to `/console/shop`, and the choice is
  re-checked server-side against the account's own active shops. Switching shops reloads rather
  than patching the current screen, because every figure on every screen is scoped to the shop it
  was rendered for. Patching them in place would be a smaller experience built on a much larger lie.
- **Inbound mail needs a forwarder.** `/webhooks/inbound` takes a signed JSON body; it does not
  speak IMAP. A provider's inbound rule has to post to it with an `X-Inbound-Signature` HMAC. That
  is deliberate: an application that polls a mailbox is an application with a second, unversioned
  copy of its own state.

# 13. Acceptance

A requirement is done when all four hold:

1. **The behaviour exists and is reachable** by the actor who needs it.
2. **A test asserts the behaviour independently of the implementation** — a payout test compares
   against a plain `SUM()`, not against the ledger's own arithmetic.
3. **The rule lives in one place**, and that place is named in BR-5.
4. **The screen matches the design**, or the difference is deliberate and written down in
   chapter 21.

Two further conditions apply to the project as a whole rather than to a single requirement: the
suite passes in full (563 tests, 2,626 assertions), and the production image boots against the
database and storage it is configured for, which is a claim no unit test can make (chapter 22).
