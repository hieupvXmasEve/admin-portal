# Swinx — Full Codebase Discovery Report

**Date:** 2026-05-12 (Finance source-pointer correction verified 2026-07-15)
**Branch:** dev
**Method:** Standalone discovery. Read-only inspection of `app/`, `resources/`, `routes/`, `database/`, `tests/`, `config/`, `docs/`, root configs, and Docker scaffolding. Cross-referenced existing `docs/system-architecture.md`, `docs/project-overview-pdr.md`, `docs/codebase-summary.md`, `docs/code-standards.md`, and `AGENTS.md`.
**Scope:** Whole repository — depth **high**.

---

## 1. Product

**Swinx** is a multi-role **university operations platform** for Asia University (env var `APP_NAME="Asia University"`). It serves:

- Administrative/staff **web app** (admissions, academic records, scheduling, finance ops, scholarships, notifications).
- **Student** API (`/api/v1/student/*`).
- **Lecturer** API (`/api/v1/lecturer/*`).
- **Parent**-linked proxy access to selected student endpoints.

Core active domains: **Identity**, **Academic**, **Finance** (incl. DNG payment gateway integration), **Notification** (V2 outbox pipeline).

Engineering owner: Platform Team. Stack chosen: Laravel monolith with SPA frontend, optimised for fast in-house iteration over multi-service complexity.

---

## 2. Tech Stack

| Layer | Technology |
|---|---|
| Language (backend) | PHP 8.4 (composer requires `^8.3`) |
| Framework (backend) | **Laravel 13** (`laravel/framework ^13.0`) |
| Language (frontend) | TypeScript 5.2 |
| Framework (frontend) | **Vue 3.5**, **Inertia v3** (`@inertiajs/vue3 ^3.0`, `inertiajs/inertia-laravel 3.x-dev`) |
| Styling | **Tailwind CSS 4** (`@tailwindcss/vite`) |
| UI primitives | **Reka-UI** (shadcn-style, `@/components/ui`), Lucide icons, Phosphor icons |
| Forms (Inertia pages) | `useForm` from `@inertiajs/vue3` |
| Forms (modal/drawer) | `vee-validate` + `Zod` + `useApi`/`useApiRequest` |
| Tables | `@tanstack/vue-table`, `@tanstack/vue-virtual` |
| Editor | TipTap 3 (full extension suite) |
| Charts | `chart.js` + `vue-chartjs` |
| State (client) | Pinia 3 |
| State (server) | Inertia props |
| Real-time | Laravel Echo (`@laravel/echo-vue`), Pusher JS, **Ably** (`ably/ably-php`) |
| Auth | **Laravel Sanctum** + custom Actor middleware; Socialite (Google) |
| Permissions | Custom permission service (`config/permission.php` ~373 lines) + `v-can` directive |
| Toasts | `vue-sonner` |
| QR | `qrcode`, `@zxing/library` (DNG QR payments) |
| Database | MySQL/MariaDB 8 (`DB_CONNECTION=mariadb`), schema in 209 migrations |
| Cache/queue | Redis (`CACHE_STORE=redis`) |
| Web server | **FrankenPHP** (prod), Caddy (dev) |
| Routing helper | Ziggy (`tightenco/ziggy ^2.4`) |
| Excel | `maatwebsite/excel` (heavy import/export pipelines) |
| Activity log | `spatie/laravel-activitylog` |
| Telescope | `laravel/telescope` |
| Dev tools | Laravel Pail, Pint, Sail, Boost, Debugbar |
| Tests | **Pest 4** (PHP), `vue-tsc` + ESLint (frontend) |
| Runtime | **Docker-first** — wrappers via `./scripts/dev.sh` |
| Package mgmt | composer + **pnpm@10.26.0** (`package-lock.json` and `pnpm-lock.yaml` both present — drift) |

### Notable dependency oddities

- `inertiajs/inertia-laravel: "3.x-dev"` — tracking a dev branch, not a tagged release.
- `vite: "^8.0.0"` — pre-release; same with `@vitejs/plugin-vue ^6.0.0`.
- Both `pnpm-lock.yaml` (141K) and `package-lock.json` (227K) committed — `package.json` declares `packageManager: pnpm@10.26.0`, so the npm lockfile is stale.
- `laravel/telescope` and `barryvdh/laravel-debugbar` available in prod dependencies (`require`, not `require-dev`) — only debugbar is in the `dont-discover` list.

---

## 3. Architecture

### 3.1 High-level flow

```
Browser SPA (Vue 3 + Inertia v3)
   │
   ▼
Routes (web/api groups, middleware: auth + actor)
   │
   ▼
Controllers (thin orchestration)
   │
   ├─► Module Actions (app/Modules/*/Actions/*Action.php — write paths)
   ├─► Module Queries (app/Modules/*/Queries/*Query.php — read models)
   └─► Shared Services (app/Services/*.php — legacy/heavy)
                  │
                  ▼
        Eloquent Models (app/Models/*)
                  │
                  ▼
        MySQL/MariaDB  +  Redis (cache/queue)
```

### 3.2 Layering — hybrid modular monolith

The repo is in **active migration** from a service-led architecture to a module-led architecture.

**New** business logic → `app/Modules/{Domain}/`
**Legacy** business logic → `app/Services/*.php`

Four active domain modules:

