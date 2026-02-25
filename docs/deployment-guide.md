# Deployment Guide (Current Baseline)

Last updated: 2026-02-25  
Owner: Platform Team  
Status: Risk-tracked baseline (not fully normalized)

## 1) Existing Deployment Assets

- Dockerfiles:
  - `docker/Dockerfile`
  - `docker/Dockerfile.production`
- Compose files:
  - `docker/docker-compose.dev.yml`
  - `docker/docker-compose.local-prod.yml`
  - `docker/docker-compose.production.yml`
- Caddy/runtime configs:
  - `docker/Caddyfile.dev`
  - `docker/Caddyfile.local-prod`
  - `docker/Caddyfile.prod`
- Scripts:
  - `scripts/dev.sh`, `scripts/local-prod.sh`, `scripts/prod.sh`
  - `scripts/deploy.sh`, `scripts/deploy-production.sh`
  - `scripts/backup-database.sh`, `scripts/monitor-production.sh`

## 2) Reliable Local Path Today

Use native app bootstrap for predictable local run:

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
composer dev
```

## 3) Verified Drift and Security Risks

### Script and path drift

- Missing scripts referenced by `package.json` and setup/pre-push flows:
  - `scripts/test-local.sh`
  - `scripts/docker-compose-dev.sh`
- Multiple scripts expect compose files at repo root while actual compose files are under `docker/`.

### CI status

- `.github/workflows/deploy.yml`, `lint.yml`, `tests.yml` are fully commented out (disabled).

### Security exposure risks in deployment artifacts

- Hardcoded credentials/defaults remain in deploy/runtime scripts and compose fallbacks.
- Production compose files publish DB port (`3306:3306`) by default.
- Production startup logic and runtime assumptions are inconsistent across scripts.

## 4) Minimum Readiness Checklist Before Production Use

1. Standardize script paths to `docker/*` assets.
2. Remove hardcoded credentials and weak default secrets.
3. Restrict or remove public DB port exposure for production.
4. Re-enable CI with required lint/type/test gates.
5. Run smoke checks for web auth, student/lecturer auth, and finance/academic critical flows.

## 5) Canonical Deployment Contract (Pending Decision)

Target contract after normalization:
- Compose files remain under `docker/`.
- Scripts under `scripts/` reference only canonical compose paths.
- Environment file locations are explicitly defined per environment.
- Deploy pipeline requires passing CI checks before rollout.

## 6) Rollback and Backup Expectations

Required baseline policy:
- backup before deploy
- deterministic version/image pinning
- health-check gate before traffic cutover
- tested rollback command in staging

## Unresolved Questions

- Which script is canonical for production deployment?
- Should production DB ever be host-exposed, or only internal-network reachable?
- Should `Caddyfile.prod` features be reduced to guaranteed baseline compatibility?
