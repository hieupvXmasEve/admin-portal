# Deployment Guide (Current Baseline)

Last updated: 2026-02-23

This guide documents the repository's current deployment assets and their current reliability status. It does not assume all scripts are production-ready.

## 1) What Exists Today

Deployment-related assets:
- Dockerfiles:
  - `docker/Dockerfile`
  - `docker/Dockerfile.production`
- Compose files:
  - `docker/docker-compose.dev.yml`
  - `docker/docker-compose.local-prod.yml`
  - `docker/docker-compose.production.yml`
- Runtime/server configs:
  - `docker/Caddyfile.dev`
  - `docker/Caddyfile.local-prod`
  - `docker/Caddyfile.prod`
- Operational scripts:
  - `scripts/dev.sh`, `scripts/local-prod.sh`, `scripts/prod.sh`
  - `scripts/deploy.sh`, `scripts/deploy-production.sh`
  - `scripts/backup-database.sh`, `scripts/monitor-production.sh`

## 2) Recommended Safe Workflow Right Now

Until script/path drift is resolved, treat native app boot as the reliable baseline:

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
composer dev
```

Use this for local validation and feature testing.

## 3) Known Deployment/Script Drift

The following are verified mismatches and should be fixed before relying on automated deploy scripts:

- `package.json` references `./scripts/test-local.sh`, but this file is missing.
- `scripts/pre-push.sh` calls `./scripts/test-local.sh` (missing).
- `scripts/setup-project.sh` calls `./scripts/docker-compose-dev.sh` (missing).
- `scripts/validate-setup.sh` expects root-level files such as:
  - `docker-compose.yml`
  - `docker-compose.dev.yml`
  - `docker-compose.test.yml`
  - `Dockerfile`
  - `env.docker.example`
  These files are not present at root.
- Several scripts expect compose files at root (`docker-compose.production.yml`) while actual files are under `docker/`.

## 4) Production Readiness Checklist (Before Go-Live)

1. Standardize file paths in all deployment scripts.
2. Define canonical env file locations and sample files.
3. Re-enable and verify CI workflows in `.github/workflows/`.
4. Verify queue workers, scheduler, and backup/restore routines.
5. Run end-to-end smoke checks on:
   - web login and campus selection
   - student/lecturer API auth
   - finance and academic critical flows

## 5) Suggested Canonical Deployment Contract

Adopt one source of truth for each concern:
- Compose files: keep under `docker/`.
- Wrapper scripts: keep under `scripts/` and reference `docker/*` explicitly.
- Env files: define and document one canonical location per environment.
- CI: enforce lint/type-check/tests before deployment.

## 6) Rollback and Backup Expectations

Existing backup/monitor scripts exist but include hardcoded assumptions and should be reviewed before production usage.

Minimum rollback policy to document in implementation phase:
- database backup before deploy
- image/version pinning
- health check gate before switch-over
- rollback command path tested in staging

## Unresolved Questions

- Which deployment path should be canonical: `scripts/prod.sh`, `scripts/deploy.sh`, or `scripts/deploy-production.sh`?
- Should Docker files be moved to root-compatible paths, or should scripts be updated to `docker/*` paths?
- Which environments are officially supported today: native only, Docker dev, local-prod, production, or all?