| Module | Files | Notes |
|---|---|---|
| `app/Modules/Identity` | 46 | Sanctum auth, Web/Api controllers, refresh token rotation, social auth, campus selection. |
| `app/Modules/Academic` | 86 | Academic records, course offerings, retake registration, placement, student decisions, action logs/imports. |
| `app/Modules/Finance` | 92 | Settlement v2 model, invoice/discount/payment_applications, EGC fee management, **DNG payment gateway** (sub-package). |
| `app/Modules/Notification` | 45 | V2 outbox pattern: domain event → outbox → message → delivery. Email + Realtime channel adapters. |

Each module follows:

```
app/Modules/{Domain}/
 ├─ Actions/        # one file per use-case (VerbEntityAction)
 ├─ Queries/        # read models (VerbEntityContextQuery)
 ├─ Http/
 │   ├─ Web/Admin/  # Inertia controllers
 │   ├─ Api/        # JSON API controllers
 │   └─ Requests/   # FormRequest validation
 ├─ Policies/       # optional
 ├─ Providers/      # service provider, route registration
 └─ routes/
     ├─ web.php
     └─ api.php
```

### 3.3 Shared "legacy" layer

`app/Services/` contains **79 service files**. Top-heavy:

| File | Size |
|---|---|
| `AssessmentReportService.php` | **113.5 KB** |
| `AssessmentManagementService.php` | **103.9 KB** |
| `StudentAcademicSummaryService.php` | **66.5 KB** |
| `EventParticipationService.php` | **59.0 KB** |
| `UnitExcelImportService.php` | **49.9 KB** |
| `StudentService.php` | **39.6 KB** |
| `EmailService.php` | **34.0 KB** |
| `FileValidationService.php` | **35.8 KB** |

These ten files alone hold the bulk of legacy business logic that has not yet been migrated to module Actions/Queries. **Each exceeds the 800-line standard documented in `docs/code-standards.md`.**

### 3.4 Cross-module communication contract

`AGENTS.md` mandates:

> Module A needing data from Module B must use a **Contract** in `app/Shared/Contracts/{Domain}/`. Never use Eloquent `join` across module boundaries. Contracts must not accept or return Eloquent Models — use DTOs.

**Reality check:** `app/Shared/` currently contains only `DTO/` and `Support/`. No `Contracts/` subdirectory exists. Contract-style interfaces are presently scoped *inside* modules (e.g. `app/Modules/Notification/Channels/Contracts/`, `app/Modules/Notification/Domain/Contracts/`, `app/Modules/Notification/EmailContent/Contracts/`). The cross-module contract folder is **documentation-only** at the moment.

### 3.5 HTTP / middleware surface

Registered in `bootstrap/app.php`:

```
web group → HandleAppearance, HandleInertiaRequests, AddLinkHeadersForPreloadedAssets, CheckCampusSelected, SetCampus
```

Middleware aliases:

| Alias | Class | Use |
|---|---|---|
| `admin` | `AdminMiddleware` | admin gate |
| `student` | `StudentMiddleware` | student gate |
| `campus.selected` | `CheckCampusSelected` | enforce active campus |
| `student.api.auth` | `StudentApiAuthorization` | student API auth |
| `student.api.rate` | `StudentApiRateLimiter` | student API throttle |
| `lecturer.api.auth` | `LecturerApiAuthorization` | lecturer API auth |
| `lecturer.api.rate` | `LecturerApiRateLimiter` | lecturer API throttle |
| `parent.student.access` | `ParentStudentAccess` | parent proxy access |
| `api.actor` | `ApiActorAuthorize` | actor-based gating (`student_or_parent`, `parent`, `lecturer`) |
| `api.logging` | `ApiLogging` | structured API log capture |
| `either` | `EitherMiddleware` | fallback chain (parent OR student) |

CSRF excluded for `api/webhooks/dng/*`. API exception handler: `App\Exceptions\ApiExceptionHandler`.

### 3.6 Auth / API security baseline

| Surface | Middleware chain |
|---|---|
| Student API v1 | `auth:sanctum`, `api.logging`, `api.actor:student_or_parent` (with `either:parent.student.access,student.api.auth` branch) |
| Lecturer API v1 | `auth:sanctum`, `api.actor:lecturer`, `lecturer.api.auth`, `api.logging` |
| Parent | `auth:sanctum`, `api.logging`, `api.actor:parent` |
| Web | session-based, Inertia |
| **Drift — `/api/system-config*`** | **NO AUTH** (GET/PUT/POST/upload public) |
| **Drift — Finance module API** | `web` + `auth` (inconsistent with Sanctum + actor used elsewhere) |

Identity tokens: standardized **8-hour** TTL. Refresh pattern: issue new token, then revoke old.

### 3.7 Notification V2 outbox

```
Domain event
   → EventIntentMapper → PolicyResolver → PersistIntentAction
   → notification_event_outbox table
   → notifications:process-outbox (cron every 5 min, push via DispatchSingleOutboxEventJob)
   → notification_messages (canonical, keyed by recipient_user_id)
   → notification_deliveries (per-channel)
   → SendNotificationDeliveryJob (email / realtime)
```

Channels: `EmailChannelAdapter`, `RealtimeChannelAdapter`, `RenderedEmailChannelAdapter`. Campus isolation enforced in `RecipientResolver`. Phase 1 is clean-slate — no legacy `notifications` backfill.

