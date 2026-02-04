# AGENTS GUIDE (Laravel 12 + Vue 3 Inertia)

Purpose: fast instructions for coding agents. Follow repo conventions; prefer facts over guesses.

## Quick Start Commands

- `composer dev` – serve app + queue + logs + Vite (no Docker).
- `composer dev:ssr` – start SSR (build first if needed).
- `npm run dev` – Vite dev only; `npm run build` / `npm run build:ssr` for assets.
- `composer test` – clear config then run full PHP suite.
- `npm run lint` / `npm run format` / `npm run format:check` / `npm run type-check` – JS/TS quality gates.
- Docker helpers: `npm run docker:up|down|logs`, `npm run docker:test`, `npm run docker:test:down`.
- Setup/validate: `npm run setup`, `npm run validate`, `npm run pre-push`, `npm run pre-push:docker`.

## Run Single Tests (PHP)

- File: `php artisan test tests/Feature/ExampleTest.php`.
- Filter method: `php artisan test --filter=methodName`.
- Pest direct: `./vendor/bin/pest --filter="test name"` (if installed).
- Keep runs minimal; prefer focused filters during edits.

## Repository Layout (high-level)

- `app/Modules/*` (preferred) and `app/Services/*` (legacy) hold business logic.
- `app/Http/Controllers/{Web,Api}/`, `app/Http/Requests/*`, `app/Http/Resources/*`, `app/Policies/*`.
- `resources/js/{pages,components,layouts,composables,stores,types,schemas,constants}` (Vue 3 + TS + Inertia).
- `database/{migrations,factories,seeders}`, `routes/{web,api,console}.php`, `config/`, `bootstrap/`.
- `tests/{Feature,Unit}` use Pest.

## Architecture Rules (Cursor pattern)

- Use Action–Request–Resource pattern (see `docs/rules/pattern.md`): Actions own business logic, FormRequests validate, Resources shape API, Controllers are thin adapters.
- Name Actions `VerbEntityAction` inside domain folders; one use-case per class.
- Keep controllers free of business logic; call Actions; return Inertia/JSON.
- Prefer module organization in `app/Modules/{Domain}` when adding new features.

## Laravel Conventions

- Every PHP file: `declare(strict_types=1);`, braces on all control structures.
- Use constructor property promotion; no empty constructors.
- Return types and parameter types required everywhere; nullable where appropriate.
- Validation via FormRequest; align with frontend Zod schemas; guard sort/per_page inputs.
- Authorization: Spatie permissions, campus-scoped; check policies/gates in controllers and Actions.
- Database: use Eloquent relationships; avoid `DB::` unless necessary; prevent N+1 with eager loads.
- Transactions for multi-step writes; log meaningful errors; rethrow domain exceptions when needed.
- Use named routes (`route('name')`); keep `env()` usage inside config only.
- Middleware lives in `bootstrap/app.php` (Laravel 12 structure); commands auto-register.

## Frontend Standards

- Vue 3 + TS + Inertia v2; always `<script setup lang="ts">`.
- Components/pages in PascalCase; single root element; prefer composition API.
- Forms: vee-validate + Zod + `toTypedSchema`; `useForm` (validation) + `useForm` from Inertia for submits; sync schemas with FormRequests.
- Tables: use `DataTable.vue` + `DataPagination.vue`; column defs via `@tanstack/vue-table`; data type `PaginatedResponse<T>`.
- Filters: follow `docs/rules/filtering.md` `useInertiaFilters` contract (defaults, transform to numbers/booleans, `only` props, debounce, clear behaviour).
- Reka-UI/shadcn components from `@/components/ui`; ensure component exists; checkbox uses `:model-value` + `@update:model-value`.
- Tailwind v4 only: import via `@import "tailwindcss";`; avoid deprecated utilities; prefer gaps over margins; support dark mode if present.
- Icons: `lucide-vue-next`; state: Pinia; routing: `<Link>` / `router.visit`.

## TypeScript & Imports

- Prefer `const`; avoid `var`; narrow `let` scope.
- Import types with `import type { ... }` to trim bundles.
- Organize imports via Prettier + `prettier-plugin-organize-imports`; keep side-effect imports separate.
- Use interfaces for object shapes; keep literal unions for enums; convert server numbers/booleans explicitly.
- Avoid `any`; use `unknown` + narrowing when needed.
- For events/handlers, type the payloads (e.g., `MouseEvent`, `FormEvent`).

## Formatting & Style

- Formatting enforced by Prettier/ESLint + `.editorconfig` (4-space indent, LF, final newline). Do not reformat to 2 spaces even though some Cursor notes mention Airbnb.
- JS/TS/Vue: semicolons required; single quotes; trailing commas where valid; keep import order automatic.
- PHP: PSR-12 via Pint (`vendor/bin/pint --dirty` before finish); 4 spaces; docblocks for public APIs/array shapes.
- Markdown: keep trailing spaces when intentional; `.editorconfig` leaves md whitespace intact.

## Naming

- PHP: Classes PascalCase; methods camelCase verbs; models singular; tables snake_case plural.
- Frontend: components PascalCase (`UserForm.vue`); composables `useSomething`; stores `useSomethingStore`; types PascalCase; variables camelCase.
- Routes: dot notation (`identity.login.show`); API/web paths kebab-case.

