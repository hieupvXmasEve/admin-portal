# CONTEXT.md — Swinx Project Onboarding

> Single-page orientation for humans and AI agents new to this repo.
> For depth, jump to `docs/codebase-discovery-2026-05-12.md`. For all agent operating rules, see `AGENTS.md`.

**Last updated:** 2026-05-31
**Owner:** Platform Team
**Status:** Active baseline

---

## 1. Project Overview & Business Goal

**Swinx** is a **university operations platform** for Asia University. Single Laravel 13 + Vue 3 monolith that serves staff, students, lecturers, and parents from one codebase.

**Surfaces:**
- Admin/staff web app (Inertia SPA) — admissions, academic records, scheduling, finance ops, scholarships, events, notifications.
- Student API `/api/v1/student/*`.
- Lecturer API `/api/v1/lecturer/*`.
- Parent proxy access to selected student endpoints.

**Business goal:** Replace fragmented academic + finance + scheduling tooling with one operationally-owned platform. Optimised for fast in-house iteration over multi-service complexity.

**Active domains:** Identity · Academic · Finance (incl. DNG payment gateway) · Notification (V2 outbox).

---

## 2. Architecture Summary

**Style:** Hybrid modular monolith — actively migrating from a service-led layout to a module-led layout.

```
Browser SPA (Vue 3 + Inertia v3)
   │
   ▼
Routes  ──►  Middleware (Sanctum + Actor)  ──►  Controllers (thin)
                                                   │
                  ┌────────────────────────────────┼────────────────────────┐
                  ▼                                ▼                        ▼
        Module Actions                     Module Queries              Shared Services
        (app/Modules/*/Actions)            (app/Modules/*/Queries)     (app/Services/*)
                  │                                │                        │
                  └────────────────►  Eloquent Models  ◄────────────────────┘
                                              │
                                              ▼
                                MySQL/MariaDB  +  Redis (cache/queue)
```

**Four active modules** under `app/Modules/{Identity, Academic, Finance, Notification}`:

| Module | Responsibility |
|---|---|
| **Identity** | Sanctum auth (8-hour TTL, rotate-then-revoke refresh), social auth, campus selection, actor segmentation (student / parent / lecturer). |
| **Academic** | Course offerings, retake registration, placement, academic progression events (`ENGLISH_LEVEL_CHANGED`, `COURSE_STAGE_CHANGED`), student action logs + decisions registry, Excel import/export. |
| **Finance** | Settlement v2 (`payment_applications`, `invoice_lines`, `invoice_discounts`, `discount_allocations`), EGC fee management (`egc_blocks`), DNG payment gateway integration (webhook inbox, HMAC checksum, reconciliation). |
| **Notification** | V2 outbox pipeline (domain event → outbox → message → delivery), Email + Realtime channels, campus isolation, ops monitoring routes. |

**Shared layer:** `app/Services/*` (79 large legacy services), `app/Models/*` (104 Eloquent models), `app/Http/*` (shared controllers/middleware/requests/responses).

**Frontend:** Vue 3 SPA mounted via Inertia. Single page resolver in `resources/js/app.ts` auto-binds `AppLayout` to all pages except auth/login and campus selection.

---

## 3. Tech Stack & Key Dependencies