### 3.8 Finance settlement v2

Canonical tables: `payments`, `payment_applications` (line-level cash, replaces legacy `payment_allocations`), `invoice_lines` (with `status` active/void), `invoice_discounts`, `discount_allocations` (line-level discount truth), `student_invoices` (snapshot cache only).

Services: `SettlementService`, `PaymentService`, `FinanceChargeService`, `InvoiceGenerationService`, `DeferCaseService`, `DeferChargeResolver`.
Actions: `AllocatePaymentAction`, `AutoAllocatePaymentsAction`, `VoidFinanceChargeAction`, `CreateFinanceChargeAction`, `RequestFinanceDebitAction`.
EGC sub-system: `egc_blocks`, `egc_retake_discount_links`, plus `SyncEgcBlockResultsAction`, `ApplyEgcRetakeDiscountAction`, `ApplyEgcCarryForwardAction`, `BuildEgcCarryForwardPlanAction`, `GenerateEgcChargesAction`, paired Query classes, and Support `StudentChargeTimingResolver`.
**DNG gateway** (`app/Modules/Finance/Dng/`): own Http/Jobs/Models/Services/Support, webhook inbox-first flow, HMAC checksum, replacement rule for unpaid requests, reconciliation job every 15 min.

### 3.9 Academic progression

`academic_progression_events` table = semantic audit/event store:
- `ENGLISH_LEVEL_CHANGED` — EGC level changes (manual + auto progression).
- `COURSE_STAGE_CHANGED` — stage transitions (e.g., `intake_pre_uni_gc` → `intake_course`).

Triggers `PublishCourseStageChangedNotificationAction` → V2 outbox.
EGC → major transition writes `students.status = intake_course` + `students.intake_major = <semester>` (canonical billing milestone). Deprecated: `students.gc_to_course_transition_semester`.

### 3.10 Scheduled commands (`routes/console.php`, `onOneServer()`)

| Command | Frequency | Purpose |
|---|---|---|
| `notifications:process-outbox --limit=100` | every 5 min | Safety-net outbox dispatch |
| `sessions:update-statuses` | every 30 min | Class session lifecycle + auto-attendance |
| `events:process-completions` | hourly | Event completion |
| `events:send-reminders` | daily 00:10 | Event reminders |
| `events:process-failed-gold-rewards` | daily 02:00 | Retry gold rewards |
| `academic-records:sync` | daily 03:00 | Canvas LMS sync |
| `attendance:sync-to-academic-records` | every 2 hrs | Attendance → academic record rollup |
| `academic-records:aggregate-manual` | daily 04:00 | Manual grade aggregation |
| `ReconcileDngPaymentsJob` | every 15 min | DNG payment reconciliation |

32 total artisan commands in `app/Console/Commands/` (academic, finance backfills, EGC backfill, email cleanup, parent data cleanup, survey migration, etc.).

### 3.11 Frontend architecture

Entry: `resources/js/app.ts` → `createInertiaApp` with:
- Pinia plugin
- Ziggy route helper
- `withInertiaModal` (`@inertiaui/modal-vue` for modal-as-route)
- `putConfig({ useNativeDialog: false, modal/slideover closeOnClickOutside: false })`
- Permission directives `v-can`, `v-can-any`
- Echo setup (`lib/echo`)
- `AppLayout` auto-bound except for `auth/Login` and `SelectCampus`

SSR entry: `resources/js/ssr.ts`.

`HandleInertiaRequests::share()` exposes: `name`, `quote`, `auth.{user, permissions, current_campus_id, current_campus}`, `ziggy`, `sidebarOpen`, `flash.{success, error, warning, info, message, batch_errors, conversion_summary, success_details}`.

Vite config: alias `@` → `resources/js`, alias `ziggy-js` → `vendor/tightenco/ziggy`. HMR host `localhost:5173`. Dev CORS allows `localhost:8000`, `127.0.0.1:8000`, plus LAN ranges `192.168.*` / `10.*`.

---

## 4. Folder Structure

### 4.1 Repository root

```
/
├── app/                   # PHP application (modules + legacy services)
├── bootstrap/             # Laravel bootstrap
├── config/                # 21 config files
├── database/
│   ├── migrations/        # 209 migration files (148 from 2025, 58 from 2026)
│   ├── factories/
│   ├── seeders/
│   └── schema/            # currently empty
├── docker/                # Dockerfile, compose, Caddy configs
├── docs/                  # canonical engineering docs (102+ files)
├── public/                # web root
├── resources/
│   ├── css/               # app.css, tiptap.css, theme tokens
│   ├── js/                # Vue SPA
│   └── views/             # Blade root (app.blade.php)
├── routes/                # web.php, api.php + split files
├── scripts/               # dev/prod/deploy wrappers
├── storage/               # logs, framework cache, uploads
├── tests/                 # Pest tests
├── vendor/                # composer dependencies (gitignored)
├── node_modules/          # pnpm dependencies (gitignored)
├── .codex/ .cursor/ .opencode/ .trellis/ .vscode/
│                          # AI tool configs; Harness in AGENTS.md is canonical
├── history/               # historical investigation notes, not a live workflow
├── plans/ openspec/ prd/ requirements-docs/ specs/ stories/
│                          # planning/spec artifacts (mostly empty in dev branch)
├── custom-skills/         # in-repo Claude skills (e.g. inertia-filter-table)
├── repomix-output.xml     # 13.4 MB — repomix bundle checked in
├── release-manifest.json  # 501 KB
└── 404.html / 502.html    # ~57 KB each (Caddy error pages)
```

