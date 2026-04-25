# AI Context — Swinx Quick-Start

Last updated: 2026-04-26
Status: Active baseline — includes Frozen Zones + Refactor guidance

This file is the fastest way to orient any AI tool before writing code.
Read this first, then read the rule files relevant to your task.

---

## Project in one sentence

Swinx is a multi-role university operations platform: Laravel 13 backend (PHP 8.4) + Vue 3 + Inertia frontend, running in Docker.

## Tech stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.4, MySQL 8, Redis |
| Web server | FrankenPHP |
| Frontend | Vue 3, TypeScript, Inertia v3, Tailwind CSS 4 |
| UI components | Reka-UI (shadcn-style), `@/components/ui` |
| Forms (Inertia pages) | `useForm` from `@inertiajs/vue3` |
| Forms (modal/drawer) | vee-validate + Zod + `useApi` |
| Icons | `lucide-vue-next` |
| State | Pinia (global), Inertia props (server state) |
| Auth | Laravel Sanctum + actor middleware |
| Tests | Pest 4 (PHP), vue-tsc + ESLint (frontend) |
| Runtime | Docker — always use `./scripts/dev.sh` wrappers |

---

## What to read before writing code

### Backend task (PHP/Laravel)

```
docs/rules/backend.md       — controllers, actions, queries
docs/rules/architecture.md  — module structure, boundaries
docs/rules/naming.md        — naming format for all types
docs/rules/contracts.md     — cross-module communication
docs/rules/pattern.md       — step-by-step module implementation
docs/rules/security.md      — gates vs policies
docs/rules/migration.md     — moving legacy code into modules
```

### Frontend task (Vue/TypeScript)

```
docs/rules/frontend.md              — components, forms, naming, composable decision rules
docs/rules/filtering.md             — filter/pagination pattern details
docs/rules/api-interaction.md       — ApiResponse + useApi patterns
docs/rules/naming.md                — naming format for components/composables
docs/rules/shadcn_vue_conventions.md — Reka-UI/Shadcn component rules
docs/rules/reference-implementations.md — copy-ready Vue patterns (list page, form, routes)
```

### Cross-cutting (any task touching both layers)

```
docs/rules/contracts.md      — module contracts (DTOs, interfaces)
docs/rules/realtime.md       — broadcasting rules
docs/rules/security.md       — auth/authorization rules
docs/code-standards.md       — overall quality gates
docs/system-architecture.md  — full architecture map
```

---

## Top 9 mistakes AI tools make here

### 1. Business logic in the wrong place

```php
// WRONG — creates new Service for new feature
class NewFeatureService { ... }
app/Services/NewFeatureService.php

// CORRECT — new domain logic belongs in a Module Action
app/Modules/Academic/Actions/CreateCourseOfferingAction.php
// Entry point: public static function run(array $data): mixed
```

### 2. Wrong form handling on Inertia pages

```typescript
// WRONG — vee-validate on a full Inertia page form (navigates on submit)
import { useForm } from 'vee-validate'  // ← wrong import source

// CORRECT — Inertia's own useForm for page-level submissions
import { useForm } from '@inertiajs/vue3'
const form = useForm({ name: '', code: '' })
form.post(route('entity.store'))

// vee-validate + Zod is ONLY for modal/drawer forms that don't navigate
```

### 3. Raw JSON response instead of ApiResponse wrapper

```php
// WRONG
return response()->json(['data' => $data]);

// CORRECT
return ApiResponse::success($data);
return ApiResponse::error($message, $errors, 422);
```

### 4. Direct cross-module Eloquent query

```php
// WRONG — Finance module querying Academic table directly
$gpa = AcademicRecord::where('student_id', $id)->avg('grade_points');

// CORRECT — use a Contract
$gpa = $this->academicReader->getStudentGpa($studentId);
// Contract: app/Shared/Contracts/Academic/StudentAcademicReader.php
```

### 5. Writing to deprecated finance table

```php
// WRONG — legacy table, no longer used
PaymentAllocation::create([...]);

// CORRECT — current canonical table
PaymentApplication::create([...]);
```

### 6. Running commands directly instead of through Docker

```bash
# WRONG
php artisan migrate
composer install
npm run dev

# CORRECT
./scripts/dev.sh artisan migrate
./scripts/dev.sh composer install
./scripts/dev.sh npm run dev
```

### 7. Literal URL paths instead of route helpers

```typescript
// WRONG
router.visit('/academic/records')
axios.get('/api/v1/student/courses')

// CORRECT
router.visit(route('academic.records.index'))
api.get(route('api.student.courses.index'))
```

### 8. Using legacy list page composable for new pages

```typescript
// WRONG — useInertiaFilters is legacy (auto-sync behavior, old component set)
import { useInertiaFilters } from '@/composables/useInertiaFilters'
import DataTable from '@/components/DataTable.vue'
import DataPagination from '@/components/DataPagination.vue'

// CORRECT — useDataTable is the current standard for list/index pages
import { useDataTable } from '@/composables/useDataTable'
// Reference: docs/useDataTable-examples.md
```

