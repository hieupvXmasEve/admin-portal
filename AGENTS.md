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
./scripts/dev.sh composer exec pint -- --test
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
| Selected list row styling | `bg-accent` / `bg-primary` with default dark text | `border-primary bg-primary/5 ring-1 ring-primary/30`; hover `hover:bg-muted/50` |
| Accent/primary surface | `bg-accent` alone | `bg-accent text-accent-foreground` (pair mandatory) |

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

## Agent skills

Skills are installed under `.agents/skills/`. When a user invokes a named skill,
read that skill's `SKILL.md` before acting.

### Issue tracker

Issues and PRDs live as local markdown under `.scratch/<feature-slug>/`. See `docs/agents/issue-tracker.md`.

### Triage labels

Default vocabulary (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`), recorded as a `Status:` line in each issue file. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one root `CONTEXT.md` (domain glossary) + `docs/adr/` (architectural decisions). See `docs/agents/domain.md`.

### Documentation boundaries

Knowledge is kept small by discipline + file boundaries, not automation. Each knowledge type has one home: `CONTEXT.md` = stable glossary only, `docs/adr/` = decisions, `docs/prd/` = large specs, `.scratch/<feature>/` = tasks/status, commits/PRs = code history, `/handoff` = temporary. Never duplicate; reference by path. See `docs/agents/documentation-rules.md`.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/socialite (SOCIALITE) - v5
- laravel/telescope (TELESCOPE) - v5
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v3
- @laravel/echo-vue (ECHO_VUE) - v2
- laravel-echo (ECHO) - v2
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `./scripts/dev.sh npm run build` or `./scripts/dev.sh npm run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands through the Swinx Docker wrapper (e.g., `./scripts/dev.sh artisan route:list`). Do not run `php artisan` directly from the host.
- Inspect routes with `./scripts/dev.sh artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `./scripts/dev.sh artisan config:show app.name`, `./scripts/dev.sh artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `./scripts/dev.sh artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `./scripts/dev.sh artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always declare `declare(strict_types=1);` at the top of every `.php` file.
- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `./scripts/dev.sh artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `./scripts/dev.sh artisan list` and check their parameters with `./scripts/dev.sh artisan [command] --help`.
- If you're creating a generic PHP class, use `./scripts/dev.sh artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `./scripts/dev.sh artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `./scripts/dev.sh artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `./scripts/dev.sh npm run build` or ask the user to run `./scripts/dev.sh npm run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `./scripts/dev.sh composer exec pint -- --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run Pint in test mode for formatting fixes; use `./scripts/dev.sh composer exec pint -- --format agent`.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `./scripts/dev.sh artisan make:test --pest {name}`.
- Run tests: `./scripts/dev.sh artisan test --compact` or filter: `./scripts/dev.sh artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
