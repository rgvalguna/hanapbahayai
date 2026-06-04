---
name: run-web
description: Run, start, build, dev-serve, smoke-test, or screenshot the HanapBahay AI web app (Next.js 15 frontend in web/). Use when asked to launch the web UI, screenshot a page/route, or confirm a frontend change renders.
---

# Run the HanapBahay AI web app (Next.js 15)

The web app is a Next.js 15 / React 19 / Tailwind v4 frontend in `web/`. It is
part of a pnpm + turbo monorepo and depends on the workspace package
`@hanapbahay/schemas`, which **must be built first** (the web app imports its
compiled `dist/`).

It is driven headlessly by **`.claude/skills/run-web/driver.mjs`** — a
zero-dependency Node script that drives a running Next server with **headless
Chrome** (off-the-shelf `chromium-cli` is not available on Windows; the local
Chrome install is the browser). The driver navigates a route, asserts the SSR
HTML contains an expected marker, and writes a PNG screenshot.

> **Paths below are relative to the repo root** (`RealEstateApp/`) unless a
> step says "from `web/`". The driver lives at
> `web/.claude/skills/run-web/driver.mjs`.
>
> **Use PowerShell, not Git Bash.** Git Bash (MSYS) rewrites a leading-slash
> arg like `--route /` into a Windows path (`C:/Program Files/Git/`), breaking
> the driver. Every command here is PowerShell.

## Prerequisites

- **Node 22+** (tested on 24.14) and **corepack** (ships with Node). pnpm is
  pinned at 9.15.0 and invoked through corepack — do **not** rely on a global
  `pnpm` being on PATH.
- **Google Chrome** (or Edge) installed — used for screenshots. The driver
  auto-detects `C:\Program Files\Google\Chrome\Application\chrome.exe` and
  Edge; override with `CHROME_PATH`.

## Build (run once, from repo root)

```powershell
corepack pnpm@9.15.0 install
corepack pnpm@9.15.0 --filter @hanapbahay/schemas build
```

Then create the web env file (from `web/`):

```powershell
Copy-Item .env.local.example .env.local -Force
```

> **Applied patch — phantom dependency.** `web/package.json` originally listed
> `@radix-ui/react-sheet@^0.1.0`, which does not exist on npm (install fails
> with `ERR_PNPM_FETCH_404`). Nothing imports it (the mobile drawer is
> hand-rolled in `components/layout/MobileSidebar.tsx`), so it was removed from
> `web/package.json`. If you re-add it, install will break again.

## Run — agent path (driver)

**One-shot** (driver starts `pnpm dev`, screenshots, asserts, tears down). Run
from `web/`:

```powershell
node .claude\skills\run-web\driver.mjs --serve --route /
```

Exit 0 = HTTP 200 + expected marker found + screenshot written. Screenshots
land in `web/.claude/skills/run-web/screenshots/` (git-ignored).

**Against an already-running server** (faster for poking several routes). Start
the dev server in one terminal, from `web/`:

```powershell
corepack pnpm@9.15.0 dev
```

Then in another terminal, from `web/`:

```powershell
node .claude\skills\run-web\driver.mjs --route /
node .claude\skills\run-web\driver.mjs --route /financial
```

Driver flags:

- `--route <path>` — route to capture (default `/`). Known markers are built in
  for `/` and `/financial`.
- `--expect "<text>"` — override the asserted SSR marker.
- `--base <url>` — server base (default `http://localhost:3000`).
- `--serve` — driver starts and stops the dev server itself.

Good routes that render **without the backend API**:
`/` (landing) and `/financial` (fully interactive Pag-IBIG / bank loan
simulator + DTI gauge — pure client-side math).

## Run — human path

From `web/`: `corepack pnpm@9.15.0 dev`, then open http://localhost:3000.
Useless for an automated check (no window in a headless run) — use the driver.

## Gotchas

- **`@radix-ui/react-sheet` is a phantom dep** — see the patch note above.
  Removing it is required for `pnpm install` to succeed.
- **Build `@hanapbahay/schemas` first.** Its `package.json` `exports` point at
  `dist/`, which does not exist until you run its `build`. A fresh checkout will
  fail to compile the web app until you do.
- **Git Bash mangles `--route /`** into `C:/Program Files/Git/...`. Always run
  the driver from PowerShell.
- **Use legacy `--headless`, not `--headless=new`.** On Windows Chrome, the new
  headless mode silently writes no file for `--screenshot`. The driver uses
  legacy `--headless` (with `--no-sandbox`); don't "modernize" it.
- **The brand-green CTA button looks near-white** in screenshots (e.g. "Start
  your free consultation" on `/`). That's an app-side Tailwind v4 quirk
  (`bg-[--color-brand-600]` arbitrary-value resolution), not a launch problem.
- **Next rewrites `web/tsconfig.json` on first `dev`** (adds `allowJs`,
  `noEmit`, `incremental`, `isolatedModules`). Expected; harmless.
- **The `(app)` and `(auth)` route groups** (`/dashboard`, `/listings`,
  `/login`, …) call the Laravel API, which is not runnable here (see
  `run-api`). They still render their SSR shell, but data-fetching states will
  be empty/loading. `/` and `/financial` are the reliable screenshot targets.

## Troubleshooting

- `ERR_PNPM_FETCH_404 ... @radix-ui/react-sheet` → the phantom dep is back in
  `web/package.json`; remove it.
- `Cannot find module '@hanapbahay/schemas'` / type errors on its imports →
  you skipped `corepack pnpm@9.15.0 --filter @hanapbahay/schemas build`.
- Driver prints `no server at http://localhost:3000` → start the dev server
  first, or use `--serve`.
- Driver prints `Chrome/Edge not found` → set `CHROME_PATH` to your
  `chrome.exe`.
- `screenshot: FAILED` → almost always the `--headless=new` trap; ensure you
  haven't edited the driver's Chrome flags.
