# AGENTS.md

Guidance for all AI coding agents (OpenCode, Devin, Codex, Cursor, and others) when working in this repository.

> **Note for Claude Code users**: see `CLAUDE.md` for the same baseline in Claude's native format.

## Project

**Name:** Swinx
**Type:** Laravel 12 monolith + Vue 3 SPA (PHP 8.4, MySQL 8, Redis, FrankenPHP)
**Product:** University operations platform — web admin/staff app + student/lecturer API surfaces.
**Architecture:** Hybrid modular monolith. New business logic in `app/Modules/{Domain}`. Shared legacy services in `app/Services/*`.

## Source of Truth

Read these before planning any change:

- `docs/ai-context.md` — quick-start: what to read, what not to do (START HERE)
- `README.md` — current baseline, entry points, operational risks
- `docs/code-standards.md` — implementation rules and quality gates
- `docs/system-architecture.md` — architecture and module boundaries
- `docs/project-overview-pdr.md` — product and domain baseline
- `docs/codebase-summary.md` — current codebase snapshot
- `docs/design-guidelines.md` — UI and frontend standards

For task-specific rules, use `docs/rules/` (see `docs/rules/README.md` for index).

## Commands

Project runs inside Docker. Always use the wrapper scripts:

```bash
./scripts/dev.sh start
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

## Backend Rules (summary — full rules in `docs/rules/backend.md`)

- Controllers orchestrate only: validate → call Action/Query → return response.
- New business logic → `app/Modules/{Domain}/Actions/` (not `app/Services/`).
- Use FormRequest for validation. Use `DB::transaction()` for multi-write flows.
- Cross-module communication → Contract in `app/Shared/Contracts/{Domain}/` (never direct Eloquent joins).
- All API responses → `ApiResponse::success()` / `ApiResponse::error()` (never `response()->json()` directly).

## Frontend Rules (summary — full rules in `docs/rules/frontend.md`)

- Use `<script setup lang="ts">` for all new components.
- Inertia page forms → `useForm` from Inertia (not vee-validate+Zod).
- Modal/drawer non-navigating forms → vee-validate + Zod + `useApi`.
- Filter/pagination pages → `useDataTable` composable (see `docs/rules/filtering.md` and `docs/useDataTable-examples.md`).
- Use `route(...)` helpers over literal URL paths.
- Use `lucide-vue-next` icons and existing `@/components/ui` primitives.

## Forbidden Patterns

These are the top mistakes AI tools make in this codebase:

| Pattern | Wrong | Correct |
|---|---|---|
| Business logic location | `app/Services/NewFeatureService.php` | `app/Modules/{Domain}/Actions/VerbEntityAction.php` |
| Form validation (pages) | vee-validate + Zod on Inertia pages | `useForm` from `@inertiajs/vue3` |
| Filter/pagination pages | `useInertiaFilters` or `useServerTableQuery` | `useDataTable` from `@/composables/useDataTable` |
| API response | `return response()->json([...])` | `return ApiResponse::success($data)` |
| Cross-module data | `AcademicRecord::where(...)` inside Finance | Contract: `$reader->getStudentGpa($id)` |
| Run artisan | `php artisan migrate` | `./scripts/dev.sh artisan migrate` |

## Development Principles

- **YAGNI** — don't build what is not asked
- **KISS** — prefer simple solutions
- **DRY** — no duplicate logic

## Done Criteria

Before final response:

- [ ] Code compiles / targeted checks were run
- [ ] Tests were run for behavior changes (or gap is explicit)
- [ ] Docs updated when routes, contracts, auth, or architecture changed
- [ ] Final answer summarizes changed files, validation result, and unresolved questions
