---
title: Swinx
status: canonical
owner: Platform Team
last_verified: 2026-07-25
scope: repository-onboarding
---

# Swinx

Swinx is a university operations platform built as a Laravel 13 modular
monolith with a Vue 3, TypeScript, and Inertia v3 staff application.

## Product surfaces

- Staff/admin web application.
- Student API under `/api/v1/student`.
- Lecturer API under `/api/v1/lecturer`.
- Parent access to authorized student capabilities.
- Controlled MCP tools for authorized staff.
- Separate Nuxt student and lecturer portals under ignored `FE/` repositories.

## Technology

- PHP 8.4, Laravel 13, Sanctum, Pest 4.
- Vue 3, TypeScript, Inertia v3, Tailwind CSS 4.
- MySQL 8, Redis, FrankenPHP.
- Docker-first local and production workflows.

## Start here

AI agents read [AGENTS.md](AGENTS.md) first.

Documentation:

- [Documentation registry](docs/README.md)
- [Product baseline](docs/project-overview-pdr.md)
- [System architecture](docs/system-architecture.md)
- [Design guidelines](docs/design-guidelines.md)
- [Deployment guide](docs/deployment-guide.md)
- [Domain glossary](CONTEXT.md)

## Local development

```bash
cp .env.example .env
./scripts/dev.sh start
./scripts/dev.sh composer install
./scripts/dev.sh npm ci
./scripts/dev.sh artisan key:generate
./scripts/dev.sh artisan migrate
./scripts/dev.sh npm run dev
```

Application commands run inside Docker:

```bash
./scripts/dev.sh status
./scripts/dev.sh logs app
./scripts/dev.sh artisan <command>
./scripts/dev.sh composer <command>
./scripts/dev.sh npm <command>
./scripts/dev.sh test
```

See [scripts/README.md](scripts/README.md) for the script inventory and
[docs/deployment-guide.md](docs/deployment-guide.md) for environment workflows.

## Targeted quality checks

```bash
./scripts/dev.sh artisan test --compact --filter=<test>
./scripts/dev.sh composer exec pint -- --dirty --format agent
./scripts/dev.sh npm exec eslint -- <changed-files>
./scripts/dev.sh npm exec prettier --check <changed-files>
./scripts/check-docs.sh
```

Use the narrowest checks that prove the changed behavior. Broader suites are
reserved for cross-cutting changes and release validation.

## Main entry points

- Laravel bootstrap: `bootstrap/app.php`
- Web routes: `routes/web.php`
- API routes: `routes/api.php`
- Student API routes: `routes/api/v1/student.php`
- Lecturer API routes: `routes/api/v1/lecturer.php`
- Frontend entry: `resources/js/app.ts`
- SSR entry: `resources/js/ssr.ts`
- Domain modules: `app/Modules/`

Health endpoints:

- `GET /up`
- `GET /health`
- `GET /api/health`

## Contribution boundaries

- New business behavior belongs in a domain module.
- Keep public API contracts and affected portals aligned.
- Update canonical documentation in the same change window as contract,
  architecture, authentication, route, or deployment changes.
- Preserve unrelated worktree and nested-repository changes.
