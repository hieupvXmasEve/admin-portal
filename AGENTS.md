# AGENTS.md

Guidance for all AI coding agents (Claude Code, Codex, Cursor, OpenCode, and others) when working in this repository. This is the single source of truth for agent operation in Swinx.

## Project

**Name:** Swinx
**Type:** Laravel 13 monolith + Vue 3 SPA (PHP 8.4, MySQL 8, Redis, FrankenPHP)
**Frontend stack:** Vue 3, TypeScript, **Inertia v3** (`@inertiajs/vue3 ^3.0`, `inertiajs/inertia-laravel 3.x`), Tailwind CSS 4.
**Product:** University operations platform — web admin/staff app + student/lecturer API surfaces.
**Architecture:** Hybrid modular monolith. New business logic in `app/Modules/{Domain}`. Shared legacy services in `app/Services/*`.

> **CRITICAL — Version awareness:** This project runs **Laravel 13** and **Inertia v3**. Many v2 APIs are removed or renamed. Before writing any Inertia or Laravel code, consult `docs/inertiajs-vue-info.md` (Inertia v3 reference). Never use deprecated patterns from older versions.

## Source of Truth

Read these before planning any change:

- `README.md` — current baseline, entry points, operational risks
- `docs/code-standards.md` — implementation rules and quality gates
- `docs/system-architecture.md` — architecture and module boundaries
- `docs/project-overview-pdr.md` — product and domain baseline
- `docs/codebase-summary.md` — current codebase snapshot
- `docs/design-guidelines.md` — UI and frontend standards
- `docs/inertiajs-vue-info.md` — **Inertia v3 API reference** (must-read for any frontend work)
- `docs/portal-repos.md` — cross-repo workflow for gitignored student/lecturer Nuxt portals

For task-specific rules, use `docs/rules/` (see `docs/rules/README.md` for index).

## Commands

Project runs inside Docker. Always use the wrapper scripts:

```bash
./scripts/dev.sh start
./scripts/dev.sh artisan <command>
./scripts/dev.sh composer <command>
./scripts/dev.sh npm <command>
./scripts/dev.sh test
./scripts/portal-status.sh
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

## Cross-Repo Portal Rules

The separate Nuxt portals live under `FE/` and are ignored by the Swinx git
repository:

- Student portal: `FE/student-nuxt`
- Lecturer portal: `FE/lecturer-nuxt`

When changing student-facing or lecturer-facing API behavior, follow
`docs/portal-repos.md`.

Rules:

- Set story metadata: `Portal impact: none | student | lecturer | both`.
- Student-impacting changes include `routes/api/v1/student.php`, student
  auth/context routes in `app/Modules/Identity/routes/api.php`, and controllers,
  resources, requests, actions, or docs that shape `/api/v1/student/*`.
- Lecturer-impacting changes include `routes/api/v1/lecturer.php`, lecturer
  auth routes in `app/Modules/Identity/routes/api.php`, and controllers,
  resources, requests, actions, or docs that shape `/api/v1/lecturer/*`.
- Run `./scripts/portal-status.sh` before touching portal files.
- If portal impact is `student`, inspect/update only the matching files in
  `FE/student-nuxt`; if `lecturer`, inspect/update only `FE/lecturer-nuxt`; if
  `both`, inspect/update both.
- Keep Swinx and portal git states separate. Do not stage portal files from the
  Swinx repository, and do not revert unrelated dirty changes in nested portal
  repos.
- Update backend API docs plus affected portal `shared/types`, composables,
  stores, or pages in the same work window when an API contract changes.
- Run portal `pnpm lint`, `pnpm typecheck`, and `pnpm build` when portal code is
  changed, or explicitly record why they could not run.

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

> Full reference: `docs/inertiajs-vue-info.md`

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

<!-- RETIRED-WORKFLOWS:START -->
## Retired Workflows

Harness Experimental is the sole operational workflow for this repository.

Do not use Khuym, claudekit, ClaudeKit command packs, `.claude/`, `.agents/`,
or `.khuym/` as workflow inputs. Do not run `node .codex/khuym_status.mjs` or
invoke `khuym:*` / `ck:*` skills for this repo.

Historical files under `history/` may still be read as product context when a
Harness story needs the old investigation notes, but they are not a live task
system.
<!-- RETIRED-WORKFLOWS:END -->

<!-- HARNESS:BEGIN -->
## Harness — sole operational system

Harness CLI (`./scripts/harness`, Rust binary at `scripts/bin/harness-cli`, DB at `harness.db`) is the only workflow system in this repo. Before work, read:

- `docs/HARNESS.md` — mental model + spec lifecycle
- `docs/FEATURE_INTAKE.md` — intake gate, risk lanes, classification
- `docs/ARCHITECTURE.md` — discovery-before-shape + boundary rules
- `docs/templates/` — story + decision + validation templates
- Current state: `./scripts/harness query matrix`, `./scripts/harness query backlog`, `./scripts/harness query decisions`

### Operator loop (all agents run this for every user requirement)

1. **Intake**: classify per `docs/FEATURE_INTAKE.md` (tiny/normal/high-risk + risk flags). Record:
   ```
   ./scripts/harness intake --type <new_spec|spec_slice|change_request|new_initiative|maintenance|harness_improvement> --summary "..." --lane <tiny|normal|high_risk> --flags "auth,data-model,..." --docs "docs/..."
   ```
2. **Story**: for normal/high-risk, create story file from `docs/templates/story.md` (or `docs/templates/high-risk-story/` for high-risk) at `docs/stories/<epic>/<story-id>.md`, then register:
   ```
   ./scripts/harness story add --id <id> --title "..." --lane <normal|high_risk> --contract docs/stories/<epic>/<id>.md
   ```
   Include `Portal impact: none | student | lecturer | both` in the story before
   implementation.
3. **Planning + confirm**: present approach (affected files, validation shape, risks) to user. Wait for explicit approval before implementation.
4. **Execute**: implement minimum vertical slice. Update story status:
   ```
   ./scripts/harness story update --id <id> --status in_progress
   ```
5. **Trace**: at end of substantial actions, record:
   ```
   ./scripts/harness trace --summary "..." --story <id> --actions "..." --changed "..." --outcome <completed|partial|failed|blocked> --friction "..."
   ```
6. **Decision**: when architecture/behavior choice is made, add ADR to `docs/decisions/NNNN-<slug>.md` + register:
   ```
   ./scripts/harness decision add --id <NNNN> --title "..." --status accepted --doc docs/decisions/NNNN-<slug>.md
   ```
7. **Backlog**: when friction is found but not fixed:
   ```
   ./scripts/harness backlog add --title "..." --while "<story-id>" --risk <tiny|normal|high_risk>
   ```

### Lanes (from FEATURE_INTAKE.md)

- **Tiny** (0-1 flags, no hard gate): patch directly + intake record. No story file required.
- **Normal** (2-3 flags): story file + matrix row + validation expectations.
- **High-risk** (4+ flags OR any hard gate — auth, authz, data loss, audit/security, external provider, validation weakening): full `docs/templates/high-risk-story/` folder (overview.md + design.md + validation.md + execplan.md) + user confirmation before implementation.

### Hard rules

- Never claim a validation command passes until it exists and was run.
- Use `./scripts/harness query matrix` to verify story status; do not edit `harness.db` by hand.
- ADR files in `docs/decisions/` are the human-readable surface; harness DB is the queryable index.
- One direction only: file → DB via CLI. Never DB → file.
<!-- HARNESS:END -->
