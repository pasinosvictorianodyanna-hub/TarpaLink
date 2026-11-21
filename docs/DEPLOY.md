# Deploying TarpaLink

The repository ships a `Dockerfile` and a `docker-compose.yml` that run the whole
application: nginx, php-fpm, MySQL 8, and the queue worker.

Nothing here has been executed against a running Docker daemon in this
environment — see [Verification status](#verification-status) before you trust
it blindly.

---

## What runs

| Service | What it is | Why it exists |
|---|---|---|
| `app` | nginx + php-fpm in one container (supervisord) | Serves HTTP. Runs migrations on boot. |
| `worker` | `php artisan queue:work` on the same image | **Required.** PayMongo callbacks are queued. |
| `db` | MySQL 8.4 | Production database. |

nginx and php-fpm share a container because splitting them would need a shared
volume just to serve `public/`. The worker is separate because it must be able to
restart — or crash — without dropping HTTP traffic.

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
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | real passwords — the defaults are `change-me` |
| `PAYMONGO_*` | sandbox keys. `PAYMONGO_WEBHOOK_SECRET` is **mandatory** — without it the webhook rejects every request |
| `MAIL_*` | real mailer, or leave `log` and read `storage/logs` |

Then:

```bash
docker compose up -d --build
docker compose logs -f app          # watch the entrypoint
```

On boot the entrypoint runs, in order: publish Filament assets, `storage:link`,
`migrate --force`, then `optimize` (config, route and view caches). Migrations
run **only in `app`** — the worker has `RUN_MIGRATIONS=false` so two processes
cannot race the schema on a rolling restart.

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
- **No scheduled tasks.** There is no scheduler service because nothing is
  scheduled — which also means nothing releases payouts automatically. An admin
  marks them paid on `/admin/payouts`.
- **Single container serves HTTP.** Fine for one host; for horizontal scaling you
  would split nginx out and share `public/` between replicas.

---

## Verification status

The Docker files in this repository have **not been built or run**. Docker
Desktop was installed on the development machine but its daemon was not running,
so `docker build` was never executed.

What *was* verified, on the host:

- `php artisan test` — 142 tests passing
- `php artisan config:cache`, `route:cache`, `view:cache` — all succeed
- `/up` returns 200
- No SQLite-specific code; migrations are portable to MySQL

What remains unverified until someone runs it:

- the image builds (PHP extension set, composer flags, asset stage)
- nginx serves the app through php-fpm
- the worker consumes the queue and settles a payment end to end

Treat the first `docker compose up --build` as a test, not a deploy.
