# 22. Deployment

## 22.1 What the target does not give

The deployment target is a free hosting tier: one container, no queue worker, no scheduler, no disk
that survives a restart, no MySQL, and outbound SMTP blocked. None of that is hidden. Each missing
capability is met by moving the work somewhere that does survive, and the blueprint encodes the
moves so a deploy is a click rather than a checklist.

| Missing | Consequence if ignored | What the system does instead |
|---|---|---|
| Queue worker | Mail and settlement sit in a table forever, which looks exactly like a bug | `QUEUE_CONNECTION=sync` — the webhook settles inside the request, because a payment that settles "eventually" is one the customer cannot see |
| Scheduler | Subscription invoices are never issued | `RUN_BILLING=true` runs `billing:issue` on the boot hook, and the command is idempotent **by period**, so a boot per day still issues one invoice per month (FR-106) |
| Persistent disk | Uploaded proofs and artwork vanish on the next spin-down | Both disks read the `AWS_*` block; `FILESYSTEM_DISK=s3` moves them to Cloudflare R2 with no code change |
| MySQL | Nothing to migrate into | TiDB Cloud Starter speaks the MySQL protocol; the connection is TLS-only against a publicly-trusted certificate |
| SMTP | No notification ever leaves | `MAIL_MAILER=log` is the shipped default, and every send is best-effort by design (FR-82) |

## 22.2 The container and the boot

One image, built in stages: Composer dependencies, the Vite bundle, then the runtime layer with
nginx and php-fpm. `PORT` decides where nginx listens, because the platform assigns it.

A boot writes five lines, in order, and a missing line names the stage that failed:

```
[entrypoint] starting as: web
[entrypoint] nginx will listen on port 10000
[entrypoint] running migrations
[entrypoint] seeding reference data
[entrypoint] issuing subscription invoices
[entrypoint] caching config, routes and views
```

The order is not cosmetic. Migrations must precede seeding; seeding must precede billing, because
billing needs plans; and the config cache must come **last**, because `config:cache` bakes resolved
values in — a path exported after it is a path nothing reads.

Each step is switchable (`RUN_MIGRATIONS`, `RUN_SEEDER`, `RUN_BILLING`) so a redeploy does not
re-seed a live database. The seeder that runs here is `ProductionSeeder`, which creates only the
rows the application cannot start without: the three plans, the page sections, and one
administrator from `ADMIN_EMAIL` and `ADMIN_PASSWORD`. It creates **no shops** — the storefront of a
fresh deployment is empty until someone applies at `/open-a-shop`, which is the correct first state
and a surprise worth stating in advance.

## 22.3 The database

TiDB Cloud Starter, in the same region as the container, on port **4000**. It refuses a plaintext
connection, so TLS is not optional.

`MYSQL_ATTR_SSL_CA` takes a **path**, and an environment variable is a **string**. That mismatch is
the whole of the difficulty:

- `DB_SSL_CA_PEM` carries the certificate's **contents**; the entrypoint writes them to
  `/etc/ssl/certs/db-ca.pem` and exports the path before the config cache is built.
- An explicit `MYSQL_ATTR_SSL_CA` always wins, so a mounted Secret File works too.
- Starter clusters present a publicly-trusted certificate, so the image's own bundle
  (`/etc/ssl/certs/ca-certificates.crt`) is sufficient and the PEM variable can stay empty.

Verifying this turned up a defect rather than a setting. Booting against MySQL 8.4 with
`require_secure_transport=ON` failed with `Cannot connect to MySQL using SSL` while the CA was
provably correct: PHP verifies the peer **name** against the certificate's SANs, and MySQL's
auto-generated certificate has none, so no hostname could satisfy the check. `config/database.php`
had no way to say "encrypt, but do not pin the host". Adding that knob exposed a second problem —
the options array used a bare `array_filter`, which drops every falsy value, and `false` is exactly
what the new switch carries. It would have been present in the code and absent from the connection.
The array is now built explicitly, and an empty value means *unset*, not `false`.

