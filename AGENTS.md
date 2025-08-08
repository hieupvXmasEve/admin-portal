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