| Layer | Choice | Version |
|---|---|---|
| Backend language | PHP | 8.4 (composer `^8.3`) |
| Backend framework | **Laravel** | **13** (`laravel/framework ^13.0`) |
| Frontend language | TypeScript | 5.2 |
| Frontend framework | **Vue 3** | 3.5 |
| Inertia bridge | **Inertia v3** | `@inertiajs/vue3 ^3.0` + `inertiajs/inertia-laravel 3.x-dev` |
| Styling | Tailwind CSS | 4 |
| UI primitives | Reka-UI (shadcn-style) at `@/components/ui` | — |
| Forms (page) | `useForm` from `@inertiajs/vue3` | — |
| Forms (modal) | `vee-validate` + `Zod` + `useApi` | — |
| Tables | `@tanstack/vue-table`, `@tanstack/vue-virtual` | — |
| Editor | TipTap 3 | — |
| State | Pinia 3 (sparingly) + Inertia props | — |
| Real-time | Laravel Echo + Pusher + **Ably** | — |
| Auth | **Sanctum** + custom actor middleware; Socialite (Google) | — |
| Database | MySQL/MariaDB | 8 |
| Cache/queue | Redis | — |
| Web server | **FrankenPHP** (prod) / Caddy (dev) | — |
| Routing helper | Ziggy (`route()` in TS) | `^2.4` |
| Excel | `maatwebsite/excel` | — |
| Tests | **Pest 4** (PHP), `vue-tsc` + ESLint (FE) | — |
| Runtime | **Docker-first** via `./scripts/dev.sh` | — |
| Package mgr | composer + **pnpm@10.26.0** | — |

> **Version awareness:** Laravel 13 + Inertia v3 — many v2 APIs are removed. Always consult `docs/inertiajs-vue-info.md` before writing Inertia code. See `AGENTS.md` for the "Forbidden Patterns" cheat-sheet.

---

## 4. Folder Structure & Module Responsibilities

```
/
├── app/                     # PHP application
│   ├── Modules/{Identity, Academic, Finance, Notification}/
│   │   ├── Actions/         # write paths — VerbEntityAction::run()
│   │   ├── Queries/         # read models — VerbEntityContextQuery::handle()
│   │   ├── Http/{Web/Admin, Api, Requests}/
│   │   ├── Policies/
│   │   ├── Providers/
│   │   └── routes/{web,api}.php
│   ├── Services/            # 79 legacy/shared services (mega files live here)
│   ├── Models/              # 104 Eloquent models
│   ├── Http/
│   │   ├── Controllers/{Admin, Api, Web}/   # legacy HTTP layer
│   │   ├── Middleware/                       # 17 custom middleware
│   │   ├── Requests/
│   │   └── Responses/ApiResponse.php         # mandatory API envelope
│   ├── Console/Commands/    # 32 artisan commands
│   ├── Events/ Listeners/ Jobs/ Mail/ Notifications/ Exports/ Imports/
│   ├── Enums/ Constants/ Helpers/ Policies/ Providers/ Traits/ Support/
│   ├── Shared/              # DTO/, Support/ — Contracts/ not yet created
│   └── Repositories/V1/     # legacy versioned repos
│
├── bootstrap/app.php        # Laravel bootstrap + middleware registration
├── config/                  # 21 config files (permission.php is large)
├── database/migrations/     # 209 migrations (148 from 2025, 58 from 2026)
├── docker/                  # Dockerfiles, docker-compose, Caddy configs
├── docs/                    # canonical engineering docs (102+ files)
│   ├── codebase-discovery-2026-05-12.md   # latest deep snapshot
│   ├── system-architecture.md, code-standards.md, project-overview-pdr.md
│   ├── inertiajs-vue-info.md (209 KB Inertia v3 ref), useDataTable-examples.md
│   ├── rules/               # 24 task-specific rule files
│   ├── decisions/ features/ plans/ product/ stories/ templates/ ui/
├── public/                  # web root
├── resources/
│   ├── js/                  # Vue SPA
│   │   ├── app.ts, ssr.ts   # entries
│   │   ├── pages/           # ~40 Inertia page folders
│   │   ├── components/      # incl. ui/ (Reka-UI), filters/, finance/, forms/
│   │   ├── composables/     # 30+ — useDataTable is canonical for tables
│   │   ├── layouts/, lib/, schemas/, stores/, types/, utils/
│   │   └── directives/permission.ts  # v-can, v-can-any
│   ├── css/                 # app.css (theme tokens), tiptap.css
│   └── views/app.blade.php  # Blade root
├── routes/
│   ├── web.php + web/*      # 35 split files
│   ├── api.php              # public system-config (!), DNG webhook, health, v1
│   ├── api/{admin.php, modules.php, uploads.php, v1/{student.php, lecturer.php}}
│   ├── console.php          # scheduled commands
│   └── channels.php
├── scripts/                 # dev.sh (canonical wrapper), prod/deploy/setup scripts
├── tests/                   # Pest — Feature (53) + Unit (7)
├── .codex/                  # Codex project config; repo hooks disabled
├── .cursor/ .opencode/ .trellis/ .vscode/
│                            # non-primary tool configs; Harness remains canonical
├── history/                 # historical investigation notes, not a live workflow
└── (root configs: composer.json, package.json, vite.config.ts, tsconfig.json,
   phpunit.xml, eslint.config.js, .prettierrc, components.json, .env.example)
```