### 9. Inventing schema fields without verification

```php
// WRONG — guessing that a column or relationship exists
$student->academic_level   // column may not exist
$course->lecturer_name     // may not be a direct column

// CORRECT — verify in migration files or model $fillable before using
```

---

## Active modules

| Module | Path | Purpose |
|---|---|---|
| Identity | `app/Modules/Identity/` | Auth, user management |
| Academic | `app/Modules/Academic/` | Curriculum, courses, grades, enrollment |
| Finance | `app/Modules/Finance/` | Invoices, payments, settlement (v2) |
| Notification | `app/Modules/Notification/` | Domain event outbox + delivery |

Shared contracts: `app/Shared/Contracts/{Domain}/`
Shared models: `app/Models/` (do not move into modules yet)

---

## Codebase split — Legacy vs Greenfield

This project is in active migration. Two architectures coexist.

### Frozen Zones (Legacy — bug fix only, no new code)

| Directory | Status | Why frozen |
|---|---|---|
| `app/Services/` (107 files) | FROZEN | Replaced by Module Actions/Queries |
| `app/Http/Controllers/` (124 files) | FROZEN | Replaced by Module Http controllers |
| `routes/web/*.php` (top-level) | FROZEN | Replaced by `app/Modules/{Domain}/routes/` |

**Rules:** Do not create new files or methods in these directories.
Bug fixes in existing code are allowed. See `docs/rules/frozen-zones.md`.

### Greenfield Zones (New code lives here)

| Directory | Status | Purpose |
|---|---|---|
| `app/Modules/{Domain}/Actions/` | ACTIVE | Business logic (write/state-change) |
| `app/Modules/{Domain}/Queries/` | ACTIVE | Read-only complex queries |
| `app/Modules/{Domain}/Http/` | ACTIVE | Controllers, requests |
| `app/Modules/{Domain}/routes/` | ACTIVE | Route registration |
| `app/Shared/Contracts/{Domain}/` | ACTIVE | Cross-module interfaces |
| `resources/js/pages/{PascalCase}/` | ACTIVE | New Vue pages (PascalCase naming) |

### Known technical debt in Greenfield zones

These files are in Modules but still use wrong patterns — do not copy them:

| File | Problem | Should use |
|---|---|---|
| `app/Modules/Finance/Dng/Http/Controllers/DngPaymentController.php` | `response()->json()` | `ApiResponse::success()` |
| `app/Modules/Finance/Http/Web/Admin/BillingOperationsController.php` | `response()->json()` | `ApiResponse::success()` |
| `app/Modules/Academic/Http/Web/Admin/StudentController.php` | `response()->json()` | `ApiResponse::success()` |
| (6 other files in Modules) | `response()->json()` | `ApiResponse::success()` |

### Pilot features (reference implementations — fully clean)

When writing new code, copy patterns from these:

| Feature | Backend | Frontend |
|---|---|---|
| Auth (login/logout) | `app/Modules/Identity/Http/Web/Admin/LoginController.php` | `resources/js/pages/auth/Login.vue` |
| Student context API | `app/Modules/Identity/Http/Api/Student/StudentContextController.php` | — |
| Room list + filters | (controller TBD) | `resources/js/pages/rooms/Index.vue` |

Full reference: `docs/rules/reference-implementations.md`

---

## Refactoring a legacy feature

Use the `refactor-feature` skill (works in Claude Code, Windsurf, OpenCode):

```
/refactor-feature Rooms
/refactor-feature StudentController
```

Or follow manually: `docs/rules/refactor-playbook.md`

## Checking your code before commit

Use the `check-pattern` skill:

```
/check-pattern
/check-pattern --backend-only
```

Or read the checklist manually: `docs/rules/reference-implementations.md` (Anti-patterns section)

---

## API surfaces

| Surface | Route file | Auth |
|---|---|---|
| Admin web (Inertia) | `routes/web.php` + `routes/web/*` | Session |
| Student API v1 | `routes/api/v1/student.php` | Sanctum + `api.actor:student_or_parent` |
| Lecturer API v1 | `routes/api/v1/lecturer.php` | Sanctum + `api.actor:lecturer` |
| Identity API | `app/Modules/Identity/routes/api.php` | Sanctum |

---

## Task management

- Active sprint tasks: `.trellis/tasks/`
- Feature specs (long-lived): `.trellis/tasks/{task}/prd.md`
- Change workflow: `.claude/commands/opsx/` (Claude) / `.cursor/commands/` (Cursor)
- Session journals: `.trellis/workspace/{developer}/`

---

## Workflow for Trellis users

```bash
# Get session context
python3 ./.trellis/scripts/get_context.py

# List/create tasks
python3 ./.trellis/scripts/task.py list
python3 ./.trellis/scripts/task.py create "<title>" --slug <name>

# Record session after commit
python3 ./.trellis/scripts/add_session.py --title "..." --commit "hash"
```
