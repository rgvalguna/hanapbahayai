# HanapBahay AI — Architecture Assessment & Transformation Plan

> Principal-architect review of the current repository as the baseline for a fiduciary AI co-buyer platform for the Philippine market.
>
> **Scope note:** The brief framed this as "transform `googlemaps/property-finder`." In reality this repository is **already a HanapBahay AI scaffold** — a `pnpm` + Turborepo monorepo with a working Laravel 11 API, a Next.js 15 App-Router frontend, shared Zod contracts, Philippine reference data, and a streaming Claude tool-use loop. So this document audits *what exists*, identifies the gap to the product vision, and lays out the path to production. Where the original Google repo concepts still apply (Maps SDK, listing UI patterns), they are called out as **reusable patterns**, not as the literal codebase.

---

## 0. TL;DR for the build team

1. **The scaffold is genuinely good bones.** Module boundaries (`Modules/AI`, `Modules/Financial`), the SSE tool-use orchestrator, the PostGIS+pgvector schema, and the Zod-contract package are the right shapes. ~60% of the *structure* of the production system is already drawn.
2. **It does not run end-to-end today.** There are four blocking, code-level defects (below) that make the AI search/scoring path return empty or throw, and one that breaks `php artisan migrate` on the provided Docker image.
3. **The "intelligence" is mostly stubbed.** `ScoreProperty` is an explicit placeholder; `SearchListings` is raw SQL (not OpenSearch); pgvector is provisioned but never written to; risk engines are partial; Veriff/PayMongo/S3 are README-only.
4. **Fastest path to MVP** is *fix-forward on this scaffold*, not a rewrite. Estimated 6–8 engineer-weeks to a credible Phase-1 + Phase-2 MVP.

### Blocking defects found during this review (fix before anything else)

| # | Severity | File | Problem |
|---|---|---|---|
| B1 | **Critical** | `api/app/Modules/AI/Tools/SearchListings.php:12`, `AdvisorController.php:82`, `FlagRedFlags.php:96` | Queries filter `where('status', 'live')`, but the `listings` migration defines statuses as `active\|under_review\|sold\|off_market\|archived` (default `under_review`). `'live'` never matches → **search, listing-context injection, and price red-flags all silently return empty.** |
| B2 | **Critical** | `SearchListings.php:71,88` | Selects/returns `hoa_php_monthly`, a column that **does not exist** in `create_listings_table`. Postgres raises `undefined column` → the primary AI search tool throws on every call. |
| B3 | **Critical** | `database/migrations/...000012_create_geo_data_tables.php:72,84` | Calls `create_hypertable(...)` (TimescaleDB) but the dev image is `pgvector/pgvector:pg16` and `init.sql` leaves `timescaledb` **commented out** → `php artisan migrate` aborts. |
| B4 | **High** | `Modules/AI/ClaudeOrchestrator.php:125-145` + persistence | Tool-use/`tool_result` turns are **never persisted**; only final assistant text is saved. Next user message rebuilds history from `ConsultationMessage` (text only) → the model loses all tool context across turns, and listing context is re-injected as a fresh `user` block every turn, busting the conversation prefix cache. |
| B5 | **Medium** | `Modules/AI/PromptBuilder.php:27-38` | Both system blocks are marked `cache_control: ephemeral`, including the **per-user, per-turn** profile block. Caching a block that changes every request yields ~0% hit rate while paying cache-write surcharge. Only the static base prompt + tools should be cached. |

---

# 1. Repository Assessment

## 1.1 Top-level architecture

```
RealEstateApp/                  (pnpm workspace + Turborepo)
├── web/        Next.js 15 (App Router, React 19, TS, Tailwind) — buyer SPA
├── api/        Laravel 11 / PHP 8.3 — REST + AI orchestration + financial math
├── packages/
│   └── schemas/  @hanapbahay/schemas — shared Zod contracts (TS)
├── sdk-ph/     Philippine reference data (BIR zonal, Pag-IBIG, RPT, PSGC) as JSON
├── prompts/    Versioned system prompt + 6 Anthropic tool schemas (JSON)
└── infra/      docker/postgres/init.sql (PostGIS + pgvector extensions)
docker-compose.yml  → postgres(pgvector pg16), redis 7, opensearch 2.13, mailpit
```

**Strength:** clean polyglot monorepo. The Zod package is the linchpin — it lets the TS frontend and (via generated types/validation) the API agree on a single contract surface. Turborepo gives cached builds.

**Limitation:** there is **no shared type bridge into PHP**. Zod lives in TS only; Laravel re-validates by hand. Drift between `packages/schemas/*.ts` and Laravel `validate()` rules is unenforced. `packages/financial/` is referenced in the README but **not tracked** — financial primitives currently live only in PHP.

## 1.2 Frontend architecture (`web/`)