### 4.2 `app/` deep map

```
app/
├── Console/Commands/      # 32 artisan commands (academic, finance, EGC backfills, email cleanup)
├── Constants/
├── Enums/                 # AcademicProgressionEventType, NotificationCategory, ProgressionTriggerSource, StudentActionType
├── Events/                # 6 events (AcademicHoldPlaced, AssessmentDeadlineApproaching, ...)
├── Exceptions/            # ApiExceptionHandler + others
├── Exports/               # Maatwebsite/Excel exports
├── Helpers/               # PermissionHelper, RoutePermissionHelper, global_helpers.php
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   ├── Api/           # legacy API controllers (admin endpoints, V1 admin)
│   │   └── Web/           # legacy web (Inertia) controllers
│   ├── Middleware/        # 17 custom middleware
│   ├── Requests/          # shared FormRequest classes
│   ├── Resources/
│   └── Responses/
│       └── ApiResponse.php  # mandatory API envelope (success / error / paginated / etc.)
├── Imports/               # Excel imports
├── Jobs/                  # 5+ queue jobs (email, notification, cleanup)
├── Listeners/             # event listeners
├── Mail/                  # Mailables
├── Models/                # 104 Eloquent models
├── Modules/
│   ├── Academic/          # see §3.2
│   ├── Finance/
│   ├── Identity/
│   └── Notification/
├── Notifications/         # Laravel Notification classes
├── Policies/              # ApiActorPolicy + a few others (most still inferred from gates)
├── Providers/             # AppServiceProvider, EventServiceProvider, PermissionServiceProvider, TelescopeServiceProvider
├── Queries/               # only Form/ subfolder — most queries now in Modules/
├── Repositories/V1/       # legacy V1 repositories
├── Services/              # 79 legacy/shared services (top-heavy, see §3.3)
│   └── V1/                # Lecturer/, Student/ — versioned API support services
├── Shared/
│   ├── DTO/               # SocialUserData (one file)
│   └── Support/           # Enums/UserType
├── Support/               # BusinessActionLogger, CampusLogContext, Attendance/
└── Traits/                # HasNotifications, LazyPermissions
```

### 4.3 `resources/js/` deep map

```
resources/js/
├── app.ts                 # SPA entry
├── ssr.ts                 # SSR entry
├── ziggy.js               # generated route table (98 KB)
├── pages/                 # Inertia pages — one folder per domain (~40 top-level)
│   ├── Academic/ Admin/ Buildings/ Campuses/ CourseStatistics/ Events/ FailedStudents/
│   ├── Finance/ Forms/ Scholarships/ StudentScholarships/ Surveys/ SystemConfig/ TuitionPlans/ Users/ Vouchers/
│   ├── attendance/ auth/ class-schedule/ class-sessions/ clubs/
│   ├── course-offerings/ course-registrations/ curriculum-units/ curriculum-versions/
│   ├── dashboard/ lectures/ programs/ roles/ room-bookings/ rooms/
│   ├── semesters/ settings/ specializations/ student-applications/ students/
│   ├── syllabus/ systems/ units/
│   ├── ModuleProgress.vue
│   └── SelectCampus.vue
├── components/
│   ├── Admin/ Events/ Wallet/
│   ├── curriculum/ dashboard/ filters/ finance/ form-engine/ forms/
│   ├── imports/ schedule/ student/ tables/
│   ├── ui/                # Reka-UI primitives, shadcn-style
│   └── (top-level Vue: AppHeader, AppLogo, AppLogoIcon, AppShell, GlobalConfirmDialog, AddClassSessionModal, ...)
├── composables/           # 30+ composables (useDataTable, useApiRequest, useInertiaFilters,
│                          #  useFilters, useServerTableQuery, useImageUpload, useErrorHandler,
│                          #  useFlashToast, useAdminSchedule, useQRScanner, ...)
├── constants/
├── directives/permission.ts  # v-can, v-can-any
├── layouts/
│   ├── AppLayout.vue
│   ├── AuthLayout.vue
│   ├── CurriculumVersionSummaryLayout.vue
│   ├── StudentLayout.vue
│   ├── app/ auth/ settings/
├── lib/                   # echo setup, helpers
├── schemas/               # Zod schemas for vee-validate forms
├── stores/confirmDialog.ts  # Pinia stores
├── types/                 # TS interfaces — models.ts (43 KB), forms.ts, finance.ts, ...
└── utils/
```

### 4.4 `routes/` map

