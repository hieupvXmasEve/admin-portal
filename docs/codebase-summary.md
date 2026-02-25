# Codebase Summary

Last updated: 2026-02-23
Compaction source: `repomix-output.xml`

## 1) How This Summary Was Built

This summary is based on:
- `repomix-output.xml` generated from current workspace (`repomix -o repomix-output.xml`)
- direct verification of key source files (`composer.json`, `package.json`, `routes/*`, `app/Modules/*`, `resources/js/*`, `scripts/*`, `docker/*`)

Repomix run snapshot (command output):
- Packed files: 1,992
- Total tokens: 3,108,013
- Output file: `repomix-output.xml`

## 2) High-Level Shape

Core implementation is concentrated in:
- `app/` (Laravel backend, modules, services, controllers, middleware)
- `resources/` (Vue + Inertia frontend)
- `database/` (migrations, schema, seeders)
- `routes/` (web + API route trees)
- `docs/` (large existing documentation tree)

Notable heavy/runtime or generated areas:
- `storage/` contains large runtime artifacts and attachments
- repository root includes very large generated files (`repomix-output.xml`, `release-manifest.json`)

## 3) Backend Summary

### Framework and Dependencies

- Laravel `^12.0`
- Sanctum, Socialite, Telescope
- Spreadsheet/export support via `maatwebsite/excel`
- Activity logging via `spatie/laravel-activitylog`

### Core Code Organization

- Domain modules: `app/Modules/Identity`, `app/Modules/Academic`, `app/Modules/Finance`
- Shared legacy/service-heavy layer: `app/Services/`
- API controllers: `app/Http/Controllers/Api/*`
- Web controllers: `app/Http/Controllers/Web/*`
- Request validation: `app/Http/Requests/*`
- API resources: `app/Http/Resources/*`

### Routing Surface

- Web root: `routes/web.php` + many files in `routes/web/`
- API root: `routes/api.php`
- Student API v1: `routes/api/v1/student.php`
- Lecturer API v1: `routes/api/v1/lecturer.php`
- Module-injected routes via service providers, especially Identity and Finance

Current route count signal:
- ~820 route declarations matched across `routes/` and `app/Modules/*/routes` (grep-based count; includes grouped declarations)

## 4) Frontend Summary

### Runtime and Build

- SPA entry: `resources/js/app.ts`
- SSR entry: `resources/js/ssr.ts`
- Build tool: Vite (`vite.config.ts`)

### Structure

- Pages grouped by feature under `resources/js/pages/`
- Shared UI in `resources/js/components/`
- Shared logic in `resources/js/composables/`
- Types in `resources/js/types/`
- Default authenticated layout wiring via `AppLayout` in Inertia resolver

### UI Stack

- Vue 3 + TypeScript
- Tailwind CSS v4 + theme tokens in `resources/css/app.css`
- Reka UI + shadcn-style component patterns
- Pinia for client-side state

## 5) Data and Infrastructure Summary

- Database config supports MySQL, MariaDB, SQLite, PostgreSQL, SQL Server
- Primary expected runtime data stores are MySQL/MariaDB + Redis
- 176 migrations under `database/migrations/`
- Docker assets exist under `docker/` for dev/local-prod/prod variants

## 6) Documentation State

Documentation is broad but fragmented:
- Existing docs cover many modules (`docs/api`, `docs/modules`, `docs/rules`, `docs/email`, etc.)
- `prd/` contains additional feature/domain requirement docs
- Some indexes and command references are stale and overlap with newer docs

This baseline adds core navigation docs:
- `docs/project-overview-pdr.md`
- `docs/codebase-summary.md`
- `docs/code-standards.md`
- `docs/system-architecture.md`
- `docs/project-roadmap.md`
- `docs/deployment-guide.md`
- `docs/design-guidelines.md`

## 7) Verified Hotspots (Current Risk/Complexity)

- Very large service classes in `app/Services/`
- API v1 student/lecturer route files include TODO/commented sections
- Script and environment references are inconsistent in several `scripts/*.sh` and older docs
- CI workflow files in `.github/workflows/` are present but commented out
- Automated test footprint is currently very small relative to codebase size

## 8) Recommended Next Documentation Priorities

1. Consolidate docs index/navigation (`docs/README.md`) to remove stale links.
2. Normalize deployment and environment docs around one canonical workflow.
3. Create a concise API index that links route groups to the existing deep API docs.
4. Add testing strategy docs tied to current actual test inventory.

## Unresolved Questions

- Should `docs/README.md` be rewritten now to match this baseline set as the new top-level docs index?
- Should `storage/` and generated artifacts be formally excluded from future repomix/doc metrics?
- Is the long-term architecture expected to favor `app/Modules/*` over `app/Services/*`, and what migration policy should be documented?
