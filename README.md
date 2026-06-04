# HanapBahay AI

> **The fiduciary AI co-buyer for Filipino homebuyers.**
>
> HanapBahay AI helps you find a property you can genuinely afford and sustain for 15–25 years — not just one you qualify for on paper.

---

## What is HanapBahay AI?

Most real estate portals optimize for broker commissions and listing clicks. HanapBahay AI does the opposite: it acts as your personal advisor whose only obligation is to you, the buyer.

Built specifically for the Philippine market, it combines PSA/PSGC-aware property search, Philippine-specific financial math (Pag-IBIG, BIR zonal valuation, bank amortization, DTI limits, hidden closing costs), and a Claude-powered conversational advisor that explains every recommendation in plain English — or Tagalog.

**Target buyers:** First-time homebuyers, OFWs, and anyone navigating the Philippine real estate market without a trusted advisor.

---

## Key Features

### Affordability-First Scoring
- Refuses or warns on any property where projected monthly amortization exceeds **35% of the buyer's verified gross monthly income**
- DTI thresholds: advisory at 30–35%, strong warning at 35–40%, block with override above 40%

### Philippine Financial Engine
- **Pag-IBIG** eligibility checks and amortization schedules
- **Bank financing** side-by-side comparisons
- **BIR zonal valuation** vs. asking price analysis
- **Hidden costs** calculator: Documentary Stamp Tax, transfer tax, registration fees, HOA dues, insurance, and agent commission
- **DTI engine** scoped to Philippine income structures

### Multi-Factor Property Scoring
Scores listings against the buyer's profile across: income, preferred location, commute, family size, flood-zone exposure, developer track record, and long-term sustainability.

### Red-Flag Detection
Automatically surfaces: flood-zone risk, overpricing vs. BIR zonal value, title issues, developer track record, and excessive HOA dues.

### Market Context
Barangay-level median ₱/sqm, price appreciation trend, and inventory depth — powered by OpenSearch.

### AI Advisor (Claude-Powered)
- Conversational deep dives: score → loan simulation → red flags → market stats in one flow
- Explainable: every verdict states *why it fits*, *what the buyer gives up*, and *the biggest risks*
- Emotional guardrails: detects impulsive decision signals and prompts structured 24-hour reflection
- Tagalog explanations on request

---

## Architecture

```
hanapbahay-ai/
├── web/                        # Next.js 15 (App Router) — buyer-facing SPA
│   ├── app/(app)/
│   │   ├── advisor/            # AI chat interface
│   │   ├── dashboard/          # Buyer overview
│   │   ├── financial/          # DTI gauge, loan calculator
│   │   ├── listings/           # Property search & map
│   │   ├── profile/            # Buyer profile & preferences
│   │   └── shortlists/         # Saved & compared properties
│   └── app/(auth)/             # Login / Register
│
├── api/                        # Laravel 11 — REST API + AI orchestration
│   ├── app/
│   │   ├── Modules/
│   │   │   ├── AI/             # Claude orchestration
│   │   │   │   ├── ClaudeOrchestrator.php
│   │   │   │   ├── PromptBuilder.php
│   │   │   │   ├── ToolRegistry.php
│   │   │   │   └── Tools/      # Tool handlers (6 tools)
│   │   │   └── Financial/      # Pure-PHP financial math
│   │   │       ├── Amortization.php
│   │   │       ├── BankFinancing.php
│   │   │       ├── DTIEngine.php
│   │   │       ├── HiddenCosts.php
│   │   │       └── PagIBIG.php
│   │   └── Models/             # User, Listing, Broker, Developer,
│   │                           # Consultation, Shortlist, UserFinance…
│   └── database/migrations/    # 12 migrations (users → geo data)
│
├── packages/
│   ├── schemas/                # @hanapbahay/schemas — shared Zod contracts
│   │   └── src/
│   │       ├── user.ts
│   │       ├── listing.ts
│   │       ├── financial.ts
│   │       ├── scoring.ts
│   │       ├── consultation.ts
│   │       └── common.ts
│   └── financial/              # Shared financial primitives (TS)
│
├── sdk-ph/                     # Philippine data SDKs
│   ├── bir-zonal.json          # BIR zonal values
│   ├── pagibig-rules.json      # Pag-IBIG contribution & loan rules
│   ├── property-tax.json       # Real property tax schedules
│   └── psgc.json               # Philippine Standard Geographic Code
│
├── prompts/
│   ├── system_prompt.md        # Versioned AI advisor system prompt
│   └── tools/                  # Tool schemas (JSON)
│       ├── search_listings.json
│       ├── score_property.json
│       ├── simulate_loan.json
│       ├── compare_properties.json
│       ├── fetch_market_stats.json
│       └── flag_red_flags.json
│
└── infra/
    └── docker/postgres/        # PostGIS + pgvector init SQL
```

### Tech Stack

