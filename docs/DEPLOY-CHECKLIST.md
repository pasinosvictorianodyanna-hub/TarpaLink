# Deploying TarpaLink to Render's free plan — a runbook

This is the linear version of [DEPLOY-RENDER.md](DEPLOY-RENDER.md). That page explains *why*
each thing is the way it is; this page is the order to do them in, what to copy at each step,
and what success looks like before you move on.

**Cost: nothing.** Render free web service, TiDB Cloud Starter, Cloudflare R2's 10 GB.
**Time: about 30 minutes**, of which roughly 8 is Render building the image.
**You need:** the GitHub account that owns `pasinosvictorianodyanna-hub/TarpaLink`, a terminal
with PHP and Composer (this repository, already set up), and two free accounts you probably do
not have yet.

Work top to bottom. Every stage ends in a **checkpoint** — do not start the next one until it
passes, because a failure at stage 5 is almost always something from stage 1 or 2.

---

## Stage 0 — What you are about to build

```
   your browser
        │
        ▼
   Render web service ──── ephemeral disk, no cron, no worker
        │        │
        │        └──────────► Cloudflare R2        proofs + artwork
        │
        └───────────────────► TiDB Cloud Starter   the schema
```

Three accounts, one container. The container is disposable; everything that matters lives in
the two boxes on the right, which is the entire point of stages 1 and 2.

---

## Stage 1 — The database: TiDB Cloud Starter

### 1.1 Create the cluster

1. Go to **tidbcloud.com** and sign in with GitHub or Google.
2. **Create Cluster** → choose **Starter** (it may be labelled *Serverless*).
3. Region: **ap-southeast-1 (Singapore)**. This should match the Render region in
   `render.yaml`; a database in Frankfurt and a container in Singapore is a slow demo.
4. Name it `tarpalink`. Leave the capacity defaults.
5. **Create**, then wait for the status to read **Available** — a minute or two.

### 1.2 Copy the connection details

Open the cluster and press **Connect**. Choose **General** / *MySQL CLI* if it offers a choice.

Copy these five things into a scratch note. You will paste them into Render in stage 5:

| Field | Looks like | Notes |
|---|---|---|
| Host | `gateway01.ap-southeast-1.prod.aws.tidbcloud.com` | |
| Port | `4000` | **not 3306** — this is the most common mistake |
| User | `3AbC9x2.root` | the random prefix is part of the username |
| Password | `••••••••` | shown **once**; if you lose it, reset it in the console |
| Database | `test` | rename it in 1.3 |

### 1.3 Give it a real database name

`test` works, but it reads badly on a panel. In the cluster's **SQL Editor**, run:

```sql
CREATE DATABASE tarpalink;
```

Then use `tarpalink` as `DB_DATABASE` everywhere below. If you skip this step, use `test` — the
application does not care, only the screenshots do.

### 1.4 Download the CA certificate

Still in **Connect**, find the **CA cert** download link (or the certificate shown in the panel)
and save it as `tidb-ca.pem`. You will need it twice, in two different forms:

- **Stage 3** — as a file path, for your laptop to reach the cluster.
- **Stage 5** — as its **contents**, pasted into a Render variable.

> It is a public certificate, not a secret. It is fine to keep it in your home folder.

### 1.5 Make sure Render can reach it

A new Starter cluster accepts connections from anywhere. If you tightened that, note that the
free plan has **no fixed egress IP**, so an allowlist narrower than `0.0.0.0/0` will refuse
Render — and report it as a timeout, not as a rejection, which wastes an hour.

### ✅ Checkpoint 1

From this repository, with the five values and the `.pem` in hand:

```bash
cd /c/Users/Temp/Desktop/TARPALINK

export DB_CONNECTION=mysql
export DB_HOST='gateway01.ap-southeast-1.prod.aws.tidbcloud.com'
export DB_PORT=4000
export DB_DATABASE=tarpalink
export DB_USERNAME='3AbC9x2.root'
export DB_PASSWORD='the-password-from-1.2'
export MYSQL_ATTR_SSL_CA='C:/Users/Temp/tidb-ca.pem'   # wherever you saved it

php artisan tinker --execute='echo DB::selectOne("select version() as v")->v, PHP_EOL;'
```