| Attempt | Result |
|---|---|
| No CA | `[3159] Connections using insecure transport are prohibited` |
| CA given, name check on | `Peer certificate CN=… did not match expected CN='tl-db'` — the CA reached PDO and the chain verified; only the name check failed |
| CA given, `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=false` | booted, migrated, `Ssl_cipher = TLS_AES_256_GCM_SHA384` |
| After `config:cache` | `{"1009":"/etc/ssl/certs/db-ca.pem","1014":false}` — the resolved values are baked in |
| Both `DB_SSL_CA_PEM` and `MYSQL_ATTR_SSL_CA` set | the mounted path wins; the written file is ignored |

## 22.4 The storage

Cloudflare R2, private. Both the `proofs` and `artwork` disks read the whole `AWS_*` block, and both
controllers stream files through `Storage::disk()->response()` and `->download()` rather than
handing out URLs — so a private bucket is enough, and no presigned URL or public hostname is
involved. Three values make R2 rather than S3, and each one fails silently if missed:

| Variable | Value | Why |
|---|---|---|
| `AWS_ENDPOINT` | `https://<account-id>.r2.cloudflarestorage.com` | R2 is S3-compatible but not S3; without this the client resolves `s3.auto.amazonaws.com`, which does not exist |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` | R2 does not serve virtual-hosted-style buckets |
| `AWS_DEFAULT_REGION` | `auto` | What R2 answers to |

Two further details are easy to miss. Laravel lists `league/flysystem-aws-s3-v3` only as a
*suggestion*, which installs nothing; without an explicit requirement every disk call dies while
building the driver, before any request reaches the bucket. And
`https://<account-id>.r2.cloudflarestorage.com` is notation, not a value — pasting it literally
into the dashboard produces `cURL error 3: URL rejected: Bad hostname`. Both disks keep `root`
empty when their driver is `s3`, because on an object store that setting is an object-key prefix,
not a directory.

The administrative panel carries a probe that writes, reads and deletes a file on the configured
disk and reports the result. It is the fastest way to tell a bucket from a misconfiguration, and it
is the only storage check available on a plan with no shell.

## 22.5 What was verified, and what was not

The image was booted locally with the environment the platform would supply — MySQL 8.4, `PORT`,
every boot flag — and the following were observed rather than assumed:

| Check | Result |
|---|---|
| nginx on the assigned port | `/up` returned 200 nine seconds after start |
| Routes after boot | `/` 200, `/admin/login` 200, `/console` 302 when signed out |
| Migrations on an existing schema | `Nothing to migrate` — a redeploy is safe |
| Seeder run twice | `users=2 sections=14 plans=3 admins=1` both times |
| `RUN_BILLING` with three subscriptions | first boot issued `INV-202611-0001/0002/0003`; the next logged *"Nothing due"* and the count stayed at 3 |
| Schema against TiDB 8.5.3 | all 29 migrations ran clean |
| Aggregate queries against TiDB | `ReportsTest` and `AdminDashboardTest`: 19 passed, 102 assertions |
| TLS against a server that refuses plaintext | negotiated `TLS_AES_256_GCM_SHA384` |

**Not verified:** the hosting platform itself. Spin-down after fifteen idle minutes, the cold start
that follows, the ephemeral disk, the blocked SMTP ports and the container's egress are documented
behaviour, not observed behaviour. The document that records this distinction is
`docs/DEPLOY-RENDER.md`, and the runbook that a person follows is `docs/DEPLOY-CHECKLIST.md`.

The live service was probed later, on a signed-in customer session: the job page rendered, and
design uploads and proof downloads failed with 500s that the local suite could not see. That led to
the adapter and key-prefix fixes above. The upload now refuses a write with a clear message rather
than a 500. An actual object in R2 has still not been written end to end: the deployment's S3
endpoint and credentials were real, but the Cloudflare account rejected the TLS handshake, and the
Cloudflare API reported that R2 was not enabled on the account. That is a dashboard step, not a
code change.

One measurement worth reading correctly: the two TiDB test files took seventeen minutes, roughly
fifty seconds per test. That is a laptop in Manila talking to a cluster in Singapore, where every
insert is a round trip. It is not a warning about production, where the container runs in the same
region; it is a reason to keep the suite on SQLite and to run only the aggregate-heavy files against
the real database.