| Layer | Technology |
|---|---|
| Frontend | Next.js 15, React 19, Tailwind CSS, TypeScript |
| Backend | Laravel 11, PHP 8.3, Sanctum (SPA cookie auth), Horizon (queues) |
| AI | Anthropic Claude (Opus / Sonnet / Haiku), tool-use API |
| Database | PostgreSQL 16 + PostGIS + pgvector |
| Search | OpenSearch 2.13 |
| Cache / Queue | Redis 7 |
| Monorepo | pnpm workspaces + Turborepo |
| Auth providers | Google OAuth, Apple Sign-In |
| Storage | AWS S3 / Cloudflare R2 |
| Payments | PayMongo |
| KYC | Veriff |
| Mail (dev) | Mailpit |

---

## AI Tools

The advisor uses Claude's tool-use API with six domain-specific tools:

| Tool | Purpose |
|---|---|
| `search_listings` | Find properties by type, price range, location, bedrooms |
| `score_property` | Multi-factor fit score against the buyer's profile |
| `simulate_loan` | Pag-IBIG + bank amortization with full schedule |
| `compare_properties` | Side-by-side comparison of 2–4 listings |
| `fetch_market_stats` | Median ₱/sqm, appreciation trend, inventory for a barangay/city |
| `flag_red_flags` | Red-flag detection pipeline for a specific listing |

The AI **never** invents prices, distances, flood-zone status, or DTI ratios — it always calls a tool for numeric data.

---

## Prerequisites

- **Node.js** ≥ 22.0.0
- **pnpm** ≥ 9.0.0
- **PHP** ≥ 8.3 with extensions: `pdo_pgsql`, `redis`, `pcntl`
- **Composer** ≥ 2.x
- **Docker** + Docker Compose (for local services)

---

## Getting Started

### 1. Clone and install dependencies

```bash
git clone https://github.com/your-org/hanapbahay-ai.git
cd hanapbahay-ai

# Install JS dependencies (web + packages)
pnpm install

# Install PHP dependencies
cd api && composer install && cd ..
```

### 2. Start local services

```bash
docker compose up -d
# Starts: PostgreSQL 16 + PostGIS, Redis 7, OpenSearch 2.13, Mailpit
```

### 3. Configure the API

```bash
cp .env.example api/.env
cd api

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# (Optional) Seed demo data
php artisan db:seed
```

Set at minimum in `api/.env`:

```env
ANTHROPIC_API_KEY=sk-ant-...
DB_PASSWORD=secret          # matches docker-compose.yml
```

### 4. Configure the web app

```bash
cp web/.env.local.example web/.env.local
```

Key variables:

```env
NEXT_PUBLIC_API_URL=http://localhost:8000
```

### 5. Run the full stack

```bash
# Start everything in parallel (web + API watcher)
pnpm dev

# Or individually:
pnpm --filter @hanapbahay/web dev      # Next.js on :3000
cd api && php artisan serve            # Laravel on :8000
cd api && php artisan horizon          # Queue worker
```

### Optional: OpenSearch Dashboards

```bash
docker compose --profile tools up -d
# Available at http://localhost:5601
```

---

## Development Commands

```bash
# Full build (all packages)
pnpm build

# Lint all workspaces
pnpm lint

# Type-check all workspaces
pnpm type-check

# Format (Prettier + Tailwind)
pnpm format

# Run migrations
pnpm api:migrate

# Seed the database
pnpm api:seed

# Start Horizon (queue dashboard)
pnpm api:horizon
```

---

## Environment Variables Reference

See [.env.example](.env.example) for the complete list. Key variables:

| Variable | Description |
|---|---|
| `ANTHROPIC_API_KEY` | Required. Claude API key |
| `ANTHROPIC_DEFAULT_MODEL` | Default: `claude-opus-4-7-20251101` |
| `DB_PASSWORD` | PostgreSQL password |
| `SANCTUM_STATEFUL_DOMAINS` | Default: `localhost:3000` |
| `SCOUT_DRIVER` | Default: `opensearch` |
| `OPENSEARCH_HOST` | Default: `http://localhost:9200` |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Google OAuth |
| `PAYMONGO_SECRET_KEY` | PayMongo payments |
| `VERIFF_API_KEY` | KYC verification |

---

## Data Sources (sdk-ph)

The `sdk-ph/` directory bundles Philippine reference data used by the financial engine and AI tools:

| File | Source | Usage |
|---|---|---|
| `bir-zonal.json` | Bureau of Internal Revenue | Overpricing detection vs. assessed value |
| `pagibig-rules.json` | Pag-IBIG Fund circulars | Loan eligibility, contribution schedules |
| `property-tax.json` | Local Government Code | Annual RPT estimates |
| `psgc.json` | Philippine Statistics Authority | Location normalization and barangay lookup |

---

## Advisory Disclaimer

HanapBahay AI is an advisory tool, not a licensed financial advisor, real estate broker, or attorney. All financial projections are estimates for planning purposes and are not a substitute for professional financial or legal advice.
