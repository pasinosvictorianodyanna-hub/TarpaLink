# TarpaLink

A job-order platform for the Philippine printing industry. Customers find a print shop, configure a job (tarpaulin, stickers, flyers, DTF shirts), get an exact price from that shop's own price matrix, and approve a proof before anything goes to press. Shops get a queue, a production board, and a CMS-managed storefront presence.

**This is not e-commerce.** There is no cart, no shipping, no inventory. Customers pick up at the counter. The unit of work is a *job*, not an order.

---

## Status

| Area | Owner | State |
|---|---|---|
| Landing page | Dev A | in progress |
| Customer side (directory, builder, checkout, status, account) | Dev A | in progress |
| Shop panel (Filament) | Dev B | not started |
| Admin panel (Filament) | Dev B | not started |
| Integrations (PayMongo, SMTP, Maps, storage) | split | not started |

## Stack

- **PHP 8.2+ / Laravel 12** — the whole application, one repo, one deploy
- **Vue 3 (Composition API) + Vite** — the interactive storefront (order builder, checkout, job status, account)
- **Filament v4 (Livewire 3 + Alpine)** — admin panel and shop panel; this is the CMS
- **MySQL 8** — 12 tables
- **PayMongo (sandbox)** — payments, no live charges

The rule that keeps it coherent: **Vue renders, Filament edits, Laravel decides.** Any price the browser shows is recomputed server-side before an order is created. The server always wins.

## Layout

```
index.html              interactive design reference — 18 screens, no build step
docs/
  SPLIT.md              who builds what, and in which order
  ISSUES.md             backlog for the second developer
  CONTRACT.md           the price engine interface both halves depend on
app/                    Laravel application (added at scaffold time)
```

`index.html` is the visual source of truth. Open it in a browser — use the area tabs to
move between Landing, Customer, Shop panel and Admin panel. It is a static mockup; no
framework code lives in it. It moves to `design/` once the Laravel app is scaffolded.

## Running locally

Not scaffolded yet. Once the Laravel app lands:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run dev
php artisan serve
```
