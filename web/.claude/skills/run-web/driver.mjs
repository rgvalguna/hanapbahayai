#!/usr/bin/env node
// Driver for the HanapBahay AI web app (Next.js 15).
//
// Drives a *running* Next dev/prod server with headless Chrome: navigates a
// route, asserts the SSR HTML contains an expected marker, and writes a PNG
// screenshot. Off-the-shelf chromium-cli is not available on Windows, so the
// "browser" here is the locally installed Chrome in --headless mode.
//
// Usage (server already running on :3000 — the normal path):
//   node driver.mjs                          # screenshot "/" + assert hero text
//   node driver.mjs --route /financial       # any route
//   node driver.mjs --route / --expect "Find the right home"
//   node driver.mjs --base http://localhost:3001 --route /
//
// Self-serve mode (driver starts + stops `pnpm dev` itself):
//   node driver.mjs --serve --route /
//
// Screenshots land in <skill>/screenshots/ (git-ignored). Exit 0 = marker
// found + screenshot written; exit 1 = anything failed.

import { spawn, spawnSync } from "node:child_process"
import { existsSync, mkdirSync, statSync } from "node:fs"
import { fileURLToPath } from "node:url"
import { dirname, join } from "node:path"
import http from "node:http"

const __dirname = dirname(fileURLToPath(import.meta.url))
const SHOT_DIR = join(__dirname, "screenshots")
const WEB_DIR = join(__dirname, "..", "..", "..") // web/

// ── args ──────────────────────────────────────────────────────────────────
const args = process.argv.slice(2)
const opt = (name, def) => {
  const i = args.indexOf(name)
  return i >= 0 && args[i + 1] ? args[i + 1] : def
}
const has = (name) => args.includes(name)

const base = opt("--base", "http://localhost:3000")
const route = opt("--route", "/")
const serve = has("--serve")
const url = route.startsWith("http") ? route : base + route
// Default expected marker per known route; override with --expect.
const expectDefaults = { "/": "Find the right home", "/financial": "Loan Simulator" }
const expect = opt("--expect", expectDefaults[route] ?? "HanapBahay")

// ── chrome discovery ────────────────────────────────────────────────────────
function findChrome() {
  if (process.env.CHROME_PATH && existsSync(process.env.CHROME_PATH))
    return process.env.CHROME_PATH
  const candidates = [
    `${process.env["ProgramFiles"]}\\Google\\Chrome\\Application\\chrome.exe`,
    `${process.env["ProgramFiles(x86)"]}\\Google\\Chrome\\Application\\chrome.exe`,
    `${process.env.LOCALAPPDATA}\\Google\\Chrome\\Application\\chrome.exe`,
    `${process.env["ProgramFiles(x86)"]}\\Microsoft\\Edge\\Application\\msedge.exe`,
    `${process.env["ProgramFiles"]}\\Microsoft\\Edge\\Application\\msedge.exe`,
    "/usr/bin/google-chrome",
    "/usr/bin/chromium",
  ].filter(Boolean)
  for (const c of candidates) if (existsSync(c)) return c
  throw new Error("Chrome/Edge not found. Set CHROME_PATH=<path to chrome.exe>.")
}

// ── helpers ──────────────────────────────────────────────────────────────────
function get(u) {
  return new Promise((resolve, reject) => {
    const req = http.get(u, (res) => {
      let body = ""
      res.on("data", (d) => (body += d))
      res.on("end", () => resolve({ status: res.statusCode, body }))
    })
    req.on("error", reject)
    req.setTimeout(8000, () => req.destroy(new Error("timeout")))
  })
}

async function waitForServer(u, ms = 60000) {
  const deadline = Date.now() + ms
  while (Date.now() < deadline) {
    try {
      const r = await get(u)
      if (r.status && r.status < 500) return true
    } catch {}
    await new Promise((r) => setTimeout(r, 1000))
  }
  return false
}

function screenshot(chrome, u, out) {
  const prof = join(
    process.env.TEMP || "/tmp",
    "hb-chrome-" + Math.random().toString(36).slice(2),
  )
  // Legacy --headless (NOT --headless=new): on Windows Chrome 1xx+ the new
  // headless mode silently writes no file for --screenshot. Legacy works.
  const r = spawnSync(
    chrome,
    [
      "--headless",
      "--disable-gpu",
      "--no-sandbox",
      "--hide-scrollbars",
      "--no-first-run",
      `--user-data-dir=${prof}`,
      "--window-size=1366,2200",
      `--screenshot=${out}`,
      u,
    ],
    { stdio: "ignore", timeout: 60000 },
  )
  if (r.error) throw r.error
  return existsSync(out) && statSync(out).size > 0
}

// ── serve mode ───────────────────────────────────────────────────────────────
let devProc = null
function startDev() {
  // This repo pins pnpm via corepack (`pnpm` is not on PATH), so go through it.
  devProc = spawn("corepack", ["pnpm@9.15.0", "dev"], {
    cwd: WEB_DIR,
    stdio: "ignore",
    shell: true,
  })
}
function stopDev() {
  if (!devProc) return
  if (process.platform === "win32")
    spawnSync("taskkill", ["/pid", String(devProc.pid), "/T", "/F"], { stdio: "ignore" })
  else devProc.kill("SIGTERM")
}

// ── main ─────────────────────────────────────────────────────────────────────
async function main() {
  mkdirSync(SHOT_DIR, { recursive: true })
  const chrome = findChrome()
  console.log(`chrome: ${chrome}`)

  if (serve) {
    console.log("starting `pnpm dev` (give it ~10s)…")
    startDev()
  }

  console.log(`waiting for ${base} …`)
  if (!(await waitForServer(base))) {
    console.error(`FAIL: no server at ${base}. Start it with: pnpm --filter @hanapbahay/web dev`)
    return 1
  }

  console.log(`GET ${url}`)
  const res = await get(url)
  console.log(`  status ${res.status}, ${res.body.length} bytes`)
  const ok = res.status === 200 && res.body.includes(expect)
  console.log(`  marker ${JSON.stringify(expect)}: ${ok ? "FOUND" : "MISSING"}`)

  const out = join(SHOT_DIR, (route === "/" ? "home" : route.replace(/\W+/g, "_")) + ".png")
  const shot = screenshot(chrome, url, out)
  console.log(`  screenshot: ${shot ? out : "FAILED"}`)

  return ok && shot ? 0 : 1
}

main()
  .then((code) => {
    if (serve) stopDev()
    process.exit(code)
  })
  .catch((e) => {
    console.error("ERROR:", e.message)
    if (serve) stopDev()
    process.exit(1)
  })