## Error Handling

- Validate inputs early (FormRequests/Zod). Guard sort/per_page whitelists.
- Wrap fallible IO (files, HTTP, DB transactions) in try/catch; log context-rich errors; surface user-safe messages (toasts/Inertia errors).
- For async frontend calls, handle `form.errors` and `form.processing`; show optimistic UI only when safe.

## Testing Expectations

- Pest only. Place Feature tests under `tests/Feature/{Domain}`; Unit under `tests/Unit` (actions, helpers).
- Patterns in `docs/rules/testing.md`: list endpoints need action + controller coverage; validation tests live with controller tests.
- Use factories + `RefreshDatabase`; avoid fixtures; mock only in unit tests.
- Status assertions: use helpers (`assertForbidden`, `assertNotFound`, etc.).
- Prefer smallest scope run: `php artisan test --filter=Name` or file path; run full `composer test` before PRs if time permits.

## Build & CI Signals

- CI pipeline (`composer ci`): Pint test, Pest, ESLint, Prettier check, TS type-check. Match locally before pushing.
- Git commits: imperative, scoped; avoid committing secrets. Respect existing branches; no force-push unless asked.
- Hooks: `npm run pre-push` (or `pre-push:docker`) mirrors CI tasks.

## Cursor Rules Summary (must honor)

- `.claude/rules/naming.md`: short naming guardrails.
- `.claude/rules/pattern.md`: short architecture pattern guardrails.
- `.claude/rules/code-style.md`: short formatting guardrails.
- `.claude/rules/checklist.md`: short pre-flight checklist.
- `docs/rules/tech.md`: stack overview + common commands; align docs with PHP 8.4+, Laravel 12, Vue 3, Tailwind 4, Sanctum/Socialite.
- `docs/rules/code-style.md`: lint/format rules, TS/Vue conventions, checkbox binding, error handling.
- `docs/rules/pattern.md`: enforce Action–Request–Resource; controllers thin; Actions single use-case; routes wired to controllers; web uses Inertia.
- `docs/rules/filtering.md`: standardized filter contract with `useInertiaFilters` (defaults, transform, clear, pagination, sort wiring).
- `docs/rules/testing.md`: action + controller test layout for filterable lists; validation assertions with controller tests.
- `docs/rules/structure.md`: directory and naming map; permission helpers, validation alignment, data tables guidance.
- `docs/rules/laravel-boost.md`: Boost tools + Laravel/Tailwind/Pest conventions; use `search-docs` before guessing.
- No `.cursorrules` or Copilot instructions found; nothing extra to import.

## Inertia/Vue Patterns

- Always return props from controllers with nullable defaults so `useInertiaFilters` can drop defaults from URLs.
- Debounce search inputs (~300–400ms) and manual numeric filters; use `clearFilters` to reset to defaults.
- For selects, use meaningful default values (`'all'`) and strip them via `transform` before hitting backend.
- Pagination: keep `withQueryString()` on Laravel paginators; front hooks `handlePaginationNavigate` & `handlePageSizeChange`.
- Provide skeleton/empty states for deferred props or loading lists.

## Styling & UI

- Tailwind class order: layout → spacing → typography → color → state; remove redundancy; favor `gap-*` over margin spacing between siblings.
- Backgrounds: prefer gradients/textures when designing new views; keep within existing design language.
- Dark mode: mirror existing pattern using `dark:` utilities where present.

## Data & Types Sync

- Keep TS interfaces in `resources/js/types`; mirror Laravel Resources/models.
- Convert numbers/booleans before sending to backend; avoid empty strings for nullable fields—use `null`.
- Keep `PaginatedResponse<T>` typed for tables; align filter keys across TS, controller validation, and query builder.

## Logging & Monitoring

- Use Laravel logging for backend errors; avoid dumping. Telescope available for debugging.
- On frontend, avoid console noise; use toast notifications (`vue-sonner`) for user feedback.

## Deliverables Expectations for Agents

- Touch one concern per turn; summarize plan then deliver diff/full file.
- Do not invent DB fields/routes/props; verify in schema/models. Ask for schema only if missing.
- Avoid destructive git commands; never revert user changes. No new docs unless requested (this file is requested).
- Before finalizing PHP, run `vendor/bin/pint --dirty` when you change backend code. For frontend, ensure Prettier/ESLint pass locally.

## Reference Commands Cheat Sheet

- Full PHP tests: `composer test` or `php artisan test`.
- Single PHP test: `php artisan test --filter=Name` or `php artisan test tests/Feature/FileTest.php`.
- Pint fix: `vendor/bin/pint --dirty`.
- Lint/format JS: `npm run lint && npm run format:check`.
- Type-check: `npm run type-check` (vue-tsc no emit).
- Asset build: `npm run build` (client), `npm run build:ssr` (client + SSR).
- Dev stack: `composer dev` (serves + queue + logs + vite) or `npm run dev` for frontend only.

Stay concise, follow these defaults, and keep Laravel/Vue patterns consistent across modules.
