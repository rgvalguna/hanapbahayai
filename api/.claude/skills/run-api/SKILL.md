---
name: run-api
description: Run, test, or smoke-check the HanapBahay AI backend (Laravel API in api/). The full Laravel app cannot boot (incomplete skeleton + impossible deps); use this to directly invoke and verify the pure-PHP domain logic (Pag-IBIG / bank loan / DTI / amortization / hidden-cost math).
---

# Run the HanapBahay AI API (Laravel skeleton)

> **The full Laravel app does not boot, and cannot be made to boot here.** This
> is not a platform/OS issue — the code in `api/` is an incomplete skeleton with
> impossible dependencies (details under Gotchas). What *is* runnable — and what
> most API PRs actually touch — is the **pure-PHP domain logic** in
> `api/app/Modules/Financial`. This skill drives that directly.

The driver is **`.claude/skills/run-api/smoke.php`** — a standalone PHP script
that registers a tiny PSR-4 autoloader (`App\` → `api/app/`) and exercises the
financial-domain classes with realistic Philippine inputs, asserting their
outputs. No database, no Redis, no Laravel, no `composer install`.

> Paths below are relative to the **repo root** (`RealEstateApp/`). Use
> PowerShell.

## Prerequisites

- **PHP 8.3+** on PATH (tested on XAMPP PHP 8.3.30). No extensions beyond the
  CLI default are needed — the driven classes are framework-free pure math.

That's it. Do **not** run `composer install` (it can't resolve — see Gotchas).

## Run — agent path (direct invocation)

```powershell
php api\.claude\skills\run-api\smoke.php
```

Exit 0 = all 18 checks pass. Expected output ends with:

```
──────────────────────────────────────────────────
Result: 18 passed, 0 failed
```

It covers `Amortization` (PMT, schedule, inverse max-loan, zero-interest),
`PagIBIG` (eligibility gates, 90% LTV cap, rate tiers), `DTIEngine` (ratio +
safe/caution/warning/critical classification), `BankFinancing` (teaser +
repriced phases, bank presets, bad-preset throw), and `HiddenCosts` (DST,
transfer tax, totals).

### Extending the smoke / invoking one class

All these classes are `final` with `public static` methods and native-type
args. Reuse the autoloader shim (`_autoload.php`) so the inline snippet has no
`$` for PowerShell to expand:

```powershell
php -r "require 'api/.claude/skills/run-api/_autoload.php'; echo App\Modules\Financial\Amortization::monthlyPayment(1000000,6.0,240);"
```

(Prints `7164.3105847817` — monthly payment on a ₱1M, 6%, 20-year loan.) Add
new `check(...)` lines to `smoke.php` when a PR touches these modules.

> Do **not** inline the autoloader closure into `php -r "..."` in PowerShell:
> its `$class`/`$file` variables get expanded by the shell and the PHP fails to
> parse. Always `require '_autoload.php'`.

## Gotchas — why the full app can't run

All three are confirmed in this container, not guessed:

- **Impossible dependency.** `api/composer.json` requires
  `laravel/framework ^13.0`, which does not exist (latest is 12.x). `composer
  install --ignore-platform-reqs --dry-run` fails to resolve:
  `pestphp/pest-plugin-laravel ... requires laravel/framework ^11.39.1|^12.0.0
  ... conflicts with your root composer.json require (^13.0)`.
- **No framework entrypoints.** `api/artisan`, `api/public/index.php`, and
  `api/config/app.php` are all absent. Only `bootstrap/app.php` and three
  partial config files (`cors`, `horizon`, `sanctum`) exist. So even with a
  fixed `composer.json` there is nothing to `php artisan serve`.
- **Missing PHP extension.** `composer.json` hard-requires `ext-redis`, which
  this PHP build does not have (`php -m` has no `redis`). `pdo_pgsql`/`pgsql`
  are present, but `ext-redis`/`sqlite3` are not.
- **Infra would still be needed.** `docker-compose.yml` defines Postgres
  (PostGIS + pgvector), Redis, and OpenSearch; the Docker daemon was not running
  in this container. None of that is required for the `smoke.php` path.

Because of the above, the financial domain modules (deliberately written
"no Eloquent, no I/O" per their own docblocks) are the only API code that runs
today — and they're the substance of the product's affordability engine.

## Troubleshooting

- `'php' is not recognized` → PHP isn't on PATH (e.g. add `D:\Xampp\php`).
- `Class "App\Modules\Financial\..." not found` → run from the **repo root** so
  the autoloader's `__DIR__/../../../app` resolves to `api/app`; or invoke the
  script by its full path as shown above.
- Tempted to `composer install`? It will fail to resolve. Don't — the smoke
  path needs no vendor dir.
