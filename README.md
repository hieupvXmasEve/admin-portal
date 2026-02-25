# Swinx

Swinx is a university operations platform built on Laravel + Vue + Inertia. It supports web-based administration and API-based student/lecturer workflows across identity, academic, and finance domains.

## Current Baseline

This repository currently contains:
- Laravel 12 backend with module-oriented domain code in `app/Modules/`
- Vue 3 + Inertia frontend in `resources/js/`
- Student and lecturer API surfaces under `routes/api/v1/`
- Extensive existing docs under `docs/` plus product specs under `prd/`

This README is an initial accurate baseline as of `2026-02-23`.

## Tech Stack

- Backend: Laravel 12, Sanctum, Socialite, Telescope, PHP (Composer constraint: `^8.2`)
- Frontend: Vue 3, Inertia, TypeScript, Tailwind CSS v4, Pinia, Vite
- Data: MySQL/MariaDB, Redis
- Runtime/Infra assets: FrankenPHP + Docker Compose files under `docker/`

## Repository Structure

```text
app/
  Modules/                 # Domain modules: Identity, Academic, Finance
  Http/                    # Controllers, middleware, requests, resources
  Services/                # Service-heavy legacy/shared business logic
resources/
  js/
    app.ts                 # Inertia SPA entry
    ssr.ts                 # Inertia SSR entry
    pages/                 # Feature pages
    components/            # Shared UI
    composables/           # Reusable logic hooks
routes/
  web.php
  api.php
  api/v1/student.php
  api/v1/lecturer.php
scripts/                   # Environment/deploy/monitor scripts (see known gaps)
docker/                    # Dockerfiles + compose variants + Caddy configs
docs/                      # Documentation source of truth
prd/                       # Product requirement/domain notes
```

## Verified Entry Points

- Web app boot: `resources/js/app.ts`
- SSR boot: `resources/js/ssr.ts`
- Main web routes: `routes/web.php`
- Main API routes: `routes/api.php`
- Student API routes: `routes/api/v1/student.php`
- Lecturer API routes: `routes/api/v1/lecturer.php`

Health endpoints currently present:
- `/up` (Laravel health route from `bootstrap/app.php`)
- `/health` (web route)
- `/api/health` (API route)

## Local Development

### Option 1: Native (most reliable currently)

1. Install dependencies:

```bash
composer install
npm ci
```

2. Prepare environment:

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
```

3. Start app:

```bash
composer dev
```

### Option 2: Docker assets exist but need alignment

Docker files exist under `docker/`, but script/compose path assumptions are not fully aligned yet. See `docs/deployment-guide.md` before using Docker workflows.

## Common Commands

```bash
# Backend/frontend dev loop
composer dev

# Frontend only
npm run dev

# Build assets
npm run build

# Static checks
npm run lint
npm run format:check
npm run type-check

# PHP tests
php artisan test
```

## Documentation

Primary baseline docs:
- `docs/project-overview-pdr.md`
- `docs/codebase-summary.md`
- `docs/code-standards.md`
- `docs/system-architecture.md`
- `docs/project-roadmap.md`
- `docs/deployment-guide.md`
- `docs/design-guidelines.md`

Additional module/API docs are in `docs/api/`, `docs/modules/`, `docs/rules/`, and related subfolders.

## Known Gaps (Current State)

- Several script references are stale or missing (for example `scripts/test-local.sh`, `scripts/docker-compose-dev.sh`, root-level `dev.sh`/`prod.sh` used by older docs).
- GitHub Actions workflow files exist but are fully commented out.
- `routes/api/v1/student.php` and `routes/api/v1/lecturer.php` include TODO/commented route sections and commented rate-limit middleware.
- Documentation index files include stale links and overlap between `docs/` and `prd/`.

## Contribution Guidance

- Keep docs under `docs/` as source of truth.
- Prefer updating existing files over creating duplicate variants.
- Verify implementation facts against code before documenting.
- Keep markdown docs concise and maintainable.

## License

Proprietary software for internal academic operations.