- **Routing:** App Router with two route groups — `(app)` (authenticated: advisor, dashboard, financial, listings, profile, shortlists) and `(auth)` (login/register). Clean separation; layouts per group.
- **State:** Zustand stores (`lib/store/auth.ts`, `lib/store/ui.ts`) — lightweight, appropriate.
- **Data layer:** thin `lib/api/*` clients (`client.ts`, `auth.ts`, `listings.ts`, `advisor.ts`, `profile.ts`) over the REST API.
- **Domain components:** `components/financial/{DTIGauge,LoanSimulator}`, `components/listings/{ListingCard,ListingGrid,ScoreBadge,SearchFilters,WarningChips}`. These directly mirror the product's affordability-first framing (warning chips, score badges) — well-conceived.

**Strengths:** the component vocabulary is *buyer-protection-first* (ScoreBadge, WarningChips) rather than listing-broker-first. That's the product thesis encoded in UI.
**Limitations:** no map component is tracked (the Google-repo Maps integration would slot in here); advisor page consumes SSE but streaming-reconnection/cancel handling needs verification; no React Query/SWR caching layer visible (hand-rolled clients), which will hurt as data dependencies grow.
**Reusability:** **High** — keep entirely; this is purpose-built, not generic.

## 1.3 Backend architecture (`api/`)

- **Controllers (57-style thin layer):** `Auth`, `Profile`, `Listing`, `Financial`, `Advisor`, `Shortlist`, `Broker`, `Admin`. Reasonable REST surface under `/api/v1`.
- **Modules (the real value):**
  - `Modules/Financial/` — **pure, static, dependency-free PHP**: `Amortization`, `PagIBIG`, `BankFinancing`, `DTIEngine`, `HiddenCosts`. These are unit-testable and correct in shape (see §1.3.1).
  - `Modules/AI/` — `ClaudeOrchestrator` (SSE streaming + tool loop), `PromptBuilder` (system prompt + profile injection w/ caching intent), `ToolRegistry` (name→handler map, loads JSON schemas), `Tools/` (6 handlers).
- **Models:** `User`, `UserFinance`, `UserProfile`, `Listing`, `Developer`, `Broker`, `Consultation`, `ConsultationMessage`, `Shortlist`, `Recommendation`. UUID PKs on `Listing` (`HasUuids`), Scout `Searchable` trait present.
- **Auth:** Sanctum SPA cookie auth + OTP (`otp/request`,`otp/verify`) + Google/Apple OAuth routes. Role middleware (`role:broker|admin`). Horizon configured.

### 1.3.1 Financial engine — current correctness

`PagIBIG::simulate()` (Modules/Financial/PagIBIG.php) is the strongest module: real HDMF Circular 461-B values — ₱6M cap, 30-yr term, age-75 maturity, 24-month contribution minimum, 90% LTV, and the 6-tier rate ladder (5.375%→9.5%). Eligibility gating is correct and returns structured `ineligibility_reason`. **This is production-grade domain logic.**

`DTIEngine::evaluate()` thresholds (safe 0.30 / caution 0.35 / warning 0.40) match Philippine bank practice and back-calculates `recommended_max_loan` from `Amortization::maxAffordableLoan`. **Caveat:** the engine computes the ratio on **gross** income, while `PromptBuilder` (line 92-95) advertises max amortization on **take-home** (`gross*0.85*0.30`). The README states "35% of *gross*." Pick one basis and make all three agree, or the advisor will contradict the gauge. Recommend: **gross for DTI gating** (lender reality), **take-home for the "sustainability" overlay** — but label them distinctly to the user.

**Reusability:** **Very High** — this is the moat. Keep, test, extend.

## 1.4 Data flow (current)

```
Browser (Next.js) ──REST/JSON──▶ Laravel controllers ──▶ Eloquent ──▶ Postgres
        │                                  │
        │  POST /advisor/.../messages      └─▶ Modules/Financial (pure compute)
        ▼  (SSE)
  AdvisorController.sendMessage ──▶ ClaudeOrchestrator.stream
        │   1. PromptBuilder.build(user)  → system[] (static + profile)
        │   2. buildMessages()            → DB history + listing-context user block
        │   3. loop (≤5 iters): Anthropic /v1/messages (stream)
        │        ├─ text deltas  ──▶ echo "data: {...}\n\n" (SSE to browser)
        │        └─ tool_use     ──▶ ToolRegistry.dispatch → Tools/* → Postgres/PostGIS
        └─ persist final assistant text + token counters to ConsultationMessage
```

**Strength:** the streaming + multi-tool-iteration loop is correctly implemented (SSE buffering, `input_json_delta` accumulation, multi-block assistant turns, stop-reason handling). This is non-trivial and done well.
**Limitations:** see **B4** (tool turns not persisted, context re-injection) and **B5** (cache mis-targeting). Also the SSE handler runs the whole Anthropic round-trip **inside the PHP request worker** — under `php artisan serve` / FPM this pins a worker for up to 120s × 5 iterations. Fine for demo, a scaling cliff for production (see §8).

## 1.5 Search architecture

