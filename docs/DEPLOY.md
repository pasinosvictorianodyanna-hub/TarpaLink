# Deploying TarpaLink

The repository ships a `Dockerfile` and a `docker-compose.yml` that run the whole
application: nginx, php-fpm, MySQL 8, and the queue worker.

This stack has been built and run end to end once — see
[Verification status](#verification-status) for exactly what was and was not
exercised.

Free hosting is a different deployment with different rules; it cannot run this
compose file. See [DEPLOY-RENDER.md](DEPLOY-RENDER.md) for what Render's free plan
can and cannot do with this application — the short version is no worker, no cron,
and no disk that survives a restart.

---

## What runs

| Service | What it is | Why it exists |
|---|---|---|
| `app` | nginx + php-fpm in one container (supervisord) | Serves HTTP. Runs migrations on boot. |
| `worker` | `php artisan queue:work` on the same image | **Required.** PayMongo callbacks are queued. |
| `scheduler` | `php artisan schedule:work` on the same image | **Required since #83.** Issues the monthly subscription invoices. |
| `db` | MySQL 8.4 | Production database. |

nginx and php-fpm share a container because splitting them would need a shared
volume just to serve `public/`. The worker is separate because it must be able to
restart — or crash — without dropping HTTP traffic.

The scheduler is a third process for the same reason. `billing:issue` runs daily and is
idempotent, so a restart mid-run costs nothing: it asks whether the current period already
has an invoice rather than how long it has been since the last run. If the scheduler is not
running, shops are simply never billed — silently, which is the failure mode to watch for.

---

## First deploy

```bash
cp .env.docker.example .env
php artisan key:generate --show        # copy the output into APP_KEY
```

Then edit `.env`:

| Variable | What to set |
|---|---|
| `APP_KEY` | the value from `key:generate --show` |
| `APP_URL` | the real public URL, e.g. `https://tarpalink.ph` |
| `APP_PORT` | host port for nginx, default `8080` |
| `APP_TIMEZONE`, `DB_TIMEZONE` | `Asia/Manila` and `+08:00` by default. Keep them in step: a 17:00 due date in UTC is 1 AM the next day in Manila, and MySQL converts TIMESTAMPs through the connection's time zone. Set them before loading real data — changing them later does not rewrite stored rows. |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | real passwords — the defaults are `change-me` |
| `PAYMONGO_*` | sandbox keys. `PAYMONGO_WEBHOOK_SECRET` is **mandatory** — without it the webhook rejects every request |
| `INBOUND_ADDRESS`, `INBOUND_SECRET` | the mailbox replies are forwarded to, and the secret that signs the forward. Both empty means notifications go out with no `Reply-To` and the inbound webhook refuses everything — deliberately, so a half-configured deployment fails closed |
| `GEOCODER_ENABLED` | `true` by default. Set `false` if the container has no outbound HTTPS, or the address box waits for a timeout before saying it cannot help |
| `MAIL_*` | real mailer, or leave `log` and read `storage/logs`. Switching to `smtp` needs `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` and `MAIL_SCHEME` — the example file carries them commented out |
| `PROOF_DISK_DRIVER` | **`s3` before accepting real artwork.** The default `local` writes into the container and is lost on the next rebuild. With `s3`, set `AWS_BUCKET` plus credentials and region |
| `TRUSTED_PROXIES` | only when something else terminates TLS. nginx forwards `X-Forwarded-Proto`, but Laravel ignores it until this names the proxy — without it every emailed link is `http://` |

`PAYMONGO_*`, `INBOUND_ADDRESS` and `INBOUND_SECRET` do not have to stay in `.env`.
They can be saved on `/admin/integrations`, where they are stored encrypted and take
effect on the next request. The environment remains the fallback, and the screen says
which of the two is in force — so rotating a key during an incident does not need a
redeploy.

Then:

```bash
docker compose up -d --build
docker compose logs -f app          # watch the entrypoint
```

On boot the entrypoint runs, in order: publish Filament assets, `storage:link`,
`migrate --force`, then `optimize` (config, route and view caches). Migrations
run **only in `app`** — the worker and the scheduler have `RUN_MIGRATIONS=false`
so no two processes race the schema on a rolling restart.

Four containers come up: `app`, `worker`, `scheduler` and `db`. The scheduler exists
only to run `billing:issue`; if it is not running, subscription invoices are never
issued and nothing reports the failure.

```bash
docker compose ps                   # all four should be Up
docker compose logs scheduler       # should show the schedule loop
```

### Behind HTTPS

The compose file publishes plain HTTP on `APP_PORT`. If something else terminates
TLS — Cloudflare, an ALB, a front nginx — set `TRUSTED_PROXIES` to that proxy, or
every link the app emails will be `http://` and every session cookie will be issued
for the wrong scheme. The container's nginx forwards `X-Forwarded-Proto`; Laravel
ignores it until `TRUSTED_PROXIES` names the proxy.

---

## Create the first administrator

There is no sign-up. `DatabaseSeeder` is demo data and must not run in
production, so `ProductionSeeder` exists for exactly this:

```bash
docker compose exec app php artisan db:seed --class=ProductionSeeder --force
```

It creates the plan catalogue, the six CMS sections, site settings, and one admin
from `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME`. Every write is
`updateOrCreate`, so running it again is harmless.

If `ADMIN_EMAIL` or `ADMIN_PASSWORD` is unset it creates no user and prints a
warning — deliberately. A production account with a guessable password is worse
than a locked panel.

Then sign in at `/admin`.

---

## The queue worker is not optional

`POST /webhooks/paymongo` dispatches `ProcessPayMongoWebhook` onto the queue. If
the worker is not running:

- payments stay `pending` forever,
- no job ever becomes payable,
- the shop's payout screen stays empty,
- and nothing in the UI says why.

Check it is alive:

```bash
docker compose ps worker
docker compose logs --tail=50 worker
```

A healthy worker logs each processed job. A silent one is a broken deployment
even though every page still renders.

---

## Everyday operations

```bash
docker compose logs -f app worker         # both, interleaved
docker compose exec app php artisan about # versions, drivers, cache state
curl -fsS http://localhost:8080/up        # health check, 200 = up
docker compose restart worker             # after changing queue-related env
```

`LOG_STACK=stderr` is set in the compose file, so application logs go to
`docker compose logs` rather than a file inside the container.

---

## Upgrading

```bash
git pull
docker compose up -d --build      # rebuilds, migrates on boot, restarts both
```

Because `optimize` runs on boot, a config change is picked up by a restart — you
do not need to clear caches by hand.

---

## Backups

The database is the only stateful volume (`dbdata`). Uploaded files live under
`storage/app/public` inside the image and are **not** persisted across a rebuild —
see [Known limits](#known-limits).

```bash
docker compose exec db sh -c \
  'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  > backup-$(date +%F).sql
```

---

## Behind a TLS proxy

If you put a load balancer or CDN in front, set:

```dotenv
TRUSTED_PROXIES=10.0.0.1,10.0.0.2
```

Without it the app sees plain HTTP: `route()` emits `http://` URLs and session
cookies lose their `Secure` flag. With it set to `*`, any client that can reach
the app directly can forge `X-Forwarded-For` — so only use `*` when the app is
unreachable except through the proxy.

---

## Known limits

- **PayMongo is sandbox only.** No live charges; the webhook signature check is
  real, the money is not.
- **Proofs are not on object storage.** `FILESYSTEM_DISK=local` means uploaded
  files sit inside the container and are lost on rebuild. Set the S3 disk
  (`AWS_*`) before accepting real artwork.
- **One scheduled task.** `billing:issue` runs daily at 01:10 from the `scheduler`
  service and issues the current period's invoice for every active paid plan. It is
  idempotent, so a missed night is caught up the next one. Nothing releases payouts
  automatically — an admin still marks them paid on `/admin/payouts`.
- **Single container serves HTTP.** Fine for one host; for horizontal scaling you
  would split nginx out and share `public/` between replicas.
- **CI runs SQLite; production runs MySQL.** Every test in the suite runs against
  SQLite, which is more forgiving than MySQL 8 in two ways that have already bitten
  this project: `only_full_group_by` (the reports query selects through a join) and
  `TIMESTAMP` conversion through the connection's time zone. Before the first deploy,
  run the suite against MySQL rather than assuming. Three things have to happen first,
  and the stack does none of them: MySQL is not published to the host, `tarpalink_test`
  does not exist, and the `tarpalink` user has no rights on it.

  ```bash
  # 1. Publish the database to the host. This override is auto-loaded by every
  #    later `docker compose` call, so it has to be removed again at step 4.
  printf 'services:\n  db:\n    ports: ["3307:3306"]\n' > docker-compose.override.yml
  docker compose up -d db

  # 2. Create the schema database and let the app user write to it.
  docker compose exec db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "
    CREATE DATABASE IF NOT EXISTS tarpalink_test;
    GRANT ALL PRIVILEGES ON tarpalink_test.* TO \"tarpalink\"@\"%\";"'

  # 3. Run. All four variables, together: phpunit.xml pins DB_DATABASE to
  #    :memory:, so exporting only DB_CONNECTION asks MySQL for a database
  #    called ":memory:" and the run dies with an error that reads like a
  #    config problem rather than a half-finished override.
  DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=tarpalink_test \
    php artisan test

  # 4. Put the database back behind the compose network.
  rm docker-compose.override.yml && docker compose up -d db
  ```

  Expect 21 minutes, against three on SQLite. That cost is already repaid: the first
  full MySQL run caught a test that had hard-coded a SQLite row id into a version
  number and would have failed on the first real deploy.
- **Inbound mail needs a forwarder.** `/webhooks/inbound` takes a signed JSON body
  and does not speak IMAP. Point a provider's inbound rule (Mailgun, Postmark, or a
  small relay) at it with an `X-Inbound-Signature` HMAC over the raw body. A body that
  has been re-encoded in transit cannot be verified, because the signature covers the
  exact bytes.

---

## Verification status

Built and run on Docker Desktop (Windows, engine 29.9.3), from a clean volume:

| Check | Result |
|---|---|
| `docker compose build` | image built — `phpbase` → `vendor` → `assets` → `runtime` |
| `docker compose up -d` | db healthy → app healthy → worker started, in that order |
| Migrations on MySQL 8.4 | all applied on first boot |
| `GET /up` | 200 |
| `/`, `/shops`, `/login`, `/admin/login` | 200 |
| Vite CSS from `public/build` | 200 |
| `ProductionSeeder` | 3 plans, 6 sections, 1 admin |
| Admin credentials | password verifies, `canAccessPanel` allows |
| Signed webhook → queue → settlement | 200, worker `DONE` in 84ms, payment `paid` |
| Full suite on MySQL 8.4 | 505 passed, 2,438 assertions, 21 min |

One bug was found this way and fixed: the worker originally declared
`depends_on: app: service_started`, so it booted before migrations had created
`queue_jobs` and crash-looped until the schema caught up. It now waits for
`service_healthy`. If you copy this compose file anywhere, keep that condition —
`service_started` is not enough when the app migrates on boot.

A second one came out of the suite run: a test had frozen a SQLite row id into an
assertion and passed for the whole life of the project, because the suite had never
been run on the database production uses. The recipe above is what surfaced it.

The build stage order matters for the same reason: `assets` runs **after**
`vendor`, because the panel theme imports Filament's `theme.css` out of
`vendor/`. Building CSS first fails with "Unable to resolve @import" and no
amount of retrying helps.

Still unverified:

- behaviour behind a real TLS proxy (`TRUSTED_PROXIES` is untested in situ)
- object storage (`FILESYSTEM_DISK=s3`) — only `local` has been exercised
- a live PayMongo account; everything here is sandbox and signed by hand

---

## If you also develop locally

The compose file reads `.env`, which is also the file `php artisan serve` reads.
They want different values — SQLite and `127.0.0.1` locally, MySQL and `db` in
the containers — so keep them apart:

```bash
cp .env .env.local        # your local settings
cp .env.docker.example .env   # for compose
# ...before running the app locally again:
cp .env.local .env
```