Expect a version string containing `TiDB`. Exported shell variables win over `.env`, so this
does not disturb your local SQLite setup — but **close that terminal** before running anything
else against the app, or it will keep talking to TiDB.

If it says `Cannot connect to MySQL using SSL`, the certificate is being found and rejected. See
[when it goes wrong](#when-it-goes-wrong).

---

## Stage 2 — The storage: Cloudflare R2

### 2.1 Create the bucket

1. **dash.cloudflare.com** → **R2** in the sidebar. It may ask you to enable R2; the 10 GB tier
   needs no card.
2. **Create bucket** → name `tarpalink-files` → location **Automatic**.
3. Leave it **private**. The application streams every file through a controller that checks who
   is asking, so it never needs public access — and a public bucket would expose every customer's
   artwork.

### 2.2 Create the API token

1. **Manage R2 API Tokens** → **Create API token**.
2. Permission: **Object Read & Write**. Scope it to `tarpalink-files` rather than all buckets.
3. **Create**, then copy three things:

| Field | Looks like |
|---|---|
| Access Key ID | `a1b2c3…` (32 hex characters) |
| Secret Access Key | a long string, **shown once** |
| S3 endpoint | `https://<account-id>.r2.cloudflarestorage.com` |

> The endpoint appears on the token screen and is awkward to find afterwards. Copy it before you
> close the tab — without it the uploads fail with `s3.auto.amazonaws.com` does not resolve.

### ✅ Checkpoint 2

Nothing to run yet — the proof is the storage probe in stage 7. What matters here is that you
have all three values *and* the endpoint contains your account id, not a placeholder.

---

## Stage 3 — Prove the database before you build anything

**Do not skip this.** TiDB speaks the MySQL protocol but it is **not MySQL 8**, and this schema
has never been run against it. Finding that out now costs five minutes; finding it out in front
of a panel costs the demo.

In the same terminal from checkpoint 1:

```bash
php artisan migrate --force
```

Expect `INFO  Running migrations` followed by one line per migration, all `DONE`. Then:

```bash
php artisan tinker --execute='echo App\Models\Job::query()->count(), " jobs", PHP_EOL;'
```

Expect `0 jobs` — an empty but working schema.

If a migration fails, **stop and report the exact error**. Two known hazards are
`only_full_group_by` and `TIMESTAMP` handling; neither should trip here, and if one does it is a
one-line fix in a migration rather than a reason to abandon TiDB.

---

## Stage 4 — Seed the demo (optional, but do it before the first boot)

`ProductionSeeder` — the one that runs on every boot — creates the plan catalogue, the page copy
and your administrator, and **no shops**. A deployment with no shops shows an empty directory,
which looks broken even though it is correct: shops are supposed to apply at `/open-a-shop`.

So seed the demo set from your laptop, **once, before Render's first boot**:

```bash
php artisan db:seed --force
```

Expect the seven demo shops (`Inkwell Press`, `Halftone Hub`, `Studio Sampaguita`, …), their
services, and the customer accounts the demo uses.

**Run it exactly once.** `DatabaseSeeder` uses `create()`, not `updateOrCreate()`: a second run
fails halfway on a unique key and leaves a partly seeded database. And do not run it *after*
Render has booted, because `ProductionSeeder` will already have written the page sections and the
two collide on `page_sections_key_unique`.

If you would rather have an empty directory than demo data, skip this stage — nothing later
depends on it.

---

## Stage 5 — Create the Render service

### 5.1 Point the blueprint at the repository

1. **render.com** → sign in **with GitHub**.
2. **New +** → **Blueprint**.
3. Connect GitHub if prompted and grant access to `TarpaLink`, then select it.
   The repository is public, so *Public Git repository* works too.
4. Render reads `render.yaml` and shows one web service named `tarpalink`, plan **free**, region
   **singapore**. Accept it.

### 5.2 Fill in the variables

Render now asks for every variable marked `sync: false`. There are **twenty**; three can be left
blank. Work through this table — the third column is where each value came from:

| Variable | Value | Source |
|---|---|---|
| `APP_KEY` | `base64:…` | `php artisan key:generate --show` in this repo |
| `APP_URL` | *leave blank for now* | fixed in stage 6 |
| `DB_HOST` | `gateway01.ap-southeast-1…` | stage 1.2 |
| `DB_PORT` | `4000` | stage 1.2 |
| `DB_DATABASE` | `tarpalink` (or `test`) | stage 1.3 |
| `DB_USERNAME` | `3AbC9x2.root` | stage 1.2 |
| `DB_PASSWORD` | the generated password | stage 1.2 |
| `DB_SSL_CA_PEM` | **the whole `.pem`**, `-----BEGIN…` to `-----END…` | stage 1.4 |
| `MYSQL_ATTR_SSL_CA` | *leave blank* | — |
| `AWS_ACCESS_KEY_ID` | `a1b2c3…` | stage 2.2 |
| `AWS_SECRET_ACCESS_KEY` | the long string | stage 2.2 |
| `AWS_BUCKET` | `tarpalink-files` | stage 2.1 |
| `AWS_ENDPOINT` | `https://<account-id>.r2.cloudflarestorage.com` | stage 2.2 |
| `ADMIN_NAME` | *optional* — `Your Name` | used on the admin account |
| `ADMIN_EMAIL` | `you@example.com` | you sign in with this |
| `ADMIN_PASSWORD` | something you will remember | **not** a password you use elsewhere |
| `MAIL_FROM_ADDRESS` | *optional* — `hello@tarpalink.ph` | cosmetic while mail is `log` |
| `PAYMONGO_PUBLIC_KEY` | *blank unless demoing payment* | stage 8 |
| `PAYMONGO_SECRET_KEY` | *blank unless demoing payment* | stage 8 |
| `PAYMONGO_WEBHOOK_SECRET` | *blank unless demoing payment* | stage 8 |

Two notes that are easy to get wrong:

- **`DB_SSL_CA_PEM` is the certificate, not its path.** Paste the file's text. The boot writes it
  to `/etc/ssl/certs/db-ca.pem` and points PDO at it, which is the only way to get a certificate
  into a container that has no shell.
- **Leave `MYSQL_ATTR_SSL_CA` blank.** An explicit value overrides `DB_SSL_CA_PEM`; setting it to
  a path that does not exist in the container is a connection failure with a confusing message.

Press **Apply** / **Create Web Service**. The build takes roughly 5–8 minutes on the free plan:
Docker layers, Composer, then Vite.

### ✅ Checkpoint 5

Open the service → **Logs**. A healthy first boot reads, in this order:

```
[entrypoint] starting as: web
[entrypoint] nginx will listen on port 10000
[entrypoint] writing database CA certificate
[entrypoint] running migrations
[entrypoint] seeding reference data
[entrypoint] issuing subscription invoices
[entrypoint] caching config, routes and views
```

Missing lines are diagnostic:

| Missing | Meaning |
|---|---|
| `writing database CA certificate` | `DB_SSL_CA_PEM` is empty or was pasted with the newlines stripped |
| `running migrations` | it failed — scroll up for the `SQLSTATE` |
| `issuing subscription invoices` | billing failed; usually a missing `plans` row, which means seeding failed first |

---

## Stage 6 — Fix `APP_URL`, then redeploy

The service URL (`https://tarpalink.onrender.com`, or with a suffix if the name was taken) did
not exist when you filled in the variables. Until it is set, every emailed link points at
`localhost`.

1. Copy the URL from the top of the service page.
2. **Environment** → `APP_URL` → paste it (no trailing slash) → **Save**.
3. **Manual Deploy** → *Deploy latest commit*. Saving a variable alone does not rebuild, and
   `config:cache` runs at boot, so the old value would survive.

### ✅ Checkpoint 6

`https://<your-url>/up` returns a small page with `200`. That is Render's health check and it is
the only route that works without a database — a 200 here does **not** prove the database is
reachable, which is why stage 7 exists.

---

## Stage 7 — Verify the six things that matter

Do these in order. Each one fails for a different reason, and knowing *which* one fails is the
whole value of doing them separately.

| # | Where | Expect | If it fails |
|---|---|---|---|
| 1 | `/` | The landing page, with the seven shops in the directory | Blank page → the seeder did not run; see stage 4 |
| 2 | `/admin/login` | Filament's login screen | 500 → `ADMIN_*` missing, or the schema is half-migrated |
| 3 | sign in with `ADMIN_EMAIL` / `ADMIN_PASSWORD` | The admin dashboard | "credentials do not match" → the seeder ran before you set them; redeploy to re-seed |
| 4 | **Admin → Integrations → Storage** | **Writable** | *Not writable* → the R2 endpoint or path-style flag is wrong; see below |
| 5 | `/open-a-shop` | The application form | — |
| 6 | `/console` while signed out | Redirect to sign-in | 500 → sessions table missing |

The storage probe is the one that pays for itself: it writes and deletes a test object on the
proof disk, so it reports a misconfigured bucket in two seconds instead of after a failed upload.

If the probe page itself returns 500 rather than a verdict, the S3 driver cannot be built; the
image must contain `league/flysystem-aws-s3-v3` (`composer require league/flysystem-aws-s3-v3`).
Without it, every `Storage::disk('proofs')` and `Storage::disk('artwork')` call dies with
`Class "League\Flysystem\AwsS3V3\PortableVisibilityConverter" not found` before any request to R2,
which looks exactly like a broken bucket from the outside.

If it says *Not writable*, check in this order — each is a silent failure if missed:

1. `AWS_ENDPOINT` — must be the **real** `https://<32-hex-account-id>.r2.cloudflarestorage.com`
   from **R2 → Manage R2 API Tokens → Account ID**. The angle-bracket string in this checklist and
   in `render.yaml` is notation, not a value. Pasting it literally produces a hostname curl
   rejects with `cURL error 3: URL rejected: Bad hostname` — the write fails, and an upload shows
   "That file could not be stored just now" rather than 500 after #123.
2. `AWS_USE_PATH_STYLE_ENDPOINT` — must be `true`; R2 does not serve virtual-hosted buckets.
3. `AWS_DEFAULT_REGION` — must be `auto`; any other region fails signature validation.
4. The token's permission — *Object Read & Write*, and scoped to this bucket.

---

## Stage 8 — Payment (only if you are demoing it)

Without the three `PAYMONGO_*` variables, checkout fails at the payment step and everything else
still works. To demo it:

1. PayMongo dashboard → **Developers** → **Webhooks** → add
   `https://<your-url>/webhooks/paymongo`, subscribed to payment events.
2. Copy the webhook's **secret key** into `PAYMONGO_WEBHOOK_SECRET`.
3. Copy the public and secret API keys into `PAYMONGO_PUBLIC_KEY` / `PAYMONGO_SECRET_KEY`.
4. Redeploy, then walk one job through checkout end to end. This is the one path where
   `QUEUE_CONNECTION=sync` differs from the documented deployment — settlement happens inside the
   webhook request — so it is worth testing by hand rather than assuming.

---

## When it goes wrong

| Symptom | Cause | Fix |
|---|---|---|
| `[3159] Connections using insecure transport are prohibited` | no CA reached PDO | `DB_SSL_CA_PEM` is empty |
| `Cannot connect to MySQL using SSL` | the CA is found and **rejected** | either the wrong CA (re-download), or the certificate has no SAN — see the next row |
| …and the CA is definitely right | PHP verifies the peer **name**, and a certificate without a SAN can never satisfy it | set `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=false`. TiDB's certificate has a SAN, so this is the self-hosted case, not TiDB's |
| `[2002]` with a timeout | network, not credentials | port `4000`; the IP allowlist; the host has no `https://` in front of it |
| `s3.auto.amazonaws.com` does not resolve | `AWS_ENDPOINT` empty | paste the R2 endpoint |
| Uploads 404 or are refused | path-style addressing off | `AWS_USE_PATH_STYLE_ENDPOINT=true` |
| Signature errors from R2 | wrong region | `AWS_DEFAULT_REGION=auto` |
| The site takes ~1 minute to load | free plan spin-down after 15 idle minutes | open it once before the demo |
| A whole month has no invoice | the boot hook bills the period containing *today*, and a month with no traffic never boots | known limitation; see DEPLOY-RENDER.md |
| Emailed links point at `localhost` | `APP_URL` unset | stage 6 |

---

## After the defense

Nothing to clean up on Render beyond **Suspend** on the service if you want the hours back. TiDB
and R2 both have free allowances that do not expire, so the data survives.

The one thing that *will* happen is the free instance spinning down and the first visitor waiting
a minute. If that matters, a paid instance is the only fix — the application is already correct
for either.