- **Declared:** OpenSearch 2.13 (compose), Scout `Searchable` on `Listing`, `toSearchableArray()` defined.
- **Actual:** `SearchListings` tool issues **raw Eloquent/Postgres** queries (`ilike`, `whereJsonContains`, `ST_DWithin`). **OpenSearch is never queried by the AI path.** Scout indexing is configured but the retrieval tool bypasses it.

**Strength:** the PostGIS `ST_DWithin(location::geography, …)` radius filter is correct and indexed (GIST on `location`).
**Limitations:** keyword search is `ILIKE %q%` (no relevance ranking, no typo tolerance, no Tagalog/English synonymy, full-table scan on large sets). The pgvector `listing_embeddings` table + `ivfflat` index exist but **nothing writes embeddings** and no tool reads them → **semantic search is dead code today.**
**Reusability:** schema is reusable; the retrieval layer must be rebuilt to actually use OpenSearch (BM25 + filters + geo) and pgvector (semantic re-rank). See §3.

## 1.6 Geospatial architecture

This is a genuine strength and a clear differentiator vs. the generic Google repo.

- `listings.location GEOMETRY(Point,4326)` + GIST index.
- Hazard layers: `flood_zones` (PAGASA, `MultiPolygon`, `return_period_years`), `fault_lines` (PHIVOLCS, `LineString`, `active_category`) — both GIST-indexed.
- POIs: `schools` (DepEd), `hospitals` (DOH levels) as points.
- `FlagRedFlags::checkFloodAndDisaster()` already does `ST_Within(point, flood_zones.geom)` filtering by `return_period_years <= 25` → **critical flood flag**. The query logic is correct; it just needs the tables *populated* by an ingestion job, and B1's `'live'` bug fixed in the sibling pricing query.

**Reusability:** **Very High.** The PostGIS schema is the right foundation for commute/flood/POI scoring. The Google repo's contribution here is the *Maps rendering* (frontend), not the spatial analytics.

## 1.7 API architecture

