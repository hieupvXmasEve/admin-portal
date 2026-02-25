# Ops/Deployment/CI Drift Scout Report

Date: 2026-02-25
Scope: `scripts/`, `docker/`, `.github/workflows`, runtime-security-relevant config

## Active risks

1. Critical: hardcoded production credentials in repo and runtime paths.
- `scripts/deploy-production.sh:105` uses fixed MySQL root password.
- `scripts/backup-database.sh:65` uses fixed MySQL root password.
- `scripts/local-prod.sh:132` and `scripts/local-prod.sh:165` expose DB password on command line.
- `scripts/monitor-production.sh:92` and `scripts/monitor-production.sh:104` expose DB/Redis credentials.
- `docker/docker-compose.production.yml:50` and `docker/docker-compose.production.yml:53` include weak fallback prod secrets.
- `docker/docker-compose.local-prod.yml:58` and `docker/docker-compose.local-prod.yml:61` include weak fallback prod secrets.

2. High: CI is effectively disabled.
- All workflows are fully commented out: `.github/workflows/tests.yml:1`, `.github/workflows/lint.yml:1`, `.github/workflows/deploy.yml:1`.
- Result: no enforced lint/test/type/deploy guardrail on push/PR.

3. High: production DB is exposed publicly by default.
- `docker/docker-compose.production.yml:48` publishes `3306:3306`.
- `docker/docker-compose.local-prod.yml:56` publishes `3306:3306`.

4. High: production startup logic is broken/inconsistent.
- `docker/start-frankenphp-production.sh:26` compares against undefined `max_attempts`.
- Script hard-skips DB check (`docker/start-frankenphp-production.sh:14-16`) and can skip migrations path unpredictably.

5. Medium: possible production webserver config incompatibility.
- `docker/Caddyfile.prod:101` uses `rate_limit` while local-prod file notes this feature is not available in standard FrankenPHP/Caddy path.
- Risk: production boot/runtime config error if plugin missing.

6. Medium: mutable base images increase supply-chain drift.
- `docker/Dockerfile:6` and `docker/Dockerfile.production:6` use `dunglas/frankenphp:latest`.
- `docker/Dockerfile:41` and `docker/Dockerfile.production:56` use `composer:latest`.

7. Medium: sample env contains static app key and weak defaults.
- `.env.example:11` ships a concrete APP_KEY.
- `.env.example:22` keeps weak DB password default.

## Command drift

1. Missing scripts referenced by package and setup flow.
- `package.json:15-18` references `scripts/test-local.sh` and `scripts/docker-compose-dev.sh` (missing).
- `scripts/setup-project.sh:84`, `scripts/setup-project.sh:99`, `scripts/setup-project.sh:110` call missing scripts.
- `scripts/pre-push.sh:50` calls missing `scripts/test-local.sh`.

2. Compose path drift: scripts expect root files; actual files are in `docker/`.
- Example root expectations: `scripts/dev.sh:18`, `scripts/local-prod.sh:18`, `scripts/prod.sh:18`, `scripts/deploy.sh:12`, `scripts/deploy-production.sh:12`, `scripts/backup-database.sh:11`, `scripts/monitor-production.sh:11`.
- Actual compose files: `docker/docker-compose.dev.yml`, `docker/docker-compose.local-prod.yml`, `docker/docker-compose.production.yml`.

3. Validation script targets non-existent root assets.
- `scripts/validate-setup.sh:92-99` requires root `docker-compose.yml`, `docker-compose.dev.yml`, `docker-compose.test.yml`, `Dockerfile`, `env.docker.example`.
- `scripts/validate-setup.sh:136-139` requires missing scripts.
- `scripts/validate-setup.sh:159-180` validates non-existent root compose files.

4. Production helper checks wrong file locations.
- `scripts/prod.sh:77` expects root `Dockerfile.production` and `Caddyfile.prod` (actual in `docker/`).

5. Legacy server setup assumes nginx service that does not exist in current compose.
- `scripts/server-setup.sh:184` and `scripts/server-setup.sh:213` restart/exec `nginx` service via compose.

## Documentation alignment needs

1. Baseline drift documentation is correct, but incomplete on runtime security findings.
- Already aligned on disabled workflows + missing scripts: `README.md:104-109`, `docs/deployment-guide.md:45-56`, `docs/codebase-summary.md:75-77`.
- Missing from docs: hardcoded credentials, exposed DB ports, mutable `latest` image tags, broken prod startup script.

2. Docker docs overstate production hardening.
- `docker/README.md` claims production startup has security hardening, but current startup script has broken DB/migration logic and credential exposure in surrounding scripts.

3. Canonical deployment contract still undecided and blocking remediation.
- `docs/deployment-guide.md:88-90` unresolved canonical path question is still active blocker for path normalization.

## Unresolved questions

1. Which single deployment entrypoint is canonical now: `scripts/prod.sh`, `scripts/deploy.sh`, or `scripts/deploy-production.sh`?
2. Should prod DB port publishing be removed entirely, or restricted to trusted network only?
3. Should Caddy `rate_limit` stay (with pluginized runtime) or be removed for base FrankenPHP compatibility?
4. Are weak secret fallbacks in compose acceptable for local-only, or must all secret defaults be removed repo-wide?