---

## 5. Entry Points — How to Run

> **Always use Docker wrappers.** Host PHP / pnpm should not be assumed to exist.

### Initial setup

```bash
composer install
pnpm install                                  # NOT npm — pnpm@10.26 declared
cp .env.example .env
./scripts/dev.sh start                         # build + up Docker stack
./scripts/dev.sh artisan key:generate
./scripts/dev.sh artisan migrate
```

### Day-to-day

```bash
./scripts/dev.sh start          # up
./scripts/dev.sh stop           # down
./scripts/dev.sh status         # ps
./scripts/dev.sh logs app       # follow logs
./scripts/dev.sh shell          # exec sh in app container
./scripts/dev.sh artisan <cmd>  # any artisan command
./scripts/dev.sh composer <cmd> # composer in container
./scripts/dev.sh npm <cmd>      # delegates to pnpm in container
./scripts/dev.sh mysql          # mariadb client
```

### Dev server

`./scripts/dev.sh npm run dev` (vite at `localhost:5173`, app at `localhost:8000`, Mailpit at `localhost:8026`).

### Build

```bash
./scripts/dev.sh npm run build       # SPA bundle
./scripts/dev.sh npm run build:ssr   # + SSR bundle
```

### Test / quality

```bash
./scripts/dev.sh test                      # Pest 4
./scripts/dev.sh npm run type-check        # vue-tsc --noEmit
./scripts/dev.sh npm run lint              # eslint . --fix
./scripts/dev.sh npm run format:check      # prettier --check resources/
./scripts/dev.sh artisan pint              # PHP code style

composer ci                                 # pint --test + pest + lint + format + type-check
composer pre-push                           # ./scripts/pre-push.sh
```

### Health endpoints

`GET /up`, `GET /health`, `GET /api/health`.

---

## 6. Major Hot Spots & Technical Debt

**Critical (`C`) and high (`H`) items.** Full table in `docs/codebase-discovery-2026-05-12.md §6`.

### Security
- **C — `S1`** Public `/api/system-config*` endpoints (GET/PUT/POST/upload) have **no auth middleware** — config tampering / exfiltration risk. `routes/api.php:24-27`.
- **H — `S2`** Finance module API uses `web` + `auth` instead of the standard `auth:sanctum` + actor model.
- **H — `S3`** `.env.example` ships with a real-looking `APP_KEY=base64:…` value (line 14) — confusing during onboarding.

### Architecture
- **C — `A1`** **Doc-vs-code drift:** `app/Shared/Contracts/{Domain}/` is mandated for cross-module data but **does not exist**. Modules host their own internal Contracts instead.
- **H — `A2`** **Mega-services** exceed the 800-line standard by 10×+. Top: `AssessmentReportService.php` (113 KB), `AssessmentManagementService.php` (103 KB), `StudentAcademicSummaryService.php` (66 KB), `EventParticipationService.php` (59 KB), `UnitExcelImportService.php` (49 KB).
- **H — `A3`** **Frontend filter-stack drift:** four parallel composables in use (`useDataTable` is canonical; `useInertiaFilters`, `useFilters`/`useTableFilters`, `useServerTableQuery` are legacy/transitional).