`routes/api.php` is clean and versioned (`/api/v1`), with sensible guards and an explicit rate-limit policy in comments (anon 60, auth 300, AI 20/min — though only `throttle:20,1` on `/advisor` and `throttle:60,1` on `/financial` are wired; the global tiers aren't). Resource grouping is idiomatic Laravel. Broker mutations gated by `role:broker|admin`; admin by `role:admin`.

**Limitations:** no response envelope standard (mix of `['data'=>…]` and bare paginators); no OpenAPI/Scribe spec; no idempotency keys on write endpoints; financial endpoints accept raw input without the Zod contracts being enforced server-side.
**Reusability:** **High** — keep the route map; harden validation + responses.

## 1.8 Authentication architecture

Sanctum SPA cookie auth (stateful domains), OTP login, Google + Apple OAuth, role middleware. This is the correct pattern for a Next.js↔Laravel SPA and is **more complete than the Google repo's**, which assumes Firebase/Google identity.

**Limitations:** Veriff KYC (identity verification for brokers/high-value actions) is README-only — `BrokerController::verifyKyc` exists as a route but the Veriff integration is not present. OTP transport (SMS provider) not wired. No 2FA on admin.
**Reusability:** **High** for buyer auth; **build** the KYC layer.

## 1.9 Infrastructure architecture

`docker-compose.yml`: pgvector/pg16, redis 7, opensearch 2.13 (single-node, security disabled), mailpit, optional OpenSearch Dashboards. Horizon config present (`config/horizon.php`).

**Strengths:** the local stack matches the target stack — good dev/prod parity intent.
**Limitations:** **B3** (TimescaleDB hypertables can't run on this image — either switch the image to `timescale/timescaledb-ha:pg16` *plus* add PostGIS/pgvector, or drop hypertables for plain partitioned tables); OpenSearch with `DISABLE_SECURITY_PLUGIN` is dev-only and must never reach prod; no S3/R2, no CDN, no reverse proxy, no queue/worker container, no CI in tree.
**Reusability:** **Medium** — good dev baseline, not a production topology (see §8).

---

# 2. Gap Analysis

| Existing Capability (in repo) | HanapBahay Requirement | Gap Level | Recommended Solution |
|---|---|---|---|
| Raw-SQL `SearchListings` with `'live'` status bug + missing `hoa` column | Reliable, ranked, geo+semantic property search | **Critical** | Fix B1/B2; route search through OpenSearch (BM25+filters+geo) with pgvector re-rank |
| `ScoreProperty` explicit placeholder (most dimensions `null`) | Multi-factor affordability-first scoring | **Critical** | Build `PropertyScorer` (income, commute, flood, education, healthcare, developer, sustainability) with profile weights |
| Migrations include TimescaleDB but image lacks it (B3) | Migrations run cleanly on provisioned infra | **Critical** | Swap image or replace hypertables with native Postgres partitioning |
| Tool turns not persisted; context re-injected (B4) | Coherent multi-turn advisor memory | **High** | Persist full content blocks (incl. tool_use/tool_result) as JSON; rebuild messages faithfully |
| `PagIBIG`, `DTIEngine`, `Amortization`, `HiddenCosts`, `BankFinancing` | Philippine Financial Engine | **Low** | Reuse; add Emergency-Fund & Sustainability-Score engines; unify gross-vs-take-home basis |
| `flood_zones`/`fault_lines`/`schools`/`hospitals` schema (empty) | Risk + livability intelligence | **High** | Build ingestion jobs (PAGASA/PHIVOLCS/DepEd/DOH) to populate; wire scorers |
| `bir-zonal.json`, `pagibig-rules.json`, `property-tax.json`, `psgc.json` | BIR/PSGC-aware analysis | **Medium** | Move from JSON blobs to seeded, indexed tables; add zonal-vs-asking overpricing scorer |
| `listing_embeddings` + ivfflat (unused) | Semantic search & similarity | **High** | Embedding-generation queue job (Voyage/Claude embeddings); similar-listings + semantic recall |
| OpenSearch provisioned, not queried | Market stats + fast facets | **High** | Index listings + market_stats; back `fetch_market_stats` and barangay analytics with OS aggregations |
| Sanctum + OAuth + OTP routes | Buyer auth | **Low** | Keep; wire OTP SMS provider; add admin 2FA |
| `verifyKyc` route only | Veriff identity verification | **High** | Integrate Veriff; gate broker listings + high-value actions |
| README mentions PayMongo | Consultations/premium payments | **High** | Integrate PayMongo (webhooks, idempotency) when monetization lands |
| Zod schemas (TS only) | One contract across TS+PHP | **Medium** | Generate JSON Schema from Zod; validate Laravel requests against it (spectator/opis-json-schema) |
| `FlagRedFlags` (pricing/dev/broker/flood/photo) | Risk Intelligence Engine | **Medium** | Solid skeleton; populate hazard tables, add title-issue & HOA & overpricing-vs-BIR scorers |
| SSE inside request worker | 10k–1M user scale | **High** | Move AI turns to queued jobs + streamed relay; or dedicated async runtime (see §8) |
| `market_stats` hypertable (won't migrate) | Barangay market analytics | **High** | Fix infra; build aggregation pipeline; serve via OpenSearch/materialized views |
| No tests in tree | Fiduciary correctness guarantees | **High** | Pest unit tests for every Financial engine (golden cases); contract tests for tools |

---

# 3. Proposed HanapBahay AI Architecture

Target stack is already chosen and largely provisioned. The work is to **connect and harden**, not re-pick.

## 3.1 Service boundaries

```
┌────────────────────────────────────────────────────────────────────────┐
│  EDGE: Cloudflare (CDN + WAF) → Nginx/ALB                               │
└───────────────┬───────────────────────────────┬────────────────────────┘
                │                                 │
        ┌───────▼────────┐               ┌────────▼─────────┐
        │  web (Next 15) │  REST + SSE   │  api (Laravel 11)│
        │  React 19 / TS │◀─────────────▶│  Sanctum / v1    │
        └───────┬────────┘               └───┬───────┬──────┘
                │ (Maps SDK render)           │       │
                │                    ┌────────▼──┐  ┌─▼───────────────┐
                │                    │ Sync REST │  │ Async (Horizon) │
                │                    │ + Financial│ │  queue: Redis    │
                │                    │  (pure PHP)│  └─┬───────┬───────┘
                │                    └────┬───────┘    │       │
                │                         │            │       │
   ┌────────────▼───────┐   ┌─────────────▼──┐  ┌──────▼──┐ ┌──▼────────────┐
   │ Anthropic Claude   │   │ PostgreSQL 16  │  │OpenSearch│ │ Ingestion jobs│
   │ (tool-use, stream) │   │ PostGIS+pgvec  │  │  (search/ │ │ PAGASA/PHIVOLCS│
   └────────────────────┘   │ +partitioning  │  │  market) │ │ DepEd/DOH/BIR  │
                            └───────┬─────────┘  └──────────┘ │ embeddings     │
                                    │                          └───────────────┘
                       ┌────────────▼─────────┐
                       │ S3 / Cloudflare R2   │  (listing photos, exports)
                       └──────────────────────┘
   External: Veriff (KYC) · PayMongo (payments) · SMS/email (OTP/notify)
```

**Boundary rules:**
- **Synchronous path** (controllers): auth, profile, listing reads, financial simulations (pure CPU, sub-50ms) — never call Claude or external APIs inline.
- **AI path**: the advisor turn becomes a **queued job** that streams back over SSE via a relay (Redis pub/sub or DB-polled buffer), so request workers aren't pinned (fixes the §1.4 scaling cliff while keeping the existing `ClaudeOrchestrator` logic).
- **Ingestion path**: all third-party/heavy work (hazard tiles, embeddings, market-stat rollups, photo pHash) runs on Horizon queues, idempotent, retryable.

## 3.2 AI orchestration flow (target)

```
user message ──▶ AdvisorController.sendMessage
   │  persist user turn
   │  dispatch AdvisorTurnJob (queue: ai) ──────────────┐
   │  open SSE; subscribe to relay channel             │
   └──────────────────────────────────────────────◀────┘ stream deltas
                                                         │
   AdvisorTurnJob:
     system = [ {static prompt + tool docs, cache_control:ephemeral},   ← cached
                {user profile block (NO cache_control)} ]                ← volatile
     messages = faithful replay of stored content blocks (incl tool_use/result)  ← fixes B4
     loop ≤ N:
        Claude stream → relay text deltas
        on tool_use → ToolRegistry.dispatch (typed, validated) → relay 'tool_call'
        append assistant blocks + tool_result; persist BOTH as JSON
     persist final; close relay
```

Key changes vs. current: (1) caching only the stable prefix (B5); (2) faithful multi-turn replay (B4); (3) worker decoupling; (4) tool inputs validated against the same JSON Schema in `prompts/tools/*.json` before dispatch.

---

# 4. Philippine-Specific Domain Design

## 4.1 Financial Engine

| Module | Status | Design |
|---|---|---|
| **DTI Engine** | ✅ exists | Gross-based gating (0.30/0.35/0.40). Add explicit `basis` field; include co-borrower; expose both gross and take-home views. |
| **Pag-IBIG Calculator** | ✅ strong | HDMF 461-B compliant. Add: subsidized rate for socialized housing, MRI/fire-insurance add-ons to true monthly, refinancing path. |
| **Bank Financing Calculator** | ✅ exists (`BankFinancing`) | Extend with per-bank rate sheets (BPI/BDO/Metrobank/Security Bank) as seeded data; teaser-vs-repricing schedules; compare side-by-side with Pag-IBIG. |
| **Hidden Cost Calculator** | ✅ exists (`HiddenCosts`) | DST (1.5%), transfer tax (≤0.75% LGU), registration (LRA schedule), notarial, HOA move-in, MRI, fire insurance, broker comm. Output as one-time vs. recurring. |
| **Affordability Engine** | ⚠️ partial (in prompt math) | Promote out of `PromptBuilder` into `Modules/Financial/Affordability.php`: max sustainable price given income, DP, obligations, target DTI, *and* hidden+recurring costs. Single source of truth used by tools + UI gauge. |
| **Emergency Fund Analyzer** | ❌ build | Given monthly amortization + recurring costs, compute months-of-runway from `available_down_payment - DP_used` and `monthly_savings`. Flag if buying drains buffer below 3–6 months. |
| **Sustainability Score Engine** | ❌ build | 15–25 yr horizon: stress-test amortization at +300–500bps repricing, income-shock (−20%), and recurring-cost inflation. Output a 0–100 "can you keep this for 20 years?" score. This *is* the product's core verb. |

**Implementation note:** keep every engine **pure static PHP** (as the existing ones are) so they're trivially unit-testable with golden cases — non-negotiable for a fiduciary tool.

## 4.2 Property Intelligence Engine

| Module | Design |
|---|---|
| **BIR Zonal Valuation Analysis** | Move `bir-zonal.json` → indexed `bir_zonal_values(psgc_code, classification, php_per_sqm, effective_date)`. Compare asking ₱/sqm vs. zonal → over/under-assessed ratio (zonal is a *floor*, not market; frame as tax-basis + sanity check, not appraisal). |
| **Price-to-Income Ratio** | listing price ÷ annual household income; benchmark vs. barangay/city norms; surface "X years of income." |
| **Barangay Market Statistics** | Populate `market_stats` (fix B3 infra); serve median ₱/sqm, txn count, days-on-market via OpenSearch aggregations + materialized views. |
| **Price Appreciation Analysis** | `listing_history` time series → trailing CAGR per area/type; caution on speculative spikes. |
| **Inventory Analysis** | active-listing counts + absorption rate per area/type → buyer-vs-seller market signal. |

## 4.3 Risk Intelligence Engine

`FlagRedFlags` is a solid skeleton (pricing, developer complaints, broker PRC/DHSUD, completeness, flood, photo-pHash). Build out:

| Module | Design |
|---|---|
| **Flood Risk Detection** | ✅ logic exists (`ST_Within` vs `flood_zones`, ≤25yr → critical). Needs PAGASA tiles ingested. Add storm-surge + 100-yr tiers with graded severity. |
| **Developer Reputation Scoring** | `developers.reputation_score`/`complaints_count` referenced. Build the scorer: HLURB/DHSUD complaint records, RFO delay history, turnover-defect reports → 0–100. |
| **HOA Risk Analysis** | **Add `hoa_php_monthly` column to `listings`** (currently selected but missing — B2). Flag HOA > X% of amortization; flag undisclosed HOA. |
| **Overpricing Detection** | Two signals: (a) vs. barangay median ₱/sqm (exists, fix B1), (b) vs. BIR zonal floor. Graded warning→critical. |
| **Title Issue Detection Framework** | Cannot read Torrens titles automatically; build a *structured checklist + document-intake* flow (TCT/CCT number capture, encumbrance/annotation prompts, "verify at Registry of Deeds" actions) and a confidence-graded risk note. Be explicit it is advisory, not a title search. |

---

# 5. AI Advisor Design

The orchestrator (`ClaudeOrchestrator`) and registry (`ToolRegistry`) are already the right architecture. Below is the tool contract specification to harden.

**Orchestration principle (enforce in system prompt + code):** Claude must **never emit a number it did not get from a tool** (price, ₱/sqm, distance, flood status, DTI, amortization). The system prompt already states this; enforce it by making numeric claims traceable to a prior `tool_result` in the same turn.

| Tool | Inputs | Outputs | Validation | Security | Failure handling |
|---|---|---|---|---|---|
| **search_listings** | property_type, city/barangay (PSGC), price/bed/area ranges, lat/lng/radius_km, q | `{count, listings[]}` (no PII) | PSGC enum; price>0; radius≤50km; cap `per_page`≤20 | Read-only; parameterized geo SQL (already `?`-bound); no user-supplied raw SQL | On OS/DB error, degrade to Postgres; return `{count:0, note}` not exception |
| **score_property** | listing_id, (implicit user profile) | per-dimension scores + rationale + warnings | UUID; listing exists & not archived | Scope to listing visibility; never leak broker PII | Currently placeholder — must return `partial:true` honestly, never fabricate dimensions |
| **simulate_loan** | property_value, income, age, pagibig_member, contrib_months, requested_loan/term, bank? | Pag-IBIG + bank schedules, eligibility | numeric ranges; age 18–75; clamp loan≤cap | Pure compute; no external calls | Return structured `ineligible(reason)` (already does) |
| **compare_properties** | listing_ids[2..4], user context | aligned matrix incl. true monthly, sustainability | 2–4 valid UUIDs | Read-only; visibility scoped | Skip missing IDs with a note; never partial-fail silently |
| **fetch_market_stats** | psgc/city, property_type, window | median ₱/sqm, appreciation, inventory, days-on-market | known area code; bounded window | Read-only aggregates only | If sparse data → `{insufficient_data:true}` with sample size |
| **flag_red_flags** | listing_id, include_photo_check | flags[] + summary + overall_risk | UUID; listing exists | Read-only; pHash compare bounded | Each sub-check try/catch (already), so one failure ≠ total failure |

**How Claude should orchestrate (canonical "is this affordable for me?" flow):**
`search_listings` (or take given listing) → `simulate_loan` (Pag-IBIG + bank) → `score_property` (fit vs. profile) → `flag_red_flags` (risk) → `fetch_market_stats` (is the price fair?) → synthesize: *verdict + why it fits + what you give up + biggest risks + sustainability over 20 years*. The orchestrator's ≤5-iteration loop already supports this fan-out.

**Add to the loop:** an `apply_affordability_guardrail` step — if `simulate_loan` monthly > 40% DTI, the advisor must **refuse/strong-warn with override**, matching the README policy. Encode as a post-tool check, not just prompt instruction.

---

# 6. Database Design

## 6.1 Core entities & relationships (as-built + proposed additions)

```
users ──1:1── user_profiles            (archetype, modifiers, risk_tolerance, family_size)
  │    ──1:1── user_finances           (gross/co-borrower income, obligations, DP, savings, pagibig)
  │    ──1:N── user_locations          (home/work pins for commute scoring)
  │    ──1:1── user_preferences        (property_types, preferred_lgus, must_haves, deal_breakers)
  │    ──1:N── consultations ──1:N── consultation_messages   (role, content-blocks JSON*, tokens)
  │                         └──1:N── recommendations ──N:1── listings
  │    ──1:N── shortlists ──N:N── listings (via shortlist_listings)
  │
developers ──1:N── listings          (reputation_score, complaints_count)
brokers    ──1:N── listings          (prc_id, dhsud_id, status, veriff_*)
listings   ──1:1── listing_embeddings (vector(1024), ivfflat)
           ──1:N── listing_history*   (price/status time series)   [partition by month]
           ──1:N── listings_photos*   (url, phash)                 [add for B2/photo-reuse]
           +add:   hoa_php_monthly, bir_zonal_psm (cached)

Geo/reference (no FK, spatial joins):
  flood_zones(geom MultiPolygon, return_period_years)   GIST
  fault_lines(geom LineString, active_category)         GIST
  schools(point), hospitals(point)                      GIST
  market_stats(city_muni_code, property_type, period)   [partition by month]
  bir_zonal_values*, psgc*, property_tax_schedules*     (seed from sdk-ph JSON)
```
`*` = proposed/needs change. `content-blocks JSON` = B4 fix (store full Anthropic content array, not just text).

## 6.2 Indexing strategy

- **listings:** keep B-tree on `source,status,property_type,price_php`; **add composite** `(status, property_type, price_php)` for the common filtered-search; GIST on `location` (exists). For ₱/sqm-median window functions, a partial index `WHERE status='active' AND deleted_at IS NULL`.
- **JSONB address:** add `GIN (address jsonb_path_ops)` and/or extract `city_psgc` to a generated column with a B-tree (the current `whereJsonContains`/`ilike address->>'city'` won't use an index well).
- **pgvector:** `ivfflat (lists=100)` exists; revisit `lists ≈ sqrt(rows)` and consider **HNSW** at scale for recall/latency.
- **Spatial:** all hazard/POI GIST indexes present — good. Ensure `location` queries cast consistently (`::geography` for meters) to keep using the index.
- **Time series:** if keeping TimescaleDB, fix the image (B3); otherwise native `PARTITION BY RANGE (recorded_at/period_start)` monthly + BRIN on the time column.

## 6.3 PostgreSQL/PostGIS optimization recommendations

- Move BIR zonal / PSGC / property-tax from JSON files into **seeded tables** so they're joinable and indexable (overpricing and tax math become SQL, not app-side lookups).
- Precompute and cache per-listing `bir_zonal_psm` and barangay median at ingestion to avoid live percentile scans in `FlagRedFlags`.
- Use **materialized views** for barangay market stats, refreshed by a Horizon job; serve hot reads from OpenSearch.
- Connection pooling (PgBouncer) before you have many FPM workers; the SSE/AI path holds connections longer than usual.

---

# 7. Migration Strategy (phased)

Because this is fix-forward, "migration" = maturation of the existing scaffold.

| Phase | Scope | Effort | Top technical risks | Dependencies | Team |
|---|---|---|---|---|---|
| **0 — Stabilize** | Fix B1–B5; add Pest tests for Financial engines; make `migrate` + `dev` green; seed demo data | **0.5–1 wk** | Hidden coupling on `'live'`; infra image swap | none | 1 senior Laravel |
| **1 — Core search** | OpenSearch-backed `search_listings` (BM25+filters+geo); listing CRUD/admin approval; map UI (Google repo pattern); ingestion skeleton | **2–3 wk** | OS index mapping & geo; Scout sync; relevance tuning | Phase 0 | 1 BE, 1 FE, ½ GIS |
| **2 — PH financial engine** | Affordability + Emergency-Fund + Sustainability engines; unify gross/take-home; wire `/financial/*` + UI gauges; BIR zonal + hidden costs as tables | **2 wk** | Correctness/golden-case coverage; basis consistency | Phase 0 | 1 BE (domain), 1 FE |
| **3 — AI advisor** | Decouple AI to queued job + SSE relay; fix caching; faithful multi-turn; guardrail step; embeddings job + semantic recall | **2–3 wk** | Streaming relay reliability; token cost; tool-validation | Phases 1–2 | 1 senior BE (AI), 1 FE |
| **4 — Risk intelligence** | Populate PAGASA/PHIVOLCS/DepEd/DOH; flood/fault/HOA/overpricing/developer scorers; title-checklist flow; Veriff KYC | **3–4 wk** | Data licensing & freshness; geocoding accuracy | Phases 1–3 | 1 BE, 1 GIS, 1 data eng |
| **5 — Production scaling** | Async topology, PgBouncer, OS cluster, R2/S3+CDN, CI/CD, observability, WAF, PayMongo, security review | **3–4 wk** | SSE at scale; AI cost controls; PII/security | all | 1 platform/SRE, 1 BE, security |

**Critical path:** Phase 0 gates everything (the scaffold doesn't run end-to-end without it). Phases 1 & 2 parallelize. Phase 3 needs both. **MVP = Phases 0–3** (~7–10 weeks with a 3–4 person team).

---

# 8. Scalability Review

Assumptions: PH market, advisor turn ≈ 3–6 tool calls, ~8–20k tokens in / ~1–2k out per turn (with caching), Opus for synthesis / Haiku for routing.

| Dimension | 10k users | 100k users | 1M users |
|---|---|---|---|
| **DB (Postgres)** | Single primary (4 vCPU/16GB), PostGIS+pgvec fine | Primary + 1–2 read replicas; PgBouncer; partition `listing_history`/`market_stats`; HNSW for vectors | Sharded reads, dedicated analytics replica; move market analytics fully to OpenSearch; consider Citus for geo-heavy reads |
| **OpenSearch** | Single node (current) → 1 small managed node | 3-node cluster, 1 replica/shard; separate hot/warm | 3+ data + dedicated master/coordinator nodes; index lifecycle mgmt |
| **AI cost** (dominant variable) | a few hundred turns/day → low $ | Caching + Haiku-routing essential; cap turns; cost ≈ thousands $/mo | Tiered models, aggressive prompt-cache (stable prefix only — B5 fix is a *cost* control), per-user quotas, batch where async; cost governance is a product feature |
| **Queue (Redis/Horizon)** | 1 worker pool | Separate `ai`, `ingestion`, `default` pools; autoscale `ai` | Redis Cluster; backpressure + rate-limit on `ai`; dead-letter; possibly a dedicated streaming runtime (Node/Go) for SSE fan-out to spare PHP workers |
| **SSE/web** | FPM fine | **Decouple AI from FPM (Phase 3)** or workers starve | Dedicated SSE relay tier; sticky-less via Redis pub/sub; HTTP/2 |
| **Infra cost** (rough) | low hundreds $/mo | low-mid thousands $/mo (AI-dominated) | tens of thousands $/mo; AI + OS the big lines |

**Architectural recommendations:**
1. **The SSE-in-request-worker pattern (current §1.4) is the #1 scaling blocker** — decouple in Phase 3 before user growth.
2. **Prompt caching is a cost lever, not just latency** — B5 fix (cache only the stable prefix + tools) materially lowers per-turn cost at scale.
3. **Push analytics to OpenSearch/materialized views**, keep Postgres for transactional + spatial + vector.
4. **Model tiering**: Haiku for tool-routing/extraction, Opus only for final fiduciary synthesis.

---

# 9. Final Recommendation

### 1. Reuse unchanged
- `Modules/Financial/PagIBIG.php`, `DTIEngine.php`, `Amortization.php`, `HiddenCosts.php`, `BankFinancing.php` (pure, correct, testable — the moat).
- `ClaudeOrchestrator` SSE + tool-iteration **mechanics** (excellent streaming impl).
- PostGIS/pgvector schema and hazard/POI tables.
- `routes/api.php` map, Sanctum/OAuth auth, Zustand frontend stores, buyer-protection component vocabulary.
- `prompts/tools/*.json` + `system_prompt.md` versioning approach.

### 2. Modify
- `PromptBuilder` caching (B5) and gross/take-home basis unification.
- `ClaudeOrchestrator` persistence (B4 — store full content blocks; stop re-injecting listing context as a fresh user turn each iteration).
- `SearchListings` (B1/B2; route through OpenSearch + pgvector).
- `FlagRedFlags` (B1 in pricing query; populate hazard tables; add HOA column).
- `listings` schema (add `hoa_php_monthly`, cached `bir_zonal_psm`, generated `city_psgc`).
- Infra (B3 image fix; production OS security; async worker topology).

### 3. Replace entirely
- The raw-SQL keyword search → OpenSearch BM25 + geo + pgvector re-rank.
- `sdk-ph/*.json` runtime lookups → seeded, indexed reference tables.
- Dev OpenSearch (security-disabled) → secured managed cluster for prod.

### 4. Build from scratch
- `PropertyScorer` (real multi-factor scoring; `ScoreProperty` is a placeholder).
- Affordability / Emergency-Fund / Sustainability-Score engines.
- Ingestion pipeline (PAGASA, PHIVOLCS, DepEd, DOH, BIR, market-stat rollups, embeddings, photo pHash).
- Developer-reputation, overpricing-vs-BIR, HOA, and title-checklist risk scorers.
- Veriff KYC + PayMongo + S3/R2 + CI/CD + observability + security review.
- A test suite (Pest golden cases for every financial engine — fiduciary-critical).

### 5. Fastest path to MVP
**Fix-forward, do not rewrite.** Phase 0 (stabilize, ~1 wk) → Phase 1 (OpenSearch search + map, ~2–3 wk) → Phase 2 (financial engines + UI, ~2 wk) → Phase 3 (decoupled AI advisor, ~2–3 wk). **A credible, demoable MVP in ~7–10 weeks** with 3–4 engineers, because the structural skeleton and the hardest domain logic (Pag-IBIG, DTI) already exist and are correct.

### 6. Ideal production architecture (venture-scale PH PropTech)
- **Edge:** Cloudflare (CDN+WAF+R2) → ALB/Nginx.
- **Web:** Next.js 15 on Vercel/containers; Maps SDK for rendering; React Query for caching.
- **API:** Laravel 11 stateless behind autoscaling; **AI turns on Horizon queues with a Redis-pub/sub SSE relay tier** (never in FPM).
- **Data:** Postgres 16 primary + read replicas + PgBouncer; PostGIS for spatial; pgvector (HNSW) for similarity; partitioned time series; OpenSearch cluster for search + market analytics; materialized views for barangay stats.
- **AI:** Claude tiered (Haiku route / Opus synthesize), stable-prefix prompt caching, per-user cost quotas, full tool-input validation, fiduciary guardrail as code.
- **Trust:** Veriff KYC, PRC/DHSUD broker verification gates, PayMongo with webhook idempotency, audit logging on every recommendation (explainability is a compliance asset for a fiduciary product).
- **Ops:** CI/CD, OpenTelemetry tracing across the AI loop, error budgets, and a standing **financial-correctness test gate** — for a product that tells Filipinos what they can afford for 20 years, a wrong amortization is a safety incident, not a bug.

---

*Generated from a direct read of the repository at `main`. Blocking defects B1–B5 are cited with file:line and should be triaged before feature work.*
