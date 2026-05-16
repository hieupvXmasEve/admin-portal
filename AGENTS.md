# AGENTS.md

Guidance for all AI coding agents (OpenCode, Devin, Codex, Cursor, and others) when working in this repository.

> **Note for Claude Code users**: see `CLAUDE.md` for the same baseline in Claude's native format.

## Project

**Name:** Swinx
**Type:** Laravel 13 monolith + Vue 3 SPA (PHP 8.4, MySQL 8, Redis, FrankenPHP)
**Frontend stack:** Vue 3, TypeScript, **Inertia v3** (`@inertiajs/vue3 ^3.0`, `inertiajs/inertia-laravel 3.x`), Tailwind CSS 4.
**Product:** University operations platform — web admin/staff app + student/lecturer API surfaces.
**Architecture:** Hybrid modular monolith. New business logic in `app/Modules/{Domain}`. Shared legacy services in `app/Services/*`.

> **CRITICAL — Version awareness:** This project runs **Laravel 13** and **Inertia v3**. Many v2 APIs are removed or renamed. Before writing any Inertia or Laravel code, consult `docs/inertiajs-vue-info.md` (Inertia v3 reference) and `.devin/skills/swinx-frontend/` (project standards). Never use deprecated patterns from older versions.

## Source of Truth

Read these before planning any change:

- `docs/ai-context.md` — quick-start: what to read, what not to do (START HERE)
- `README.md` — current baseline, entry points, operational risks
- `docs/code-standards.md` — implementation rules and quality gates
- `docs/system-architecture.md` — architecture and module boundaries
- `docs/project-overview-pdr.md` — product and domain baseline
- `docs/codebase-summary.md` — current codebase snapshot
- `docs/design-guidelines.md` — UI and frontend standards
- `docs/inertiajs-vue-info.md` — **Inertia v3 API reference** (must-read for any frontend work)

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

## Inertia v3 API Rules (mandatory)

Breaking changes from Inertia v2 → v3. Violating these produces runtime errors.

**Backend (Laravel):**
- Deferred props: `Inertia::defer(fn () => ...)` — lazy-loaded after initial render.
- Static props: `Inertia::once(fn () => ...)` — sent only on first visit, excluded from partial reloads.
- Flash: `Inertia::flash('success', 'message')` — not `->with('success', ...)`.

**Frontend (Vue):**
- `<Deferred>` uses a **string** `data` prop: `<Deferred data="scoresData">` (not `:data="['scoresData']"`).
- `<Deferred>` slots: `#fallback` for skeleton, `#default="{ reloading }"` for loaded state.
- Props are **snake_case**: Laravel sends `class_sessions`, not `classSessions`. TS interfaces must match.

**Removed APIs (do NOT use):**
- `Inertia::lazy()` → use `Inertia::defer()` or `Inertia::optional()`
- `$page.props.flash` → use `usePage().props.flash` with `Inertia::flash()`

> Full reference: `docs/inertiajs-vue-info.md` | Project patterns: `.devin/skills/swinx-frontend/`

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
| Inertia deferred prop | `Inertia::lazy(fn () => ...)` | `Inertia::defer(fn () => ...)` |
| Deferred component bind | `<Deferred :data="['x']">` | `<Deferred data="x">` |
| Flash message | `->with('success', 'msg')` | `Inertia::flash('success', 'msg')` |
| Prop casing in TS | `classSessions: Session[]` | `class_sessions: Session[]` (snake_case) |
| `DatePicker` in modal without `portalTo` | `<DatePicker v-model="..." />` | `<DatePicker :portal-to="modalContentRef ?? undefined">` |
| `useNativeDialog: true` (reverting) | Remove `putConfig` or set `true` | Keep `useNativeDialog: false` in app.ts — required for Select above modal |
| `input[type=date]` or `input[type=time]` in forms | `<Input type="date">` | `<DatePicker>` / `<TimePicker>` from `@/components/ui` |

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

<!-- KHUYM:START -->
# Khuym Workflow

Use `khuym:using-khuym` first in this repo unless you are resuming an already approved Khuym handoff.

## Startup

1. Read this file at session start and again after any context compaction.
2. If `.khuym/onboarding.json` is missing or outdated, stop and run `khuym:using-khuym` before continuing.
3. If `.codex/khuym_status.mjs` exists, run `node .codex/khuym_status.mjs --json` as the first quick scout step.
4. If `.khuym/HANDOFF.json` exists, do not auto-resume. Surface the saved state and wait for user confirmation.
5. If `history/learnings/critical-patterns.md` exists, read it before planning or execution work.

## Chain

```
khuym:using-khuym
  → khuym:exploring
  → khuym:planning
  → khuym:validating
  → khuym:swarming
  → khuym:executing
  → khuym:reviewing
  → khuym:compounding
```

## Critical Rules

1. Never execute without validating.
2. `CONTEXT.md` is the source of truth for locked decisions.
3. If context usage passes roughly 65%, write `.khuym/HANDOFF.json` and pause cleanly.
4. Treat `.khuym/state.json` as the single runtime state file for routing, current focus, and operator notes.
5. After compaction, re-read `AGENTS.md`, run `node .codex/khuym_status.mjs --json` if present, then re-open `.khuym/HANDOFF.json`, `.khuym/state.json`, and the active feature context before more work.
6. P1 review findings block merge.

## Working Files

```
.khuym/
  onboarding.json     ← onboarding state for the Khuym plugin
  state.json          ← single runtime state file for agents, tools, and humans
  HANDOFF.json        ← pause/resume artifact
  reservations.json   ← local file reservations for same-session Codex swarms

history/<feature>/
  CONTEXT.md          ← locked decisions
  discovery.md        ← research findings
  approach.md         ← approach + risk map

history/learnings/
  critical-patterns.md

.beads/               ← bead/task files when beads are in use
.spikes/              ← spike outputs when validation requires them
```

.codex/
  khuym_status.mjs    ← read-only scout command for onboarding, state, and handoff
  khuym_state.mjs     ← shared state helpers used by the scout command
  khuym_reservations.mjs ← local reservation helper used by swarming, executing, and hooks

## Codex Guardrails

- Repo-local `.codex/` files installed by Khuym are workflow guardrails, not optional decoration.
- Use `node .codex/khuym_status.mjs --json` as the preferred quick scout step when it is available.
- Treat `compact_prompt` recovery instructions as mandatory.
- Use `bv` only with `--robot-*` flags. Bare `bv` launches the TUI and should be avoided in agent sessions.
- If the repo is only partially onboarded, stay in bootstrap/planning mode and surface what is missing before implementation.

## Session Finish

Before ending a substantial Khuym work chunk:

1. Update or close the active bead/task if one exists.
2. Leave `.khuym/state.json` and `.khuym/HANDOFF.json` consistent with the current pause/resume state.
3. Mention any remaining blockers, open questions, or next actions in the final response.
<!-- KHUYM:END -->