# 23. Design decisions

Ten decisions that a reader is entitled to disagree with, each with the alternative that was
rejected. The reasons are the point: a later change that reverses one of these should have to
answer the reason, not just the code.

| # | Decision | Rejected alternative, and why |
|---|---|---|
| 1 | **One application, three surfaces** | Three services. They share one price engine, one lifecycle and one set of tables; splitting would duplicate the pricing class or turn an in-process call into a network call at a scale where a boundary buys nothing |
| 2 | **Blade with Vue islands, not an SPA** | A single-page app. The landing page and the directory would ship a router and a bundle to render text, and the no-JavaScript fallback is a requirement rather than a nicety (FR-25, FR-88) |
| 3 | **Filament for administration only** | Filament for the shop too. It was, for one release; the seam was visible as a different sidebar the moment a link was clicked, and two frameworks do not share a shell |
| 4 | **Money as integer centavos** | `DECIMAL`, or floats with rounding at the edge. Every payout is a sum of four subtractions; rounding at the edge means the sum of the parts need not equal the whole, and a ledger that does not add up is not a ledger |
| 5 | **Commission stored as an absolute amount at settlement** | Storing a rate and multiplying later. A shop that changes plan would have its history repriced (FR-67), and a rate is a policy while an amount is a fact (BR-4) |
| 6 | **Two settlement statuses** | One status with a combined value. "The customer paid us" and "we paid the shop" fail independently; collapsing them makes a failed payout look like an unpaid order |
| 7 | **Revenue recognised at collection** | Recognising at payment. Until collection the money is refundable, so a cancelled job would have booked revenue that was handed back (FR-111, BR-10) |
| 8 | **A request is not a shop** | A shop row with `status = pending`. Every count that says "shops" and every list that shows one would have included unowned rows with no matrix and no storefront |
| 9 | **Nobody is handed a password** | Approval creates the account and emails a password. A deployment with no real mailer would approve a shop and leave its owner locked out, and a stored-hash link cannot be re-read (FR-97) |
| 10 | **Inbound mail can never move a job** | Trusting a reply to confirm a proof. Mail is unauthenticated; the customer's button is the only approval, and a note is all an email may append (FR-86) |

Two smaller ones that come up in review:

- **The shop picker navigates instead of patching the screen.** Every figure on every console screen
  is scoped to the shop it was rendered for; patching them in place would be a smaller experience
  built on a larger lie.
- **Billing is idempotent by period, not by clock.** Issuing "the current period" once per day is
  safe; issuing "everything since the last run" depends on when the last run happened, which is not
  a fact the database holds.

# 24. Conclusion

TarpaLink does one thing: it turns a print order into a record that both sides can see, price, and
settle. Everything in this document follows from taking that seriously — the price is frozen at
quote time because an agreed price is a fact; the commission is recognised at collection because
until then the money is refundable; the customer's file is append-only because "which file did you
send me?" is a question with an answer; and the shop's proof is separate from the customer's artwork
because they travel in opposite directions and have different lifetimes.

The system is 89 functional requirements, 10 business rules, 10 non-functional requirements, 19
tables and 16 policies, proven by 567 tests and 2,644 assertions, and it runs on a free hosting tier
by moving each missing capability's work somewhere that survives rather than by pretending the
capability is there.

Three things would come next, in the order they would be worth doing:

1. **A real mailer and a queued send.** The inline `Notifier` is correct for a deployment with no
   worker; it is not correct for one with a hundred shops, where a slow SMTP server becomes a slow
   checkout.
2. **Enforcement of subscription payment.** The gap is stated in chapter 12. Closing it means a
   decision about who may withhold a shop's livelihood, which is a policy question before it is a
   code one.
3. **A second geocoding provider behind the same class.** The interface exists; what does not exist
   is a reason to pay for one until the directory outgrows a free tier's rate limit.

What is deliberately not on that list is the list chapter 3 excludes: a cart, a courier, a mobile
app, a second currency. Each of those would make the product larger and the record weaker, and the
record is the product.