```
routes/
├── api.php                # broadcasting routes, system-config (PUBLIC), DNG webhook, health, modules, v1
├── api/
│   ├── admin.php          # 11.8 KB — admin auth + V1 admin endpoints
│   ├── modules.php
│   ├── uploads.php
│   └── v1/
│       ├── student.php    # 18.2 KB
│       └── lecturer.php   # 10.1 KB
├── web.php                # core web entry + 35 split files in routes/web/*
├── web/                   # academic.php attendance.php auth.php canvas.php class-schedule.php
│                          # class-sessions.php clubs.php course-offerings.php course-registrations.php
│                          # course-statistics.php curriculum.php departments.php email-monitoring.php
│                          # events.php failed-students.php forms.php lectures.php modules.php
│                          # notifications.php programs.php role.php room-bookings.php rooms.php
│                          # scholarships.php semester.php settings.php specializations.php
│                          # student-application.php student-scholarships.php surveys.php
│                          # syllabus-templates.php systems.php tuition-plans.php units.php vouchers.php
├── channels.php
└── console.php            # scheduled commands (see §3.10)
```

Plus per-module routes loaded by each module's ServiceProvider:
- `app/Modules/Academic/routes/{web.php (14.5K), api.php (1.8K)}`
- `app/Modules/Finance/routes/{web.php (9.8K), api.php (3.3K)}`
- `app/Modules/Identity/routes/{web.php (2.2K)}` and Identity API routes

### 4.5 `database/` map

- **209 migrations** total
  - 148 from 2025 (early structural migrations)
  - 58 from 2026 (recent: settlement v2, EGC blocks, DNG payment requests, notification outbox, academic progression events, retake course registrations, GPA refactor, payment_applications)
- Latest migration: `2026_05_11_170000_change_gpa_calculations_finalized_by_id_to_users.php`
- `database/schema/` is empty (no schema dump generated).

### 4.6 `tests/` map

```
tests/
├── Pest.php
├── TestCase.php
├── Feature/      # 53 PHP test files
│   ├── Academic/ Api/ Console/ Feature/ Finance/ Modules/ Notification/
└── Unit/         # 7 PHP test files
    └── Finance/ Modules/ Notification/
```

**60 test files vs 833 PHP source files in `app/`** — coverage looks thin relative to size. `phpunit.xml` enables `Pest` (`vendor/bin/pest`) with `DB_CONNECTION=testing`, in-memory cache, sync queue.

### 4.7 `docs/` map

```
docs/
├── ai-context.md                 # quick-start for AI agents
├── README.md
├── project-overview-pdr.md       # PDR baseline
├── system-architecture.md        # canonical architecture map
├── codebase-summary.md           # repomix snapshot summary
├── code-standards.md             # implementation rules
├── design-guidelines.md          # frontend standards
├── deployment-guide.md           # FrankenPHP, queue workers, scheduler
├── project-roadmap.md
├── inertiajs-vue-info.md         # 209 KB — Inertia v3 reference (must-read)
├── inertiajs-vue-code.md
├── db-flow.md                    # 34 KB
├── student-finance-api.md        # 24 KB
├── useDataTable-examples.md      # 16.9 KB — canonical pattern
├── RULES_vue-form-useApi.md
├── excel-export-service.md, excel-memory-optimization.md
├── ARCHITECTURE.md, FEATURE_INTAKE.md, GLOSSARY.md, HARNESS.md, HARNESS_BACKLOG.md, TEST_MATRIX.md
├── api/ decisions/ features/ plans/ product/ rules/ stories/ templates/ ui/
└── (rules/ holds: api-interaction, architecture, backend, code-style, contracts,
    filtering, frontend, frontend-gotchas, frozen-zones, laravel-boost, migration,
    naming, pattern, realtime, realtime_notification, refactor-playbook,
    reference-implementations, security, shadcn_vue_conventions, structure, tech, testing,
    toast-patterns)
```

---

## 5. Entry Points

### 5.1 How to run dev

```bash
# Local clone
composer install
pnpm install                                 # NOT npm — package.json declares pnpm 10.26
cp .env.example .env

# Docker stack (preferred — and mandated by AGENTS.md)
./scripts/dev.sh start                        # build + up swinx-dev stack
./scripts/dev.sh artisan key:generate
./scripts/dev.sh artisan migrate
./scripts/dev.sh npm run dev                  # vite dev server in container

# Direct (non-Docker — discouraged by repo conventions, but composer script exists)
composer dev                                  # spawns: serve + queue:listen + pail + vite
```

`./scripts/dev.sh` wraps `docker compose -f docker/docker-compose.dev.yml -p swinx-dev` and exposes:
`start | stop | restart | rebuild | logs | status | shell | mysql | artisan | composer | npm | pnpm | test`.

Default ports (from `.env.example`): App **8000**, Vite **5173**, Mailpit HTTP **8026**, MariaDB **3306**, Redis default.

### 5.2 How to build

```bash
./scripts/dev.sh npm run build                # vite build
./scripts/dev.sh npm run build:ssr            # vite build + SSR
```

### 5.3 How to test

```bash
./scripts/dev.sh test                          # artisan test → Pest 4
./scripts/dev.sh npm run type-check            # vue-tsc --noEmit
./scripts/dev.sh npm run lint                  # eslint . --fix
./scripts/dev.sh npm run format:check          # prettier --check resources/
./scripts/dev.sh artisan pint                  # PHP code style
```

`composer ci` chains: `pint --test` → `pest` → `lint` → `format:check` → `type-check`.

`composer pre-push` invokes `./scripts/pre-push.sh`.

### 5.4 Health endpoints (in code)

- `GET /up` (Laravel health endpoint)
- `GET /health` (custom JSON)
- `GET /api/health` (custom JSON, `api.health` named route)

### 5.5 Backend bootstrap chain

