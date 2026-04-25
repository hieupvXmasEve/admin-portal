# CLAUDE.md

Guidance for Claude Code when working in this repository.

> **For other AI tools** (OpenCode, Devin, Codex, Cursor): see `AGENTS.md`.

## Purpose

Short operating guide. Full rules live in `docs/rules/`. Full architecture lives in `docs/system-architecture.md`. Do not duplicate long framework manuals here.

## Project Context

- **Product:** Swinx — university operations platform.
- **Backend:** Laravel 12 monolith, PHP 8.4, MySQL 8, Redis, FrankenPHP.
- **Frontend:** Vue 3, TypeScript, Inertia v2, Tailwind CSS 4.
- **Architecture:** Hybrid modular monolith.
  - New business logic → `app/Modules/{Domain}/`.
  - Extend `app/Services/*` only when modifying existing service-led areas.
- **Auth:** Sanctum + actor middleware for student/parent/lecturer API surfaces.
- **Runtime:** Docker-first. Always use `./scripts/dev.sh ...` wrappers.

## Source of Truth

Read these before planning meaningful changes:

- `docs/ai-context.md` — quick-start map: what to read per task type (START HERE)
- `README.md` — current baseline, entry points, operational risks
- `docs/code-standards.md` — implementation rules and quality gates
- `docs/system-architecture.md` — architecture and module boundaries
- `docs/project-overview-pdr.md` — product and domain baseline
- `docs/codebase-summary.md` — current codebase snapshot
- `docs/design-guidelines.md` — UI and frontend standards

Use `docs/rules/` for task-specific rules (see `docs/rules/README.md` for index).

## Commands

Use Docker wrappers — do not assume host binaries exist:

```bash
./scripts/dev.sh start
./scripts/dev.sh status
./scripts/dev.sh logs app
./scripts/dev.sh artisan <command>
./scripts/dev.sh composer <command>
./scripts/dev.sh npm <command>
./scripts/dev.sh test
```

Quality checks:

```bash
./scripts/dev.sh test
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh artisan pint
```

## Architecture Rules

Full details in `docs/rules/architecture.md` and `docs/rules/contracts.md`.

### Module structure (backend)

```
app/Modules/{Domain}/
 ├─ Actions/        # Business logic — one file per use-case
 ├─ Queries/        # Read-only logic — complex joins/reports
 ├─ Http/
 │   ├─ Web/Admin/  # Inertia controllers
 │   ├─ Api/        # JSON API controllers
 │   └─ Requests/   # FormRequest validation per domain
 ├─ Policies/
 └─ routes/
```

### Cross-module communication

- Module A needing data from Module B → **must use a Contract** in `app/Shared/Contracts/{Domain}/`.
- Never use Eloquent `join` across module boundaries.
- Contracts must not accept or return Eloquent Models — use DTOs.

## Naming Conventions

Full details in `docs/rules/naming.md`.

| Type | Format | Example |
|---|---|---|
| Module | PascalCase singular | `Academic`, `Finance` |
| Action | `VerbEntityAction` with `static run(array $data)` | `CreateEventAction` |
| Query | `VerbEntityContextQuery` with `handle(...$args)` | `ListAcademicRecordsQuery` |
| Controller (Web) | `Http/Web/Admin/{Entity}Controller` | — |
| Controller (API) | `Http/Api/Student/{Entity}Controller` | — |
| Contract | `{Noun}{Verb}{Purpose}` | `StudentAcademicReader` |
| Vue component | PascalCase | `RecordTable.vue` |
| Composable | `useXxx.ts` | `useInertiaFilters.ts` |
| DB table/column | snake_case | `course_offerings` |
| Route name | dot notation | `academic.records.index` |

## Backend Guidelines

Full details in `docs/rules/backend.md` and `docs/rules/pattern.md`.

- Controllers orchestrate only: validate → call Action/Query → return response.
- New business logic → `Actions/` (not `Services/`).
- Use FormRequest for validation; never validate inline in controllers.
- Use `DB::transaction()` for multi-write flows.
- All API responses → `ApiResponse::success()` / `ApiResponse::error()`. Never `response()->json()` directly.
- Campus scoping must be explicit where data is campus-bound.

## Frontend Guidelines

Full details in `docs/rules/frontend.md`, `docs/rules/filtering.md`, `docs/rules/api-interaction.md`.

- Use `<script setup lang="ts">` for all new components.
- Inertia page forms → `useForm` from `@inertiajs/vue3`.
- Modal/drawer non-navigating forms → vee-validate + Zod + `useApi`/`useApiRequest`.
- Filter/pagination pages → `useDataTable` composable (see `docs/rules/filtering.md` and `docs/useDataTable-examples.md`).
- Use `route(...)` helpers over literal URL paths.
- Use `lucide-vue-next` icons and existing `@/components/ui` primitives.

## Forbidden Patterns

These are the top mistakes that break architecture or produce bugs:

| What | Wrong | Correct |
|---|---|---|
| New business logic | `app/Services/NewFeature.php` | `app/Modules/{Domain}/Actions/VerbEntityAction.php` |
| Inertia page forms | vee-validate + Zod | `useForm` from `@inertiajs/vue3` |
| Filter/pagination pages | `useInertiaFilters` or `useServerTableQuery` | `useDataTable` from `@/composables/useDataTable` |
| API response | `response()->json([...])` | `ApiResponse::success($data)` |
| Cross-module data | Direct Eloquent query on another module's table | Contract in `app/Shared/Contracts/` |
| New finance writes | `payment_allocations` table | `payment_applications` table |
| Run artisan directly | `php artisan migrate` | `./scripts/dev.sh artisan migrate` |
| Literal URL in frontend | `router.visit('/academic/records')` | `route('academic.records.index')` |
| Invent schema fields | Guess column/relation names | Verify in migration files or existing model |

## Security & Authorization

Full details in `docs/rules/security.md`.

- Route with no `{id}` → Gate check.
- Route with `{id}` → Policy check (`$this->authorize('action', $model)`).
- Never call `Gate::allows()` directly inside controllers for resource actions.

## Documentation

Update docs in the same change window when behavior, routes, contracts, auth, or architecture changes.

For documentation-only edits, application tests are not required; do a Markdown/readability check instead.

## Done Criteria

Before final response, verify the relevant scope:

- [ ] Code compiles or targeted checks were run (state clearly if checks could not run)
- [ ] Tests were run for behavior changes, or the gap is explicit
- [ ] Docs updated when contracts, routes, or architecture changed
- [ ] Final answer summarizes changed files, validation result, and unresolved questions
