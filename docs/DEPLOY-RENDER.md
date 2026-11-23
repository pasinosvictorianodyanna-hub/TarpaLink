# Deploying to Render's free plan

`DEPLOY.md` describes the intended deployment: one host, four containers, a database
on a volume. This page describes a different thing — putting the same image on
Render's free compute plan — and it is a different thing because **three of the four
containers cannot come along**. Read the table before you start, not after.

The blueprint is `render.yaml`. It encodes everything that can be encoded; the
secrets, the database and the bucket are yours to fill in.

## What the free plan does to this application

Every number below is from Render's own free-tier documentation, not from experience — this
has never been run on Render. What *has* been run is the image, with the environment Render
would give it; see [Verification status](#verification-status) for exactly how far that got.

| Free-plan behaviour | What it does here |
|---|---|
| **Spins down after 15 minutes idle**, ~1 minute to come back | First request after a quiet period waits on a loading page. Harmless for a demo, wrong for a link you hand to a client. |
| **Ephemeral filesystem**, no persistent disks | Anything written inside the container is lost on every redeploy, restart **and spin-down**. So nothing important is written there: uploads go to Cloudflare R2 — see [File storage](#file-storage-cloudflare-r2). |
| **No background workers** | The queue worker does not exist. `QUEUE_CONNECTION=sync` runs `ProcessPayMongoWebhook` inside the webhook request instead, so settlement still happens. |
| **No cron jobs** | The scheduler never runs. `RUN_BILLING=true` issues subscription invoices from the boot hook instead, which is safe because the command is idempotent **by period** rather than by clock (#83). See [Billing without cron](#billing-without-cron). |
| **No shell, no one-off jobs** | `php artisan db:seed` cannot be run by hand. `RUN_SEEDER=true` seeds on boot instead; `ProductionSeeder` is `updateOrCreate` throughout, so it is safe on every start. |
| **Outbound SMTP (25, 465, 587) blocked** | `MAIL_MAILER=smtp` cannot deliver. Use `log` for a demo, or an HTTP mail API (Resend, Postmark) for anything real. |
| **750 instance hours per month** | One always-on service is ~744 h, so a single service fits — but only just, and spin-down is what keeps it inside the allowance. |
| **No MySQL, at any price** | The database has to live somewhere else. See below. |
| **TLS terminated at the load balancer** | Laravel sees plain HTTP unless `TRUSTED_PROXIES` names the proxy. The blueprint sets `*`, which is safe here only because the container is unreachable except through Render. |

## The database: TiDB Cloud Starter

Render offers Postgres, and its **free Postgres is deleted 44 days after creation**
(30 days, then a 14-day grace period). That is not a database you can put a capstone
on and defend a month later.

So the MySQL has to be external. **TiDB Cloud Starter** is the free option worth
naming: 5 GiB of row storage and 50M request units per instance, up to five
instances, no card. It speaks the MySQL protocol, so `DB_CONNECTION=mysql` works
unchanged.

**It is not MySQL 8.** The suite has never been run against it, and `docs/DEPLOY.md`
explains why that matters here (`only_full_group_by`, `TIMESTAMP` conversion). Run
`php artisan test` against it before trusting it with a grade — which is why this
section is longer than "paste five variables".

### Getting the connection working

1. **Create the cluster** at `tidbcloud.com` → *Starter* → the region nearest you
   (`ap-southeast-1` matches the `singapore` region this blueprint deploys to). Note
   the **host** (`gateway01.<region>.prod.aws.tidbcloud.com`), the **port** (`4000`,
   not 3306) and the generated `default` user.
2. **Allow Render in.** A new Starter cluster is open to the internet. If you tighten
   that, remember the free plan has no fixed egress IP, so an allowlist narrower than
   `0.0.0.0/0` will refuse every connection from Render — and report it as a timeout,
   not as a rejection.
3. **Get the CA certificate.** *Connect* → *TiDB Cloud Starter* → **CA cert**, or
   `curl https://<host>:4000`. It is a public certificate, not a secret.
4. **Paste its contents into `DB_SSL_CA_PEM`** — contents, not a path. See
   [A certificate has to be a file](#a-certificate-has-to-be-a-file).
5. **Fill in** `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. A
   new Starter cluster names the database `test`; rename it, or accept the name.

### A certificate has to be a file

`MYSQL_ATTR_SSL_CA` is a *path*, and a Render environment variable is a *string*. That
mismatch is the entire difficulty of using TiDB here, and there are two ways through:

- **`DB_SSL_CA_PEM`** — paste the certificate's contents. The boot writes them to
  `/etc/ssl/certs/db-ca.pem` and exports the path before `php artisan optimize` caches
  the config. Works from the blueprint alone and needs no dashboard step.
- **A Render Secret File** — mount the certificate at, say, `/etc/secrets/ca.pem` and
  set `MYSQL_ATTR_SSL_CA` to that path. An explicit `MYSQL_ATTR_SSL_CA` always wins
  over `DB_SSL_CA_PEM`, so the two cannot fight each other.

Both end in the same place: `config/database.php` hands the path to
`PDO::MYSQL_ATTR_SSL_CA` and mysqlnd does the TLS.

Order matters and is easy to get wrong by hand: the export has to happen **before**
`config:cache`, because the cache stores the resolved value. The entrypoint does it
first thing for exactly that reason — a path exported afterwards is a path nothing
reads.

### If it will not connect

The failure is unhelpful: `SQLSTATE[HY000] [2002]` covers a wrong host, a blocked IP,
a missing certificate and a plaintext connection alike. Check in this order — the port
is **4000**; the log line `[entrypoint] writing database CA certificate` proves the CA
reached the disk; the allowlist is open; and `DB_DATABASE` is `test` if you never
renamed it.

If it gets as far as **`Cannot connect to MySQL using SSL`**, the certificate is being
found and rejected. That message has exactly two causes, and they are worth telling
apart because only one of them is safe to work around:

- **The CA is wrong** — a stale download, or a certificate from another cluster.
  Compare the `[entrypoint] writing database CA certificate` file against the one in
  the TiDB console. Fix it; do not paper over it.
- **The certificate does not name the host** — PHP verifies the peer name against the
  certificate's SANs, and a certificate without a SAN can never satisfy that check.
  MySQL's own auto-generated certificate has none, so this is what you hit on a
  self-hosted TLS server, not on TiDB. Set `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=false`
  to keep the encryption and drop the name check. It is a real weakening — the
  connection is still encrypted, but no longer pinned to a particular host — so use it
  only after the first cause is ruled out.

An empty `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` is treated as *not set*, not as `false`.
That is deliberate: an env line left blank would otherwise switch the name check off
without anyone deciding to.

## File storage: Cloudflare R2

The free plan's disk is ephemeral, so both upload directions — the customer's artwork
and the shop's proof — have to live somewhere that survives a restart. R2 is
S3-compatible with 10 GB free and no egress charge, and **this application needs no
code change to use it**: `config/filesystems.php` already reads the whole `AWS_*` block
on both disks.

The reason a **private** bucket is the right shape here: `ProofController` and
`ArtworkController` stream files back through `Storage::disk()->response()` and
`->download()` rather than handing out URLs. Nothing needs a presigned URL, nothing
needs a public hostname, and `AWS_URL` can stay unset.

### Setting it up

1. **Create the bucket** — R2 → *Create bucket* → e.g. `tarpalink-files`. Leave it
   private; the app never asks for a public object.
2. **Create an API token** — R2 → *Manage R2 API Tokens* → *Create API token* →
   **Object Read & Write**, scoped to that bucket. You get an *Access Key ID*, a
   *Secret Access Key*, and the **S3 endpoint**
   (`https://<account-id>.r2.cloudflarestorage.com`). The endpoint appears on the
   token screen and is awkward to find again, so copy it before closing the tab.
3. **Fill in** `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET` and
   `AWS_ENDPOINT`. The rest is already set in the blueprint:
   `AWS_DEFAULT_REGION=auto`, `AWS_USE_PATH_STYLE_ENDPOINT=true`, and
   `FILESYSTEM_DISK` / `PROOF_DISK_DRIVER` both `s3` — artwork follows the proof disk
   unless `ARTWORK_DISK_DRIVER` splits them.

### The three ways R2 is not S3

Each is a silent failure if missed, which is why they are worth naming:

| Difference | What breaks without it |
|---|---|
| `AWS_ENDPOINT` is required | The SDK resolves `s3.auto.amazonaws.com`, which does not exist. |
| `AWS_USE_PATH_STYLE_ENDPOINT=true` | R2 does not serve virtual-hosted buckets; the request 404s or is refused. |
| `AWS_DEFAULT_REGION=auto` | R2 signs for `auto`; any other region fails signature validation. |

`Admin → Integrations` has a **storage probe** that writes and deletes a test object on
the proof disk. Press it first after deploying: if R2 is misconfigured it says *Not
writable*, which is faster than uploading a real file to find out.

## Deploying

1. **Push the repository.** Render builds from Git, not from your laptop.
2. **Create the database and the bucket first** — [TiDB](#the-database-tidb-cloud-starter)
   and [R2](#file-storage-cloudflare-r2) — and have every value to hand. Both are
   external accounts, so neither can be created from the blueprint or by anyone but
   you.
3. **New → Blueprint**, point it at this repository. Render reads `render.yaml` and
   creates the web service.
4. **Fill in the `sync: false` variables** when prompted. There are twenty; the
   blueprint marks every secret this way rather than committing one. Three can stay
   empty: `MYSQL_ATTR_SSL_CA` if you are using `DB_SSL_CA_PEM`, and `ADMIN_NAME` and
   `MAIL_FROM_ADDRESS` if you do not care what they say.
5. **Set `APP_URL`** to the `onrender.com` URL Render assigns, then redeploy. It
   cannot be known before the service exists, and a stale value puts `localhost`
   into emailed links.
6. **Set `ADMIN_EMAIL` and `ADMIN_PASSWORD`.** Without them `ProductionSeeder` logs
   `ADMIN_EMAIL / ADMIN_PASSWORD not set — no administrator created` and you have a
   deployment nobody can sign into.
7. **Press the storage probe** at `Admin → Integrations`. It is the fastest way to
   find out that the R2 endpoint or the path-style flag is wrong.

## Billing without cron

The scheduler cannot run here, and a monthly subscription that is never invoiced is not a
subscription. `RUN_BILLING=true` puts `billing:issue` in the boot hook, and that works for the
same reason the daily cron did: the command asks *"does this period have an invoice?"* rather
than *"has it been a month?"*, so thirty runs produce one invoice and the thirty-first produces
none (#83).

A free instance boots on the first request after every spin-down — up to once per fifteen
minutes — so in practice this runs far more often than a nightly job would. `Billing` also
guards the run with a cache lock, because a rolling deploy can briefly have two containers
booting at once and the check-then-insert was never atomic.

**The hole, stated plainly:** it bills the period containing *today*. A service that receives no
traffic for an entire calendar month never boots, and that month's invoice is never issued —
the next boot bills the month it lands in, not the one it missed. On a demo this is invisible.
On a shop that is actually trading, use a paid instance with a real scheduler, or a host that
has one.

## The storefront will be empty

`RUN_SEEDER=true` runs `ProductionSeeder`, which creates plans, page copy and the administrator
and **no shops**. That is correct for a real deployment — shops register at `/open-a-shop` — but
it means the directory is empty on first load, which looks broken in a demo.

`DatabaseSeeder` (the seven-shop demo set) cannot run on the boot hook: it uses `create()` rather
than `updateOrCreate`, so a second boot would either duplicate rows or fail on a unique key. It
also collides with `ProductionSeeder`'s page sections if the production seeder got there first —
verified: `Duplicate entry 'hero' for key 'page_sections_key_unique'`.

So seed the demo **from your laptop, before the first boot**, against the external database:

```bash
# .env pointed at the external MySQL
php artisan migrate --force
php artisan db:seed --force          # the seven-shop demo set
```

Then deploy. The boot's `migrate` finds nothing to do, and `ProductionSeeder`'s `updateOrCreate`
calls pass over the rows the demo seeder already wrote. Do not run `db:seed` twice — it is not
idempotent, and the second run fails halfway, leaving a partially seeded database.

## Verifying it worked

There is no shell on the free plan, so verification happens through the app:

- `https://<name>.onrender.com/up` returns 200. If it does not, the deploy log is the
  only place to look.
- The log shows `[entrypoint] nginx will listen on port 10000`, then
  `running migrations`, then `seeding reference data`, then
  `issuing subscription invoices`. All four lines should appear on every boot.
- `/admin/login` renders and the credentials from step 6 sign in.
- A signed PayMongo webhook settles a payment — this is the one path where
  `QUEUE_CONNECTION=sync` differs from the documented deployment, so it is the one
  worth testing by hand.

## The two gaps left, and what closing them costs

1. **Recurring invoices need a boot.** Closed by `RUN_BILLING=true` — see
   [Billing without cron](#billing-without-cron) — with the one hole that a month of
   zero traffic bills nothing. A paid instance with a scheduler closes it completely.
2. **No worker.** `sync` is correct for this application because the only queued job
   is the webhook settlement, and it is fast. If a second job type is ever added that
   is slow or retryable, `sync` stops being a reasonable choice.

Storage and the database were the other two and are now closed — uploads go to R2 and
the schema lives in TiDB. Both were configuration; neither needed a line of code,
because the disks and the PDO options already existed and only the environment was
wrong.

## Verification status

The image changes this page depends on were tested locally, on the same MySQL 8.4
container the rest of the project uses:

| Check | Result |
|---|---|
| `PORT=10000` makes nginx listen on 10000 | log line present, `/up` 200 on 10000 |
| `/`, `/admin/login` on that port | 200 |
| `RUN_MIGRATIONS=true` on an existing schema | `Nothing to migrate` |
| `RUN_SEEDER=true` twice | `users=2 sections=14 plans=3 admins=1` both times |
| Drivers resolve to the Render set | `database / sync / database` |
| Seeder without admin credentials | refuses and says so |
| **Full image boot, Render-shaped env** | nginx on `PORT=10000`, migrations, seeding, `billing:issue`, `optimize`, `/up` 200 in 9 s; `/` and `/admin/login` 200; `/console` 302 |
| **`RUN_BILLING=true` with three subscriptions** | first boot issued `INV-202611-0001/0002/0003`; the next boot logged *"Nothing due — every active paid subscription is already billed"*; invoice count stayed at 3 |
| **`db:seed` after `ProductionSeeder`** | fails on `page_sections_key_unique` — see [The storefront will be empty](#the-storefront-will-be-empty) |
| **`DB_SSL_CA_PEM` written and exported** | log line `writing database CA certificate`; `/etc/ssl/certs/db-ca.pem` present; `config('database.connections.mysql.options')` carries the path after `optimize` |
| **`MYSQL_ATTR_SSL_CA` set explicitly** | wins over `DB_SSL_CA_PEM`, as documented |

**Not tested:** the Render platform itself, a live TiDB cluster and a live R2 bucket.
The spin-down, the cold start, the ephemeral disk and the blocked SMTP ports are
documented behaviour, not observed behaviour; the boot above ran against MySQL 8.4 in
Docker. The two external services were verified as far as an image can be verified
without an account — the certificate reaches PDO and the disk resolves to `s3` — but
the first real connection is yours.