`public/index.php` → `bootstrap/app.php` → routes `web.php` + `api.php` → middleware groups → controllers/Inertia.

### 5.6 Frontend bootstrap chain

`resources/views/app.blade.php` (Blade root) → Vite asset injection → `resources/js/app.ts` (SPA) or `resources/js/ssr.ts` (SSR).

---

## 6. Hot Spots, Tech Debt & Risks

Severity: **C** = critical, **H** = high, **M** = medium, **L** = low.

### Security

| ID | Sev | Item | Evidence |
|---|---|---|---|
| S1 | **C** | Public `/api/system-config*` endpoints (GET/PUT/POST/upload) with no auth middleware. Configuration tampering / exfiltration risk. | `routes/api.php:24-27` |
| S2 | H | Finance module API uses `web` + `auth` instead of `auth:sanctum` + actor model — auth drift across surfaces. | `app/Modules/Finance/routes/api.php` (per docs) |
| S3 | H | `.env.example` ships with a real-looking `APP_KEY=base64:9NX5UrR...` value — confusing during onboarding (someone may treat it as a working key). | `.env.example:14` |
| S4 | M | Hardcoded MariaDB credentials default (`swinx/swinx`, root password `root`) in `.env.example` — mirrored in production compose per docs (`Known Operational Risks`). | `.env.example`, `README.md` |
| S5 | M | `laravel/telescope` is in `require` (production) — must be guarded by env / middleware in prod. | `composer.json:19` |
| S6 | M | DNG webhook CSRF excluded — must rely on checksum + replacement rule discipline. (Documented behavior, but no defence-in-depth above that.) | `bootstrap/app.php:27` |

### Architecture / maintainability

| ID | Sev | Item | Evidence |
|---|---|---|---|
| A1 | **C** | Doc-vs-code drift: `app/Shared/Contracts/{Domain}/` is mandated for cross-module data but **does not exist**. Only `app/Shared/DTO/` and `app/Shared/Support/`. Modules currently host their own internal Contracts. | `app/Shared/` actual listing vs `AGENTS.md` |
| A2 | H | Mega-services exceed the **800-line standard** by an order of magnitude. `AssessmentReportService.php` is 113 KB, `AssessmentManagementService.php` 103 KB, `StudentAcademicSummaryService.php` 66 KB. | `app/Services/*.php` |
| A3 | H | Frontend filter-stack drift: pages use mix of `useInertiaFilters`, `useFilters`/`useTableFilters`, `useServerTableQuery`, and the newly-canonical `useDataTable`. Code-standards calls `useDataTable` the only correct path; the other three remain in active use. | `resources/js/composables/`, `docs/code-standards.md` |
| A4 | M | 35 split files in `routes/web/*` plus per-module routes — registration is mostly OK but the **legacy `routes/web/student.php` is commented out and references say to remove campus routes after 2026-06-01** (deprecation deadline imminent). | `routes/web.php:62-79` |
| A5 | M | Two parallel lockfiles: `pnpm-lock.yaml` (live) + `package-lock.json` (stale, 227 KB). Risk: drift / confusion. | repo root |
| A6 | M | `inertiajs/inertia-laravel: 3.x-dev` — tracking a dev branch, not a release tag. Updates may break behaviour silently. | `composer.json:15` |
| A7 | M | `vite ^8.0.0` and `@vitejs/plugin-vue ^6.0.0` — pre-release / very recent majors. | `package.json:67,89` |
| A8 | L | Top-level `app/Queries/` only contains `Form/` — most query classes have already moved into modules, but the legacy folder is not yet retired. | `app/Queries/` |
| A9 | L | Inconsistent versioned namespaces: `App\Services\V1\{Lecturer,Student}\*` + `App\Repositories\V1\*` mixed with module-based modern code. Versioned legacy is an active migration target. | `app/Services/V1/`, `app/Repositories/V1/` |
| A10 | L | 13.4 MB `repomix-output.xml` checked in (regenerable artifact). Inflates clones and diffs. | `repomix-output.xml` |
| A11 | L | `release-manifest.json` (501 KB) and 57 KB static error HTML files checked in at the repo root — consider moving under `public/` or asset pipeline. | repo root |

### Delivery / ops

| ID | Sev | Item | Evidence |
|---|---|---|---|
| D1 | **C** | **All 3 GitHub Actions workflows are fully commented out** (`name:` is `# name:`). No CI-enforced merge gates. Quality relies on local discipline alone. | `.github/workflows/{tests,lint,deploy}.yml` first non-blank line is `# name:` |
| D2 | H | Deploy script drift — `scripts/deploy.sh` (62 B), `scripts/deploy-production.sh` (62 B), `scripts/prod.sh` (2.7 KB), `scripts/local-prod.sh`, `scripts/server-setup.sh`. Three overlapping deploy entrypoints; canonical is unresolved. | `scripts/`, `docs/system-architecture.md §9` |
| D3 | M | Compose path drift — `dev.sh` correctly points to `docker/docker-compose.dev.yml`, but other scripts referenced in docs may still reference root-level filenames. | per `README.md` operational risks |
| D4 | M | Setup/validate scripts (`setup-project.sh`, `validate-setup.sh`) reference helpers not in repo (`docker-compose-dev.sh`, `test-local.sh`). | per docs |
| D5 | M | Test coverage thin: 53 feature + 7 unit tests vs 833 PHP files; `docs/code-standards.md §9` lists tests as a release-risk indicator while CI is disabled. | `tests/` count |

