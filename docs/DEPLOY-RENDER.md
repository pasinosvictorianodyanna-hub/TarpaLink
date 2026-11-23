# Deploying to Render's free plan

`DEPLOY.md` describes the intended deployment: one host, four containers, a database
on a volume. This page describes a different thing — putting the same image on
Render's free compute plan — and it is a different thing because **three of the four
containers cannot come along**. Read the table before you start, not after.

The blueprint is `render.yaml`. It encodes everything that can be encoded; the
secrets and the database are yours to fill in.

## What the free plan does to this application

Every number below is from Render's own free-tier documentation, not from
experience, because this deployment has never been run there. See
[Verification status](#verification-status) for what has.

| Free-plan behaviour | What it does here |
|---|---|
| **Spins down after 15 minutes idle**, ~1 minute to come back | First request after a quiet period waits on a loading page. Harmless for a demo, wrong for a link you hand to a client. |
| **Ephemeral filesystem**, no persistent disks | Uploaded proofs are lost on every redeploy, restart **and spin-down**. A shop that uploads artwork and comes back after lunch finds it gone. |
| **No background workers** | The queue worker does not exist. `QUEUE_CONNECTION=sync` runs `ProcessPayMongoWebhook` inside the webhook request instead, so settlement still happens. |
| **No cron jobs** | `billing:issue` never runs. Subscription invoices are issued once, when a shop subscribes, and then never again. **This is a functional gap, not a performance one.** |
| **No shell, no one-off jobs** | `php artisan db:seed` cannot be run by hand. `RUN_SEEDER=true` seeds on boot instead; `ProductionSeeder` is `updateOrCreate` throughout, so it is safe on every start. |
| **Outbound SMTP (25, 465, 587) blocked** | `MAIL_MAILER=smtp` cannot deliver. Use `log` for a demo, or an HTTP mail API (Resend, Postmark) for anything real. |
| **750 instance hours per month** | One always-on service is ~744 h, so a single service fits — but only just, and spin-down is what keeps it inside the allowance. |
| **No MySQL, at any price** | The database has to live somewhere else. See below. |
| **TLS terminated at the load balancer** | Laravel sees plain HTTP unless `TRUSTED_PROXIES` names the proxy. The blueprint sets `*`, which is safe here only because the container is unreachable except through Render. |

## The database

Render offers Postgres, and its **free Postgres is deleted 44 days after creation**
(30 days, then a 14-day grace period). That is not a database you can put a capstone
on and defend a month later.

So the MySQL has to be external. Two options that cost nothing:

- **TiDB Cloud Starter** — free allowance of 5 GiB row storage and 50M request units
  per MySQL-compatible instance, up to five instances. It speaks the MySQL protocol,
  so `DB_CONNECTION=mysql` works unchanged, **but it is not MySQL 8**. The suite has
  never been run against it, and `docs/DEPLOY.md` explains why that matters here
  (`only_full_group_by`, `TIMESTAMP` conversion). Treat it as an experiment and run
  the suite against it before trusting it.
- **Any free MySQL from a shared host** — usually 400 MB–1 GB, usually with an
  uptime promise of "sometimes". Adequate for a demo, and closer to MySQL 8 than
  TiDB is.

TiDB requires TLS with its own CA. Download the CA certificate, get it into the image,
and point `MYSQL_ATTR_SSL_CA` at it — `config/database.php` already passes that
variable through to PDO.

## Deploying

1. **Push the repository.** Render builds from Git, not from your laptop.
2. **Create the database first**, wherever you chose, and note host, port, database
   name, user and password.
3. **New → Blueprint**, point it at this repository. Render reads `render.yaml` and
   creates the web service.
4. **Fill in the `sync: false` variables** when prompted. There are fifteen; the
   blueprint marks every secret this way rather than committing one.
5. **Set `APP_URL`** to the `onrender.com` URL Render assigns, then redeploy. It
   cannot be known before the service exists, and a stale value puts `localhost`
   into emailed links.
6. **Set `ADMIN_EMAIL` and `ADMIN_PASSWORD`.** Without them `ProductionSeeder` logs
   `ADMIN_EMAIL / ADMIN_PASSWORD not set — no administrator created` and you have a
   deployment nobody can sign into.

## Verifying it worked

There is no shell on the free plan, so verification happens through the app:

- `https://<name>.onrender.com/up` returns 200. If it does not, the deploy log is the
  only place to look.
- The log shows `[entrypoint] nginx will listen on port 10000`, then
  `running migrations`, then `seeding reference data`. All three lines should appear
  on every boot.
- `/admin/login` renders and the credentials from step 6 sign in.
- A signed PayMongo webhook settles a payment — this is the one path where
  `QUEUE_CONNECTION=sync` differs from the documented deployment, so it is the one
  worth testing by hand.

## The three gaps, and what closing them costs

1. **Proofs on an ephemeral disk.** Point `FILESYSTEM_DISK` and `PROOF_DISK_DRIVER` at
   S3 or Cloudflare R2 (10 GB free). The `AWS_*` block is already in
   `.env.docker.example`; nothing in the code needs to change.
2. **No recurring invoices.** Either accept that subscription billing is manual, or
   run the scheduler somewhere that has cron — a $5 VPS, or a paid Render instance.
   Adding an HTTP endpoint that triggers `billing:issue` would also work and would add
   a public command trigger to the attack surface; that trade was not made here.
3. **No worker.** `sync` is correct for this application because the only queued job
   is the webhook settlement, and it is fast. If a second job type is ever added that
   is slow or retryable, `sync` stops being a reasonable choice.

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

**Not tested:** anything that requires Render itself. The spin-down, the 1-minute cold
start, the ephemeral disk, the blocked SMTP ports and the TiDB connection are all
documented behaviour, not observed behaviour. Treat this page as a recipe that has
been read carefully, not one that has been cooked.
