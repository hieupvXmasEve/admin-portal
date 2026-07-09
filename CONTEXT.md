# CONTEXT.md — Swinx Project Onboarding

> Single-page orientation for humans and AI agents new to this repo.
> For depth, jump to `docs/codebase-discovery-2026-05-12.md`. For all agent operating rules, see `AGENTS.md`.

**Last updated:** 2026-07-09
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

> **Migration note (ADR-0026):** the shared `app/Models/*` layout is *current state*, not target. Academic and Finance are bounded contexts that do **not** share money/lifecycle models. Money models move to `App\Modules\Finance\Models`; only `Student` stays shared as an identity reference. Do not add new cross-context reads through shared Eloquent models — use `app/Shared/Contracts/*`.

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
│   ├── Shared/              # Contracts/, DTO/, Support/ — contract coverage partial (see A1)
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
- **H — `A1`** **Partial contract migration:** `app/Shared/Contracts/{Academic,Finance}/` **now exists** (e.g. `HubStudentFinanceSummaryReader`, `RetakeRegistrationPaymentSyncer`, `ExamResitAttemptPaymentSyncer`), but coverage is partial — the clean contract path and direct cross-module shared-Eloquent access still coexist (e.g. `GetStudentFeeSummaryQuery` reads `FinanceCharge`/`Payment` directly). Target: all cross-context reads go through contracts (ADR-0026); no new shared-model reads.
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
- **Cross-module data** must go through Contracts in `app/Shared/Contracts/{Domain}/` (folder exists; coverage still partial — see A1. Add new cross-context reads as contracts, never as shared-Eloquent access).
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
| **All-campus AI scope** | A Staff Copilot query scope that may include multiple campuses only when the staff member has an explicit all-campus data permission. It is never implied by being an admin, having an AI provider, or having `view_ai_metrics`. |
| **Actor** | The segmented caller identity — `student`, `parent`, or `lecturer` — resolved by actor middleware on top of Sanctum auth. |
| **Module** | A bounded domain unit under `app/Modules/{Identity, Academic, Finance, Notification}` owning its own Actions, Queries, routes, and policies. |
| **Cross-context Domain Event** | A durable fact emitted by one context for another context to react to, without giving the emitting context ownership of the receiver's rules. Events that can affect money obligations or academic lifecycle must be auditable and retryable. |
| **Cross-context Query Contract** | A synchronous question one context asks another context when it needs an immediate answer without reading the other context's internals. Fresh decisions read the owner context's source of truth; projections are allowed only when staleness is acceptable. |
| **Action** | A write-path use case, named `VerbEntityAction` with `static run(array $data)`. |
| **Query** | A read model, named `VerbEntityContextQuery` with `handle(...$args)`. |
| **ApiResponse envelope** | The mandatory API response shape `{ success, timestamp, data?, meta?, message?, errors? }` produced via `ApiResponse::*`. |
| **Staff Copilot** | A staff/admin web AI assistant in Swinx that helps authorized staff inspect and analyze allowlisted academic and finance data within the current campus. It is read-only unless a separate write-approval story accepts mutations. |
| **Allowed AI data** | The subset of Swinx business data an AI capability may use after staff permission, campus scope, tool/catalog allowlists, redaction, and audit policy are applied. It is not a raw database dump or unrestricted transcript memory. |
| **Report/metric Q&A** | A Staff Copilot flow where staff ask natural-language questions about allowlisted operational metrics, and Swinx answers through validated metric queries with source, filter, scope, freshness, and confidence evidence. |
| **MVP metric seed** | The first Staff Copilot report/metric Q&A metric set for Academic and Finance: student status count, academic defer count, finance collection summary, finance fee monitor summary, and DNG lifecycle attention. |
| **Structured AI answer** | A Staff Copilot response shaped for consistent rendering and validation, while the staff-facing text stays natural and non-technical. In the MVP, it contains descriptive observations only, not risk scoring or recommendations. |
| **AI query permission notice** | A natural-language Staff Copilot notice that tells staff whether the assistant can query the requested data for them. It should avoid technical source, filter, tool, schema, or audit terminology. |
| **AI Data Access Preview** | A staff-facing summary of the Academic and Finance data groups Staff Copilot may query for the current staff member, based on connected provider status, AI entry permission, domain data permissions, and current campus scope. It uses natural language instead of tool, schema, or audit terminology. |
| **Free-form staff prompt** | A Staff Copilot question entered in natural language without metric selectors or frontend minimum-text gating. Backend safety still applies through provider gating, authorization, redaction, length limits, tool validation, and audit. |
| **Safe AI markdown** | A limited Staff Copilot answer format allowing headings, paragraphs, emphasis, lists, blockquotes, and markdown tables. Raw HTML, images, external links, code blocks, and copy actions are not part of the MVP answer surface. |
| **Natural-language answer synthesis** | The use of an approved AI provider/model to turn redacted, structured Swinx query results into staff-friendly language. It does not grant data access, run queries, decide permissions, or replace backend audit evidence. |
| **BOD AI Operations Analyst** | A read-only Staff Copilot mode for leadership that summarizes Academic and Finance operations through aggregate metrics and controlled drilldown. It is governed by a dedicated BOD AI access permission, separate from operational staff and super-admin permissions. |
| **Controlled MCP server** | A Swinx-owned, OAuth-protected MCP server that authorized staff connect to from external agent clients. Claude is the v1 client; other clients (ChatGPT, Codex) are prospective. "Controlled" means access-controlled — per-user identity, Swinx permission, campus scope, redaction, and audit gate every call — **not** network-private; it is publicly reachable so cloud clients can connect inbound. It exposes only read-only tools, and the client's own model performs natural-language synthesis. |
| **MCP client authorization** | A per-user OAuth grant that lets one external agent client act for one staff member against the Controlled MCP server. The client inherits that staff member's **full** Swinx permissions (no per-client capability narrowing) and is revocable and auditable independently per client. Replaces the earlier "scoped service token" model, which assumed per-agent service accounts. |
| **Connected AI provider** | A staff-owned AI provider configuration that is enabled, has a saved API key, and has passed Swinx's provider test. Staff Copilot is unavailable until the staff member connects an AI provider. |
| **AI access layers** | The separate gates for Staff Copilot: a connected AI provider enables the assistant experience, `view_ai_metrics` allows entry to AI metric Q&A, and domain data permissions decide which Academic or Finance metrics may be queried. Having an AI provider or AI entry permission never grants data access by itself. |
| **Multi-tool orchestration** | A Staff Copilot execution flow where an AI provider may plan multiple allowlisted tool calls for one staff question, while Swinx validates each step's permission, scope, schema, limits, and audit evidence before execution. |
| **Compare metrics** | A Staff Copilot capability that compares one allowlisted metric with the same filters and grouping between the current term and previous term in the MVP. It does not invent metric formulas or read outside the metric catalog. |
| **Analyze context** | A Staff Copilot capability that turns already retrieved, allowed AI data into observations and suggested follow-up questions. It does not query the database directly and does not produce risk levels or recommendations in the MVP. |
| **Bounded metric context** | The short-lived Staff Copilot context used for follow-up metric questions, carrying only redacted current metric, filters, groupings, current term, and recent tool history. It helps propose the next query arguments but never grants permission or bypasses validation. |
| **Clarification-first AI behavior** | Staff Copilot must ask a clarifying question instead of querying data when the requested metric, filter, term, entity, or context reference has no single valid interpretation. |
| **Current term** | The single academic term or semester Swinx resolves for the current campus when staff ask for current-period metrics. If Swinx cannot resolve exactly one current term, Staff Copilot must ask for clarification before querying. |
| **Student-centric analysis** | A Staff Copilot analysis flow centered on one resolved Student, using only allowed AI data for that student to explain academic, finance, attendance, enrollment, and lifecycle context. |
| **Query-only AI capability** | An AI capability that may retrieve and analyze allowed AI data but must not mutate records, create tasks, send messages, change workflow state, or trigger side effects. |
| **Resolved Student** | The single Student accepted by Staff Copilot conversation context as the current subject for a follow-up analysis. If no Student or multiple Students are plausible, Staff Copilot must ask for clarification before querying student-specific data. |
| **Staff Student Case Review Summary** | A query-only Staff Copilot output that summarizes one resolved Student's allowed AI data into evidence-backed observations and suggested follow-up questions for staff. It explains notable academic, finance, attendance, enrollment, and lifecycle context without taking action on the student's record. |
| **Chat-initiated case review** | A Staff Copilot review flow started from the current chat after exactly one Student has been resolved in conversation context. It is not a separate button or entry point on the student detail page. |
| **Case review section bundle** | The default allowed-data bundle for a Staff Student Case Review Summary: identity, academic summary, bounded recent enrollments, attendance summary, finance summary, and lifecycle actions. Missing section permissions produce hidden-section notices instead of failing the whole review. |
| **Student Identity** _(Shared Kernel)_ | The stable reference identity for a Student across contexts: identifiers, display name, campus, and account linkage. It is not the source of truth for academic lifecycle or money behavior. _Avoid_: "Student as God Model". |
| **Academic Student Lifecycle** _(Academic)_ | The Academic-owned study state and rights of a Student: enrollment, registration, progression, defer/resume/dropout/return, eligibility, and graduation. Finance may consume it through events, contracts, read models, or snapshots, but must not infer it from internal Academic relationships. |
| **Finance Student Account** _(Finance)_ | The Finance-owned money account for a Student: charges, receivables, invoices, payments, debt, refunds, credit/wallet, and payment requests. It references Student Identity but does not own the Student's academic lifecycle. |
| **Student Hub** _(Academic)_ | The single student-centric operational console where Academic Affairs staff (Cán Bộ Đào tạo) view and act on one Student's whole academic lifecycle in one place — records, scores, placement & progression, lifecycle actions. Finance and scholarships appear only as a read-only summary that links out to the Finance Office. Mirrors the **Case review section bundle**. Not "Academic Summary" (old name), the "Show / detail page", or "Student 360" (which is finance-only). |
| **Course Offering Cockpit** _(Academic)_ | The single course-offering-centric operational console where academic staff manage one `CourseOffering`'s setup, roster, sessions, attendance (recording and readiness), scores, completion, surveys, and Canvas-linked constraints. The Course Offering detail page owns operations; Course Statistics and the Attendance page remain report-only surfaces. Not a parallel "Course Statistics management" flow. |
| **Canvas grade sync** _(Academic)_ | Pulling students' Canvas submission scores into a course offering's assessment grid, for all students or a selected subset. Canvas is the source of truth: applying a sync overwrites local component scores, and it is only available while the offering is not completed — afterwards grades change only through Recalculate. |
| **Sync preview** _(Academic)_ | The informational diff staff review before a Canvas grade sync or Recalculate applies: what changes per student and component, what cannot sync and why, and (for Recalculate) which students would be notified. A preview commits nothing — the apply step reads Canvas afresh. |
| **Readiness blocker** _(Academic)_ | A declared condition that prevents a course-offering lifecycle action (e.g. finalize) until resolved — such as sessions without recorded attendance, or a Canvas-mapped offering not yet synced. The Course Offering Cockpit renders blockers from the backend contract; the frontend never infers them. |
| **Academic Attendance Rate** _(Academic)_ | The primary attendance KPI for academic and BOD reporting: attended recorded sessions divided by attendance-applicable recorded sessions. Late attendance counts as attended; excused, official leave, medical leave, and not-recorded sessions are not penalizing absence evidence. |
| **Operational Presence Rate** _(Academic)_ | A secondary attendance operations KPI comparing present or late active roster students against expected active attendees for a class session or aggregate report. It highlights attendance-taking and roster compliance separately from the academic attendance KPI. |
| **Curriculum Module** _(Academic)_ | A named grouping of Units inside a Curriculum Version (e.g. "Software 1") that receives its own aggregate grade and pass status. Distinct from **Module** (the code-architecture term above). |
| **Module grade** _(Academic)_ | The credit-weighted average of a Curriculum Module's graded (0-5) units, rounded once to a whole grade. It exists only once every graded unit has passed — no provisional average is shown. Pass/fail units are excluded from the average, but every unit — graded or pass/fail — must pass for the module to pass. Module grades never feed GPA — GPA stays unit-based. |
| **Best attempt** _(Academic)_ | When a Student has multiple academic records for the same Unit (retakes), the attempt with the highest grade represents that Unit in module aggregation; the Unit counts as passed if any attempt passed. |
| **EGC** _(Academic)_ | The Pre-Uni GC English-preparation phase. A Student is "in EGC" while their status is `intake_pre_uni_gc`, and leaves EGC on transition to `intake_course`. The Academic Progression stream tracks a Student's journey through EGC. |
| **Student Action** _(Academic)_ | A formal event that changes a Student's enrollment status — academic defer, resume, dropout, campus transfer, admission deferral. Recorded in the action log. Some action types require an authorizing Decision. Not a generic "status change". |
| **Academic Progression** _(Academic)_ | A Student's English-level and course-stage journey through EGC, recorded as progression events: placement, English-level change, course-stage change, and an *IELTS recorded* event. The IELTS event is only a timeline marker that points to an **IELTS certificate** — the scored record lives separately, not inside the progression event. Distinct from Student Action (enrollment status, not level). |
| **Placement** _(Academic)_ | The first Academic Progression event for a Student entering EGC — their initial English level and course stage. Not a separate concept; it is progression's starting point. |
| **IELTS certificate** _(Academic)_ | A recorded IELTS result for a Student — overall band score and issue date — used to qualify a Student's transition from `intake_pre_uni_gc` to `intake_course`. The scored record itself, distinct from its supporting **IELTS scan**. _Avoid_: "IELTS record", "IELTS document". |
| **IELTS scan** _(Academic)_ | The uploaded document evidencing an **IELTS certificate**. When absent it is flagged "scan missing"; the `intake_course` transition requires it unless staff explicitly allow missing documents. Not the band score (that is the certificate). _Avoid_: "IELTS document". |
| **Decision** _(Academic)_ | A formal governance document (number, signer, issue/expiry date, scan) that covers one or more Students. It may authorize lifecycle events — a Student Action and/or an EGC progression transition (into `intake_pre_uni_gc` or `intake_course`) — or be purely informational (attached to Students without changing status). Some transitions require an authorizing Decision, which may be attached after the transition; a missing-decision report surfaces those still lacking one. Not a separate status track. |
| **Tổng tiền đã nộp** _(Finance)_ | The total money a Student has paid to the school, regardless of whether it has already been matched to a fee. Use over "tổng tiền nhận". |
| **Financial Obligation** _(Finance)_ | The Finance-owned decision that a fact from **any** source system (academic, admission, bookstore, insurance, finance itself, …) has created, changed, cancelled, or superseded something **payable** (a debit receivable) for a billing account. A source event such as enrollment approval is not itself a fee; Finance decides whether and how it becomes an obligation. Academic is one source among many, not the owner. Credits and discounts are **not** obligations — they are separate aggregates (see FinanceCreditEntitlement, FinanceDiscountEntitlement). _Avoid_: "academic event as invoice", "Academic obligation", "obligation with `direction=credit/discount`". |
| **FinanceObligation** _(Finance)_ | The persisted Finance-owned, **source-agnostic, debit-only** aggregate that records one payable-receivable intake and its decision lifecycle. It stores source identity, obligation type, Finance-priced amount, and a `lifecycle_status` (`requested → accepted → rejected → cancelled → voided → superseded`). It **never** stores settlement state — how much has been paid is always derived from the settlement ledger. Only debit obligations enter financial-clearance checks. _Avoid_: "AcademicObligation", "candidate table", credit/discount rows. |
| **FinanceCreditEntitlement** _(Finance)_ | The Finance-owned aggregate for a **credit/grant** in the student's favour — scholarship grant, defer credit, overpayment credit, sponsor credit, manual credit memo. It has a consumption lifecycle (`granted → approved → revoked → expired → refunded`; allocation `available → partially_applied → fully_applied`), **not** a payment-settlement lifecycle. It reduces a debit obligation's outstanding when applied (recorded as a Credit Application); it never appears as an unpaid blocker. _Avoid_: modelling a credit as a FinanceObligation or as a negative charge row. |
| **FinanceDiscountEntitlement** _(Finance)_ | The Finance-owned aggregate for a **discount** that reduces a specific fee — voucher, campaign/policy discount, EGC retake discount, manual discount. Consumption lifecycle (`requested → approved → rejected → cancelled → revoked → expired`; allocation `available → partially_allocated → fully_allocated → released`), truth in `invoice_discounts`/`discount_allocations`. It is never "paid" and never a clearance blocker. Supersedes the older informal **Discount entitlement** term. _Avoid_: "discount that must be paid", `direction=discount` on an obligation. |
| **Finance Intake Contract** _(Finance)_ | The single source-facing entry point into Finance. Every source sends one request shape (`source_system` + `source_kind` + `source_ref` + `financial_effect` + `effect_type` + `facts`); Finance's intake router routes by `financial_effect` to the correct aggregate — `debit → FinanceObligation`, `credit → FinanceCreditEntitlement`, `discount → FinanceDiscountEntitlement`. One door in, three correctly-named aggregates inside. _Avoid_: "one aggregate with a direction column". |
| **Obligation Source Reference** _(Finance)_ | The neutral three-part correlation token every source uses to link to a Finance aggregate: `source_system` + `source_kind` + `source_ref`. The **source owner mints `source_ref`** (Finance mints it for finance-owned intakes). Invariant: Finance never stores an external Eloquent class name or source table id; source modules never store `finance_charge_id` / `finance_obligation_id` in business tables. The two sides exchange only this triple. _Avoid_: `CourseRetakeRegistration::class` as `source_type`, `finance_charge_id` FK on Academic. |
| **Financial Obligation Candidate** _(Finance)_ _(deprecated as a separate aggregate)_ | Not a distinct table/aggregate. A "candidate" is simply a FinanceObligation in `lifecycle_status = requested` (not yet `accepted`). A dedicated pre-obligation pipeline (normalize/dedupe/merge/split many raw signals before an obligation exists) would only be introduced if a real need appears; until then, do not model candidates separately. _Avoid_: two aggregates for one lifecycle. |
| **Settlement state (derived)** _(Finance)_ | The paid/unpaid position of a FinanceObligation — `unpaid`, `partially_paid`, `paid`, `overpaid`, `settled_by_discount` — **computed** from `invoice_lines`, `payment_applications`, `discount_allocations`, credit applications, and `payments`, never stored as canonical status. Any stored summary is a rebuildable projection/cache; if it disagrees with the ledger, the ledger wins. _Avoid_: `paid`/`partially_paid` inside `finance_obligations.lifecycle_status`. |
| **Financial Clearance** _(Academic/Finance)_ | The authoritative go/no-go a source module gets from Finance before an **irreversible or hard-gated action** (exam sitting, active course participation, final registration, irreversible progression). It is obtained by a **synchronous** query to a Finance settlement contract that reads the authoritative ledger — never from a local projection. A local projection such as Academic's `hq_fee_status` is display/queue convenience only and MUST NOT be the sole condition of a hard gate. The **absence** of an obligation is never clearance — a chargeable source with no matching obligation blocks as `missing_finance_obligation`; a genuine no-fee case must be an explicit Finance decision (`waived` / `not_required` / `zero_amount`), never inferred from absence. _Avoid_: "gate on `hq_fee_status`", "no obligation = cleared". |
| **Fixed Retake Fee** _(Finance)_ | A deterministic Finance obligation for retaking a failed or repeated course/unit when the fee is fixed by Finance policy. It is a separate obligation from tuition, defer settlement, refund, or scholarship adjustment. |
| **Fixed Resit Fee** _(Finance)_ | A deterministic Finance obligation for a resit attempt when the fee is fixed by Finance policy. It is one of the two debit obligation types in the ADR-0026 slice-1 **full cutover** (with Fixed Retake Fee) — a complete cutover, not a pilot; being deterministic and evidence-free, it auto-accepts at intake. |
| **Finance Charge** _(Finance)_ | The Finance-owned ledger/read-model row existing settlement, DNG, Fee Monitor, reports, and exports read for payable lines; in the Finance Obligation v2 target it is projected only from accepted **debit** obligations. Credits and discounts use entitlements/applications instead of new negative charge rows; legacy negative rows are migration data to convert or void, not a model to extend. |
| **Credit Application** _(Finance)_ | In the Finance Obligation v2 target, the ledger record of a FinanceCreditEntitlement being applied against a specific payable line, reducing its outstanding like a payment does. It is the only carrier of a credit's settlement effect. _Avoid_: negative charge rows as credit carriers. |
| **Obligation Type Registry** _(Finance)_ | The code-owned definition of each obligation/entitlement type's behaviour — financial effect, allowed sources, pricing strategy, collection code, cancel policy. Adding or changing a type is a code change; only pricing rules are runtime-configurable. |
| **Billing Account** _(Finance)_ | The Finance-owned payer identity that obligations and entitlements attach to. Every Student has exactly one; an Applicant gets one only when a fee actually arises, and it carries over on Approve. |
| **Pricing Operations** _(Finance)_ | Finance staff's runtime management of pricing rules — amounts, currency, effective dates, active state — for obligation types. It never defines or alters obligation types themselves. |
| **Finance Invoice** _(Finance)_ | A downstream billing or presentment document that groups and presents payable lines for issue, print, send, collection, or audit. It is not the first commitment created from an Academic fact. |
| **Đã thu** _(Finance)_ | The portion of a Student's paid money that has been matched to current fee obligations. In student-facing finance views, this is the familiar label for paid tuition/fees, not a raw cash-receipt total. |
| **Còn phải thu** _(Finance)_ | The amount the school still needs to collect from the Student for current fee obligations. Use over "balance" or "outstanding" in staff-facing student finance pages. |
| **Còn dư** _(Finance)_ | Paid money that is not currently matched to any fee obligation and needs follow-up, such as allocation, refund, or manual review. Use over "unapplied credit" in staff-facing student finance pages. |
| **Cần kiểm tra** _(Finance)_ | A non-blocking staff-facing signal that a Student's finance data has something to review, such as paid money not matched to fees or inconsistent supporting records. It does not mean the Student necessarily owes more money. |
| **Defer settlement** _(Finance)_ | The Finance handling of an Academic defer: stop or review the original obligation, preserve or consume only real paid cash according to policy, and keep the future return-study obligation explicit. _Avoid_: "free future study". |
| **Deferred study scope** _(Academic/Finance)_ | The part of a Student's study preserved by a defer decision: either the whole term scope or selected courses. It is an academic right, not a money amount. |
| **Course-scope defer** _(Academic/Finance)_ | A defer decision that preserves selected courses only. It must not be treated as "partial amount" or a percentage of tuition. _Avoid_: "partial money defer". |
| **Term tuition entitlement** _(Academic/Finance)_ | The study right created by settling term-level tuition: the Student may study the covered curriculum courses for that term, even if some classes are offered later. It is not a fixed cash amount per course; failed-course retakes are separate. |
| **Early-study defer window** _(Academic/Finance)_ | The first two weeks from the first class session of the covered study scope, where a started term/course may still be eligible for fee preservation if Academic Affairs and Student Services confirm the case. Paid fees may be preserved; unpaid fees are not collected for the old scope. |
| **Partial early-study payment** _(Finance)_ | A payment state inside the early-study defer window where the Student paid only part of the relevant fee. It preserves only real paid cash as balance, not a full term tuition entitlement; the old obligation closes and return study creates a new obligation. |
| **Academic equivalence mapping** _(Academic/Finance)_ | The Academic decision that a replacement or equivalent course satisfies a covered deferred study scope. It maps study rights, not money; Finance uses it only to decide whether a new tuition obligation exists. |
| **Preserved cash** _(Finance)_ | Real paid money released from the deferred original obligation and kept available to settle a future obligation. It is not synthetic credit and not a reason to hide the future charge. |
| **Preserved cash surplus** _(Finance)_ | Preserved or otherwise unapplied paid money left after the relevant return-study obligation has been fully settled. It remains `Còn dư` for allocation, refund, or review; it is not automatically consumed or collected through DNG. |
| **Forfeited cash** _(Finance)_ | Real paid money consumed by a defer policy when tuition is not preserved. It does not include unpaid balance and must not create penalty debt by itself. _Avoid_: "unpaid forfeiture". |
| **Forfeit reason** _(Finance)_ | The free-text Finance explanation for why already-paid cash is consumed under a no-preserve defer policy. It is distinct from the Academic reason for the defer itself. |
| **Discount entitlement** _(Finance)_ | A rule-based right to reduce a specific fee obligation, including scholarships. It is not paid cash and does not automatically carry forward when a Student returns from defer. _Avoid_: "discount cash", "scholarship balance". |
| **Return-study obligation** _(Finance)_ | The payable obligation created when a Student returns to study a deferred scope. Preserved cash may settle it, but the obligation remains visible for reporting and audit. |
| **Return-study pricing basis** _(Finance)_ | The fee/rule basis used to price a return-study obligation. By default it is the current rule set for the return-study period, not the old deferred obligation's price. |
| **Balance-use choice** _(Finance)_ | The staff decision before DNG collection to use applicable preserved/unapplied cash or intentionally not use it with a free-text reason. |
| **Internal transfer settlement** _(Finance)_ | The parked Finance workflow for campus-only, program-only, or combined campus + program transfer inside Swinx. It is separate from defer settlement. |
| **Application** _(Admissions)_ | A prospective student's dossier, captured before they become a Student; created by the admissions CRM via API or entered manually by staff. |
| **Applicant** _(Admissions)_ | The person an Application is about; becomes a Student only when approved, and is neither a User nor a Student until then. |
| **Approve** _(Admissions)_ | The decision to admit an Applicant so they become a Student. Not "convert", "admit", or "enroll". |
| **Reject** _(Admissions)_ | The decision that an Applicant will not become a Student. Not "decline" or "deny". |
| **Guardian** _(Admissions)_ | A parent or other responsible adult linked to an Applicant or Student. Use over "parent" when the relationship may not be a parent. |
| **Revoke** _(Admissions)_ | Undoing a mistaken approval while the new Student has no downstream activity: it returns the Application to pending. Distinct from Withdraw. |
| **Withdraw** _(Admissions)_ | Removing a Student who has already studied, preserving all their academic/financial history. Distinct from Revoke. |
| **Document type** _(Admissions)_ | A category of admission document (e.g. CCCD, transcript), defined by the admissions CRM's catalog and mirrored into Swinx. |
| **Intended Program** _(Admissions)_ | The Program an Applicant requests on their Application, identified by the canonical `Program` code — the applicant's *intent*, distinct from the actual Program a Student is placed into on Approve. |
| **Intake** _(Admissions)_ | The Semester an Applicant intends to start in, identified by the canonical `Semester` code. With the Intended Program it determines the Curriculum Version the Student is enrolled under. |

## Onward Pointers

- Architecture decisions (ADRs) → `docs/adr/` (legacy decision records live in `docs/decisions/`)
- Documentation boundaries / anti-bloat rules → `docs/agents/documentation-rules.md`
- Deep snapshot → `docs/codebase-discovery-2026-05-12.md`
- Architecture truth → `docs/system-architecture.md`
- Standards → `docs/code-standards.md` and `docs/rules/`
- Frontend Inertia v3 reference → `docs/inertiajs-vue-info.md`
- Canonical filter/table pattern → `docs/useDataTable-examples.md`
- AI agent quick-start → `AGENTS.md`