### Migration debt

| ID | Sev | Item | Evidence |
|---|---|---|---|
| M1 | M | Legacy `payment_allocations` table no longer used in runtime but still in DB schema. Backfill commands (voucher/scholarship → `invoice_discounts`+`discount_allocations`) exist but full convergence isn't documented as complete. | docs / migrations |
| M2 | M | `students.gc_to_course_transition_semester` deprecated — runtime now reads `students.intake_major`. Old column still present. | `docs/system-architecture.md` |
| M3 | M | Phase 1 Notification V2 is clean-slate; **legacy `notifications` table history is not migrated**. Cutover/backfill plan beyond Phase 1 is an open decision in PDR. | `docs/system-architecture.md §11`, `docs/project-overview-pdr.md` |
| M4 | L | "Review code and optimize" inline comment left in `routes/web.php:61` next to deprecated requires. | `routes/web.php` |
| M5 | L | 28 occurrences of `TODO`, `FIXME`, `@deprecated`, or `@todo` across `app/`. Modest debt marker. | grep `app/` |

### Frontend specific

| ID | Sev | Item | Evidence |
|---|---|---|---|
| F1 | M | Literal-URL pockets still present in some pages (`router.visit('/...')`, direct `/api/...`) even though `route(...)` helpers are dominant (~523 matches per existing docs). Refactor risk during route renames. | `docs/codebase-summary.md §5` |
| F2 | M | `inertiajs-vue-info.md` is **209 KB** of reference material — useful, but indicates significant Inertia v2 → v3 migration context still needed by contributors. | `docs/inertiajs-vue-info.md` |
| F3 | L | `pinia` listed as `^3.0.4` but only `stores/confirmDialog.ts` exists. Pinia is barely used — most state still flows through Inertia props. | `resources/js/stores/` |

---

## 7. Conventions

### 7.1 Backend

