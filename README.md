# Swinx

Swinx is a university operations platform built as a Laravel 12 + Vue 3 + Inertia monolith.

Last updated: 2026-02-25  
Owner: Platform Team  
Status: Current-state baseline (code-verified)

## Source of Truth

- Canonical project docs: `docs/`
- Baseline architecture and standards:
  - `docs/system-architecture.md`
  - `docs/code-standards.md`
  - `docs/project-overview-pdr.md`

## Current Baseline (Code-Verified)

- Backend: Laravel 12 hybrid monolith
  - modular domains: `app/Modules/*`
  - shared legacy/business services: `app/Services/*`
- Frontend: Vue 3 + TypeScript + Inertia (`resources/js/*`)
- API surfaces:
  - Student v1: `routes/api/v1/student.php`
  - Lecturer v1: `routes/api/v1/lecturer.php`
  - Identity module auth/context routes: `app/Modules/Identity/routes/api.php`

## Entry Points

- App bootstrap: `bootstrap/app.php`
- Web routes: `routes/web.php`
- API routes: `routes/api.php`
- Frontend app entry: `resources/js/app.ts`
- Frontend SSR entry: `resources/js/ssr.ts`

Health endpoints in code:
- `GET /up`
- `GET /health`
- `GET /api/health`

## Authentication and API Security (Current State)

- Sanctum remains default token auth for student/lecturer API groups.
- Actor middleware is in use for student/parent/lecturer segmentation:
  - `api.actor:student_or_parent`
  - `api.actor:parent`
  - `api.actor:lecturer`
- Identity lecturer refresh is in a protected middleware group.
- Parent refresh endpoint exists and is protected.
- Token TTL is standardized to 8 hours in current Identity login/refresh actions.
- Refresh rotation pattern is currently "issue new token, then revoke old token" in controllers.

Known open security drift (not fixed in this update):
- `routes/api.php` exposes public `system-config` endpoints (GET/PUT/POST).
- Finance API auth style differs (`web` + `auth` in `app/Modules/Finance/routes/api.php`) from Sanctum + actor model.

## Student Action Import (Academic Module)

Implemented under `app/Modules/Academic/routes/web.php`:
- `GET reports/student-actions/import`
- `GET reports/student-actions/import/template`
- `POST reports/student-actions/import/preview`
- `POST reports/student-actions/import/execute`

Verified behavior:
- Preview/execute anti-tamper check binds user + preview token + file hash + `shared_upload_record_id`.
- `ADMISSION_DEFERRAL` is excluded from template/import mapping.
- Append mode only allows same student + same action type + same period.
- Execute uses per-row DB transaction.
- Decision fields are persisted on action logs:
  - `decision_number`
  - `decision_signed_at`
  - `decision_signer`

## Local Development

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
composer dev
```

## Common Commands

```bash
composer dev
npm run dev
npm run build
npm run lint
npm run format:check
npm run type-check
php artisan test
```

## Current Quality Gates Status

- GitHub workflows exist but are fully commented out (disabled):
  - `.github/workflows/lint.yml`
  - `.github/workflows/tests.yml`
  - `.github/workflows/deploy.yml`
- Local checks are available, but CI-enforced merge gates are currently inactive.

## Known Operational Risks

- Script/path drift in `scripts/*` (missing helper scripts and root-vs-`docker/` compose path mismatch).
- Deployment artifacts still include secrets-exposure risks (hardcoded defaults/credentials and exposed DB ports in production compose files).

## Documentation Index

- `docs/project-overview-pdr.md`
- `docs/codebase-summary.md`
- `docs/code-standards.md`
- `docs/system-architecture.md`
- `docs/project-roadmap.md`
- `docs/deployment-guide.md`
- `docs/design-guidelines.md`

## Contribution Rules

- Keep docs evidence-first: only document what current code verifies.
- Update docs in the same change window when routes, middleware, contracts, or deploy behavior changes.
- Keep unresolved decisions explicitly tracked in `Unresolved Questions` sections.

## Unresolved Questions

- Should `/api/system-config*` move behind authenticated admin middleware?
- Should Finance API routes migrate from `web` + `auth` to Sanctum + actor model?
- Which deploy script becomes canonical (`scripts/prod.sh`, `scripts/deploy.sh`, or `scripts/deploy-production.sh`)?
