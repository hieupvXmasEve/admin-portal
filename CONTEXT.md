# CONTEXT.md — Swinx Project Onboarding

> Single-page orientation for humans and AI agents new to this repo.
> For depth, jump to `docs/codebase-discovery-2026-05-12.md`. For all agent operating rules, see `AGENTS.md`.

**Last updated:** 2026-07-01
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
| **All-campus AI scope** | A Staff Copilot query scope that may include multiple campuses only when the staff member has an explicit all-campus data permission. It is never implied by being an admin, having an AI provider, or having `view_ai_metrics`. |
| **Actor** | The segmented caller identity — `student`, `parent`, or `lecturer` — resolved by actor middleware on top of Sanctum auth. |
| **Module** | A bounded domain unit under `app/Modules/{Identity, Academic, Finance, Notification}` owning its own Actions, Queries, routes, and policies. |
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
| **Student Hub** _(Academic)_ | The single student-centric operational console where Academic Affairs staff (Cán Bộ Đào tạo) view and act on one Student's whole academic lifecycle in one place — records, scores, placement & progression, lifecycle actions. Finance and scholarships appear only as a read-only summary that links out to the Finance Office. Mirrors the **Case review section bundle**. Not "Academic Summary" (old name), the "Show / detail page", or "Student 360" (which is finance-only). |
| **Academic Attendance Rate** _(Academic)_ | The primary attendance KPI for academic and BOD reporting: attended recorded sessions divided by attendance-applicable recorded sessions. Late attendance counts as attended; excused, official leave, medical leave, and not-recorded sessions are not penalizing absence evidence. |
| **Operational Presence Rate** _(Academic)_ | A secondary attendance operations KPI comparing present or late active roster students against expected active attendees for a class session or aggregate report. It highlights attendance-taking and roster compliance separately from the academic attendance KPI. |
| **EGC** _(Academic)_ | The Pre-Uni GC English-preparation phase. A Student is "in EGC" while their status is `intake_pre_uni_gc`, and leaves EGC on transition to `intake_course`. The Academic Progression stream tracks a Student's journey through EGC. |
| **Student Action** _(Academic)_ | A formal event that changes a Student's enrollment status — academic defer, resume, dropout, campus transfer, admission deferral. Recorded in the action log. Some action types require an authorizing Decision. Not a generic "status change". |
| **Academic Progression** _(Academic)_ | A Student's English-level and course-stage journey through EGC, recorded as progression events: placement, English-level change, course-stage change, and an *IELTS recorded* event. The IELTS event is only a timeline marker that points to an **IELTS certificate** — the scored record lives separately, not inside the progression event. Distinct from Student Action (enrollment status, not level). |
| **Placement** _(Academic)_ | The first Academic Progression event for a Student entering EGC — their initial English level and course stage. Not a separate concept; it is progression's starting point. |
| **IELTS certificate** _(Academic)_ | A recorded IELTS result for a Student — overall band score and issue date — used to qualify a Student's transition from `intake_pre_uni_gc` to `intake_course`. The scored record itself, distinct from its supporting **IELTS scan**. _Avoid_: "IELTS record", "IELTS document". |
| **IELTS scan** _(Academic)_ | The uploaded document evidencing an **IELTS certificate**. When absent it is flagged "scan missing"; the `intake_course` transition requires it unless staff explicitly allow missing documents. Not the band score (that is the certificate). _Avoid_: "IELTS document". |
| **Decision** _(Academic)_ | A formal governance document (number, signer, issue/expiry date, scan) that covers one or more Students. It may authorize lifecycle events — a Student Action and/or an EGC progression transition (into `intake_pre_uni_gc` or `intake_course`) — or be purely informational (attached to Students without changing status). Some transitions require an authorizing Decision, which may be attached after the transition; a missing-decision report surfaces those still lacking one. Not a separate status track. |
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
