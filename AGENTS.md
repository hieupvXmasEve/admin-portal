# Repository Guidelines

## Project Structure & Module Organization
- app/: Laravel application logic (models, controllers, policies, jobs).
- routes/: HTTP routes split by domain; entry points `web.php`, `api.php`.
- resources/js/: Vue 3 + Inertia app (components, pages, layouts, stores, composables, utils).
- resources/css, images/: Frontend assets managed by Vite.
- database/: Migrations, seeders, factories.
- tests/: Pest tests (`Feature/`, `Unit/`, optional `Browser/`).
- public/: Public assets and entry HTML.
- config/, bootstrap/, storage/: Standard Laravel dirs.
- docker/, scripts/: Local dev, CI, and deployment helpers.

## Vue.js Conventions

### Component Structure

- Use `<script setup>` syntax for all new components
- Order script blocks: imports, props, emits, composables, reactive data, computed, methods
- Use PascalCase for component names and file names
- Prefer composition API over options API

### Template Guidelines

- Use kebab-case for HTML attributes and event handlers
- Prefer `v-show` for conditional rendering that toggles frequently
- Use `v-if` for conditional rendering that rarely changes
- Always use `:key` with `v-for` loops

### Reka-UI Components

- Verify component exists in `@/components/ui/` before use
- Add TODO comment if component needs installation: `<!-- TODO: Run npx shadcn-vue add [component] -->`
- Always import components explicitly
- Use proper TypeScript props interface

### DataTable & Pagination Pattern

For list/index pages, use these components:
- `DataTable` from `@/components/DataTable.vue` - table component
- `DataPagination` from `@/components/DataPagination.vue` - pagination component
- Props type: `PaginatedResponse<T>` from `@/types`
- Columns: `ColumnDef<T>[]` from `@tanstack/vue-table`

**Pattern:**
- Use `reactive()` for filter form state
- Debounce search with `debounce()` from `lodash-es` (300ms)
- Apply filters via `router.visit()` with `preserveState` and `preserveScroll`
- Custom cells: use `<template #cell-columnName="{ row }">` slots
- Handlers: `handlePaginationNavigate(url)` and `handlePageSizeChange(pageSize)`

**Reference:** `resources/js/pages/Scholarships/Index.vue` or `StudentScholarships/Index.vue`

### Form Validation Pattern (vee-validate + Zod)

For create/edit forms, use vee-validate with Zod schema:

**Components:**
- `useForm` from `vee-validate` - for validation
- `useForm as useInertiaForm` from `@inertiajs/vue3` - for submission
- `toTypedSchema` from `@vee-validate/zod` - convert Zod to vee-validate schema
- Form components from `@/components/ui/form`: `FormField`, `FormItem`, `FormLabel`, `FormControl`, `FormMessage`
- `toast` from `vue-sonner` - for notifications

**Pattern:**
1. Define Zod schema in `resources/js/schemas/` (match Laravel validation rules)
2. Convert with `toTypedSchema(zodSchema)`
3. Initialize `useForm()` with `validationSchema` and `initialValues`
4. Initialize `useInertiaForm()` with same initial values
5. Wrap submit handler with `handleSubmit()`
6. Use `<FormField v-slot="{ componentField }" name="fieldName">` with `v-bind="componentField"`
7. Handle `onSuccess` and `onError` callbacks with toast notifications
8. Convert types before submission (string → number, empty string → null)
9. For number inputs: `@input="(e) => componentField['onInput'](parseFloat(e.target.value) || 0)"`

**Important:**
- Never use empty string in `<SelectItem value="">`, use meaningful defaults
- Keep Zod schema in sync with Laravel FormRequest validation rules
- Store schemas in `resources/js/schemas/` directory

**Reference:** `resources/js/pages/Scholarships/Create.vue` or `StudentScholarships/Assign.vue`

## Build, Test, and Development Commands
- composer dev: Run PHP server, queue worker, logs, and Vite concurrently.
- composer dev:ssr: Start Laravel + Inertia SSR pipeline.
- npm run dev: Vite dev server for the frontend only.
- npm run build: Build production assets (client and optional SSR with build:ssr).
- composer test: Clear config and run the test suite.
- composer ci: Pint check, Pest, ESLint, Prettier check, and TS type-check.
- npm run lint | format | format:check | type-check: Frontend quality tasks.
- npm run docker:up | down | logs: Docker-based development support.

## Coding Style & Naming Conventions
- PHP: PSR-12 via Laravel Pint (4-space indent). Run `vendor/bin/pint` to fix.
- JS/TS/Vue: ESLint + Prettier (single quotes, semicolons, 4-space tabs, wide print width). Vue blocks ordered as script → template → style.
- Vue components: PascalCase files in `resources/js/components` or `pages` (e.g., Dashboard.vue).
- Composables/Stores: `useX` and `useXStore` naming; keep modules small and focused.
- Routes: Use dot notation names consistent with `routes/web.php` (e.g., curriculum_version.electives).

## Testing Guidelines
- Framework: Pest for Unit/Feature tests. Place files under `tests/Unit/*Test.php` and `tests/Feature/*Test.php`.
- Run: `composer test` (all), `php artisan test --filter=Name` (focused).
- Keep tests deterministic; prefer factories/seeders over fixtures. Tag slow/browser tests when applicable.

## Commit & Pull Request Guidelines
- Commits: Imperative, concise, and scoped (e.g., "Refactor authentication and user management features").
- PRs: Clear description, linked issues, repro steps; include screenshots/GIFs for UI changes.
- Checks: Ensure Pint, ESLint, Prettier check, type-check, and tests pass locally; CI mirrors these.
- Branching: Open PRs against `develop` or `main` as requested; keep diffs minimal and focused.