- **Strict types**: `declare(strict_types=1)` used in newer code (e.g. `app/Http/Responses/ApiResponse.php`, `routes/api.php`). Not yet repo-wide.
- **Classes**: PascalCase. **Action** = `VerbEntityAction` with `static run(array $data)`. **Query** = `VerbEntityContextQuery` with `handle(...$args)`.
- **Controllers**: thin orchestration only — validate via FormRequest → call Action/Query → return response. **No** inline validation, **no** heavy business logic.
- **API responses**: must use `ApiResponse::success() / error() / paginated() / validationError() / authenticationError() / authorizationError() / notFound() / serverError() / businessLogicError() / rateLimitError()`. Envelope is `{ success, timestamp, data?, meta?, message?, errors? }`. **Never** `response()->json([...])` directly.
- **Transactions**: `DB::transaction()` for any multi-write flow.
- **Validation**: `FormRequest` per use-case, not inline.
- **Auth**: routes with no `{id}` → Gate check; routes with `{id}` → Policy check (`$this->authorize('action', $model)`).
- **Cross-module data**: must go through Contract in `app/Shared/Contracts/{Domain}/`. No Eloquent joins across modules. (Reality: contract folder doesn't exist yet — see A1.)
- **Campus scoping**: explicit where data is campus-bound. `session('current_campus_id')` is the source for current campus context.

### 7.2 Frontend

- **All new components**: `<script setup lang="ts">`.
- **Inertia page forms**: `useForm` from `@inertiajs/vue3`. NEVER vee-validate on full-page forms.
- **Modal/drawer non-navigating forms**: vee-validate + Zod + `useApi`/`useApiRequest`.
- **Filter/pagination pages**: `useDataTable` composable (`@/composables/useDataTable`). `useInertiaFilters`, `useFilters`, `useTableFilters`, `useServerTableQuery` are legacy/transitional.
- **Routes in frontend**: `route('foo.bar', params)` from Ziggy. NEVER literal URLs.
- **Icons**: `lucide-vue-next`. Phosphor (`@phosphor-icons/vue`) used in some places too.
- **UI primitives**: `@/components/ui` (Reka-UI).
- **Reusable filter components**: `resources/js/components/filters/{FilterPanel, FilterSearchInput, FilterDateRange, FilterSelect}.vue` — composed inside `FilterPanel`.
- **DatePicker / TimePicker**: must use `@/components/ui` versions, NOT `<Input type="date">`. In modal, pass `:portal-to="modalContentRef ?? undefined"`.
- **useNativeDialog: false** is required globally (set in `app.ts`) so Select / Combobox dropdowns escape modal stacking.

### 7.3 Inertia v3 specifics (mandatory — many v2 APIs removed)

| Backend | Frontend |
|---|---|
| `Inertia::defer(fn () => ...)` (NOT `lazy()`) | `<Deferred data="scoresData">` (string, NOT `:data="['x']"`) |
| `Inertia::once(fn () => ...)` for one-shot props | `<Deferred>` slots: `#fallback`, `#default="{ reloading }"` |
| `Inertia::flash('success', 'msg')` (NOT `->with('success', ...)`) | `usePage().props.flash` (NOT `$page.props.flash`) |
| Snake_case prop names from Laravel (e.g. `class_sessions`) | TS interfaces must also use **snake_case** to match payload |

### 7.4 Naming

| Concept | Format | Example |
|---|---|---|
| Module | PascalCase singular | `Academic`, `Finance`, `Identity`, `Notification` |
| Action | `VerbEntityAction` | `CreateEventAction`, `AllocatePaymentAction` |
| Query | `VerbEntityContextQuery` | `ListAcademicRecordsQuery` |
| Controller (Web) | `Http/Web/Admin/{Entity}Controller` | — |
| Controller (API) | `Http/Api/{Actor}/{Entity}Controller` | `StudentAuthController` |
| Contract | `{Noun}{Verb}{Purpose}` | `StudentAcademicReader` |
| Vue component | PascalCase | `RecordTable.vue` |
| Composable | `useXxx.ts` | `useInertiaFilters.ts` |
| DB table / column | snake_case | `course_offerings`, `recipient_user_id` |
| Route name | dot notation | `academic.records.index` |
| URL segment | kebab-case | `/api/v1/student/parent/auth/refresh` |

### 7.5 Standards-mandated exact field names

For Academic action logs / imports:
`decision_number`, `decision_signed_at`, `decision_signer`, `decision_id`, `missing_documents`, `shared_upload_record_id`.

For Notification V2 Phase 1:
`notification_event_outbox.campus_id`, `notification_messages.recipient_user_id`, `notification_messages.campus_id`.

### 7.6 Documentation discipline (per `docs/code-standards.md §8`)

Every core doc requires: **last updated date, owner, status, unresolved questions section**. Keep under 800 LOC. Remove stale claims quickly.

### 7.7 Quality gates

Currently — **local-only** (CI disabled). Minimum expected before merge:
```bash
./scripts/dev.sh test
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
```

`composer ci` runs the full chain. If a check cannot be run, **record the gap in the change summary**.

---

## 8. Numbers at a glance

| Metric | Value |
|---|---|
| Total tests | 60 (53 Feature + 7 Unit) |
| PHP files in `app/` | 833 |
| Files in `resources/` | 745 |
| Files in `database/` | 235 |
| Migrations | 209 |
| Files in `docs/` | 102+ |
| Files in `routes/` | 49 |
| Files in `config/` | 21 |
| Files in `scripts/` | 12 |
| Eloquent models | 104 |
| Legacy services (`app/Services/*.php`) | 79 |
| Artisan commands | 32 |
| Module count | 4 (Identity, Academic, Finance, Notification) |
| Middleware aliases | 11 |
| Largest service | 113.5 KB |
| Largest controller | `app/Http/Controllers/Api/V1/Lecturer/AssessmentController.php` (>30 KB) |
| Repomix output (regenerable) | 13.4 MB |
| Token count last repomix run | 3,151,945 |

---

## 9. Unresolved Questions

These should be answered before deeper changes touching the affected areas:

1. **Auth uniformity** — should `/api/system-config*` move behind admin auth, and should Finance API migrate to `auth:sanctum` + actor?
2. **`app/Shared/Contracts/{Domain}/`** — when does this directory get created and which existing internal-module Contracts get promoted into it?
3. **Mega-service decomposition** — which of the 113K/103K/66K services is the next refactor target, and along what seams (per-entity, per-use-case, per-export)?
4. **Filter-stack convergence** — concrete deadline to migrate all pages off `useInertiaFilters` / `useServerTableQuery` to `useDataTable`?
5. **CI reactivation** — which checks become hard merge blockers when GitHub Actions are re-enabled (Pint, Pest, lint, type-check, build)?
6. **Deploy entrypoint** — which of `scripts/{prod.sh, deploy.sh, deploy-production.sh}` is canonical?
7. **Notification V2 cutover** — plan beyond Phase 1 clean-slate; does legacy `notifications` history get backfilled or sunset?
8. **`payment_allocations` table** — schedule for drop migration once all read paths converge on `payment_applications`?
9. **`students.gc_to_course_transition_semester`** — schedule for drop migration?
10. **`repomix-output.xml` checked in** — keep regenerable artifact in git, or `.gitignore` it?
11. **Lockfile drift** — remove `package-lock.json`?
12. **`inertiajs/inertia-laravel: 3.x-dev`** — pin to a tagged release once one is published?
13. **`run` vs `execute` Action method naming** — standardize repo-wide (per `code-standards.md`)?
14. **`EitherMiddleware`** — replace with explicit route group middleware composition (per PDR)?

---

## 10. Onward Pointers

- Quickest orientation for new contributors: `docs/ai-context.md` → `README.md` → this report → `docs/system-architecture.md` → `docs/code-standards.md`.
- For frontend tasks: `docs/inertiajs-vue-info.md` (Inertia v3 reference) + `docs/useDataTable-examples.md` + `.devin/skills/swinx-frontend/`.
- For backend tasks: `docs/rules/{architecture,backend,naming,pattern,contracts}.md`.
- Canonical filter/table pattern: `custom-skills/inertia-filter-table/SKILL.md`.
- Notification V2 module: `docs/features/notification/README.md`.
- Finance settlement v2: `docs/features/finance/tuition-settlement-model-v2.md`.

---

*Report generated 2026-05-12. Source of truth is the code at the time of writing. Re-run discovery after major migrations, especially after CI reactivation, contract folder creation, or finance/notification cutover phases.*