### Delivery / Ops
- **C — `D1`** **All 3 GitHub Actions workflows are commented out** (`# name:` on the first non-blank line). **No CI-enforced merge gates** — local discipline only.
- **H — `D2`** Deploy script drift — three overlapping entrypoints (`scripts/deploy.sh`, `scripts/deploy-production.sh`, `scripts/prod.sh`); canonical unresolved.

### Migration debt
- Legacy `payment_allocations` table still exists in schema but no longer in runtime.
- `students.gc_to_course_transition_semester` deprecated — runtime reads `students.intake_major`.
- Notification V2 is Phase-1 clean-slate; legacy `notifications` history not migrated.

### Test coverage
- 60 test files vs 833 PHP source files. Thin relative to size, made worse by CI being disabled.

### Misc
- `repomix-output.xml` (13.4 MB) checked in — regenerable artifact.
- Both `pnpm-lock.yaml` and `package-lock.json` committed (pnpm is canonical; npm lock is stale).
- `inertiajs/inertia-laravel: 3.x-dev` tracks a dev branch, not a release tag.

---

## 7. Conventions

### Backend
- **Classes** PascalCase. **Action** = `VerbEntityAction` with `static run(array $data)`. **Query** = `VerbEntityContextQuery` with `handle(...$args)`.
- **Controllers** are **thin orchestration only**: validate via FormRequest → call Action/Query → return response.
- **API responses must use `ApiResponse::success() / error() / paginated() / validationError() / …`** — never `response()->json([...])` directly. Envelope: `{ success, timestamp, data?, meta?, message?, errors? }`.
- **Validation** via `FormRequest`, never inline.
- **Transactions** via `DB::transaction()` for multi-write flows.
- **Cross-module data** must go through Contracts in `app/Shared/Contracts/{Domain}/` (see A1 — folder doesn't exist yet; promote internal-module contracts when this is set up).
- **No Eloquent joins across module boundaries.**
- **Authorization:** routes with no `{id}` → Gate; routes with `{id}` → Policy via `$this->authorize('action', $model)`.
- **Campus scoping** is explicit. Active campus comes from `session('current_campus_id')`.
- Prefer `declare(strict_types=1)` in new code.

### Frontend
- `<script setup lang="ts">` for all new components.
- **Inertia page forms** → `useForm` from `@inertiajs/vue3`. Never vee-validate on Inertia pages.
- **Modal / drawer non-navigating forms** → vee-validate + Zod + `useApi`/`useApiRequest`.
- **Filter / pagination pages** → `useDataTable` composable (`@/composables/useDataTable`). Other filter composables are legacy.
- **Routes** → `route('foo.bar', params)` from Ziggy. Never literal URLs like `/api/foo`.
- **Icons** → `lucide-vue-next`.
- **UI primitives** → `@/components/ui` (Reka-UI). Reusable filters: `FilterPanel`, `FilterSearchInput`, `FilterDateRange`, `FilterSelect` in `resources/js/components/filters/`.
- **DatePicker / TimePicker** from `@/components/ui` — never `<Input type="date">`. In modals pass `:portal-to="modalContentRef ?? undefined"`.
- Keep `useNativeDialog: false` in `app.ts` (Select/Combobox above modal depends on it).

### Inertia v3 specifics (mandatory)
| Wrong (v2) | Correct (v3) |
|---|---|
| `Inertia::lazy(fn ...)` | `Inertia::defer(fn ...)` or `Inertia::once(fn ...)` |
| `->with('success', 'msg')` | `Inertia::flash('success', 'msg')` |
| `$page.props.flash` | `usePage().props.flash` |
| `<Deferred :data="['x']">` | `<Deferred data="x">` (string) |
| `classSessions: Session[]` | `class_sessions: Session[]` (snake_case to match Laravel JSON) |

### Naming
| Concept | Format | Example |
|---|---|---|
| Module | PascalCase singular | `Academic`, `Finance` |
| Action | `VerbEntityAction` | `CreateEventAction` |
| Query | `VerbEntityContextQuery` | `ListAcademicRecordsQuery` |
| Vue component file | PascalCase | `RecordTable.vue` |
| Composable | `useXxx.ts` | `useDataTable.ts` |
| DB table / column | snake_case | `course_offerings`, `recipient_user_id` |
| Route name | dot notation | `academic.records.index` |
| URL segment | kebab-case | `/api/v1/student/parent/auth/refresh` |

### Quality gates (local-only — CI disabled)
Before merging, run:
```bash
./scripts/dev.sh test
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
```
If a check cannot be run, **record the gap in the change summary**.

### Documentation discipline
Every core doc has: **last updated date, owner, status, unresolved questions**. Keep docs under 800 LOC. Update in the same change window when routes / contracts / auth / architecture change.

---

## Glossary / Ubiquitous Language

> **Discipline (see `docs/agents/documentation-rules.md`):** this is the project's domain glossary, **not** a notebook. Add only **stable business terms**, max **1–2 sentences** each. No implementation detail, dates, bug logs, or decisions. Grow it **lazily** — add a term when it's actually used, not in bulk. When your output names a domain concept (issue title, test name, proposal), use the term **as defined here**; if a concept isn't here yet, that's a signal to add it (or reconsider the wording).

Seed terms (cross-cutting platform language; extend per domain as work lands):

| Term | Definition |
|---|---|
| **Campus scoping** | All data is scoped to the active campus, taken from `session('current_campus_id')`. Cross-campus access is never implicit. |
| **Actor** | The segmented caller identity — `student`, `parent`, or `lecturer` — resolved by actor middleware on top of Sanctum auth. |
| **Module** | A bounded domain unit under `app/Modules/{Identity, Academic, Finance, Notification}` owning its own Actions, Queries, routes, and policies. |
| **Action** | A write-path use case, named `VerbEntityAction` with `static run(array $data)`. |
| **Query** | A read model, named `VerbEntityContextQuery` with `handle(...$args)`. |
| **ApiResponse envelope** | The mandatory API response shape `{ success, timestamp, data?, meta?, message?, errors? }` produced via `ApiResponse::*`. |
| **Application** _(Admissions)_ | A prospective student's dossier, captured before they become a Student; created by the admissions CRM via API or entered manually by staff. |
| **Applicant** _(Admissions)_ | The person an Application is about; becomes a Student only when approved, and is neither a User nor a Student until then. |
| **Approve** _(Admissions)_ | The decision to admit an Applicant so they become a Student. Not "convert", "admit", or "enroll". |
| **Reject** _(Admissions)_ | The decision that an Applicant will not become a Student. Not "decline" or "deny". |
| **Guardian** _(Admissions)_ | A parent or other responsible adult linked to an Applicant or Student. Use over "parent" when the relationship may not be a parent. |
| **Revoke** _(Admissions)_ | Undoing a mistaken approval while the new Student has no downstream activity: it returns the Application to pending. Distinct from Withdraw. |
| **Withdraw** _(Admissions)_ | Removing a Student who has already studied, preserving all their academic/financial history. Distinct from Revoke. |
| **Document type** _(Admissions)_ | A category of admission document (e.g. CCCD, transcript), defined by the admissions CRM's catalog and mirrored into Swinx. |

## Onward Pointers

- Architecture decisions (ADRs) → `docs/adr/` (legacy decision records live in `docs/decisions/`)
- Documentation boundaries / anti-bloat rules → `docs/agents/documentation-rules.md`
- Deep snapshot → `docs/codebase-discovery-2026-05-12.md`
- Architecture truth → `docs/system-architecture.md`
- Standards → `docs/code-standards.md` and `docs/rules/`
- Frontend Inertia v3 reference → `docs/inertiajs-vue-info.md`
- Canonical filter/table pattern → `docs/useDataTable-examples.md`
- AI agent quick-start → `AGENTS.md`