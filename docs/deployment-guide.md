---
title: Swinx Deployment Guide
status: canonical
owner: Platform Team
last_verified: 2026-07-25
scope: deployment-operations
---

# Swinx Deployment Guide

## Operating rule

Use repository scripts. Do not reconstruct Docker Compose or Artisan commands
from memory, and do not run host `php artisan` for Docker-managed environments.

Use `.env.example` as the application environment template. Never commit `.env`,
provider secrets, database dumps, OAuth keys, or generated credentials.

## Deployment modes

| Mode | Script | Compose file | Purpose |
| --- | --- | --- | --- |
| Development | `scripts/dev.sh` | `docker/docker-compose.dev.yml` | Local coding, HMR, tests |
| Local production | `scripts/local-prod.sh` | `docker/docker-compose.local-prod.yml` | Production-style local verification |
| Host proxy | `scripts/host.sh` | `docker/docker-compose.host.yml` | aaPanel/nginx/Cloudflare in front of one host port |
| Full production | `scripts/prod.sh` | `docker/docker-compose.production.yml` | Dedicated host with container-managed TLS |

FrankenPHP is the application runtime. Redis backs cache/queues and MySQL stores
transactional state.

## Development

```bash
cp .env.example .env
./scripts/dev.sh start
./scripts/dev.sh status
./scripts/dev.sh logs app
./scripts/dev.sh artisan key:generate
./scripts/dev.sh artisan migrate
./scripts/dev.sh npm run dev
```

Common commands:

```bash
./scripts/dev.sh shell
./scripts/dev.sh mysql
./scripts/dev.sh artisan <command>
./scripts/dev.sh composer <command>
./scripts/dev.sh npm <command>
./scripts/dev.sh test --filter=<test>
```

Stop or rebuild:

```bash
./scripts/dev.sh stop
./scripts/dev.sh restart
./scripts/dev.sh rebuild
```

## Local production verification

Use this mode to exercise built assets and production-style services locally:

```bash
./scripts/local-prod.sh start
./scripts/local-prod.sh status
./scripts/local-prod.sh logs app
./scripts/local-prod.sh test-ssl
```

Database and Redis remain private to the Compose network. Local production does
not automatically import application data.

## Host-proxy deployment

Use when an existing reverse proxy or Cloudflare terminates the public request:

```bash
cp .env.example .env
./scripts/host.sh deploy
./scripts/host.sh status
./scripts/host.sh health
```

Point the reverse proxy at `HOST_APP_PORT`. The example nginx configuration is
`docker/nginx-proxy-snippet.conf`.

Required checks:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` matches the public origin
- trusted proxy configuration matches the deployment
- database/Redis ports are not exposed publicly
- queue and scheduler containers are healthy

## Full production deployment

Use when Docker owns public ports and Caddy/FrankenPHP manages TLS:

```bash
cp .env.example .env
./scripts/prod.sh deploy
./scripts/prod.sh status
./scripts/prod.sh health
```

Confirm DNS, server name, ACME email, firewall, persistent volumes, and backup
location before deployment.

## Ubuntu branch deployment

`scripts/deploy-ubuntu.sh` and the repository deployment workflow support the
current Ubuntu host flow. Before use:

1. Verify the target branch and clean server worktree.
2. Verify environment and persistent storage.
3. Back up the production database.
4. Deploy through the approved script/workflow.
5. Run health, queue, scheduler, and application smoke checks.

Do not select among `deploy.sh`, `deploy-production.sh`, and
`deploy-ubuntu.sh` by filename alone. Inspect the active workflow and server
configuration first.

## Database backup

Use the environment wrapper:

```bash
./scripts/local-prod.sh backup
./scripts/host.sh backup
./scripts/prod.sh backup
```

The underlying backup helper is `scripts/backup-database.sh`.

Before destructive migration or restore:

1. Create a fresh backup.
2. Confirm the target database and backup path explicitly.
3. Store production backups outside container lifecycle.
4. Test restore in a non-production environment.
5. Record migration and integrity verification results.

Do not auto-import initialization SQL on application or database restart.

## Build and migration checks

```bash
./scripts/dev.sh npm run build
./scripts/dev.sh artisan migrate:status
./scripts/dev.sh artisan schedule:list
./scripts/dev.sh artisan queue:monitor redis:default,redis:emails,redis:bulk-emails,redis:notifications,redis:finance,redis:webhooks,redis:reconciliation
```

Production migrations must be deliberate and use the production wrapper.
Inspect migration debt before and after migration work:

```bash
./scripts/dev.sh artisan migration-debt:inventory --check --format=table
```

## Health verification

Expected application endpoints:

- `/up`
- `/health`
- `/api/health`

Also verify:

- queue workers are consuming expected queues;
- scheduler is running;
- Redis and MySQL are healthy;
- logs contain no new boot or migration errors;
- built frontend assets are present;
- authentication redirects and secure cookies use the public origin.

## MCP deployment

Controlled MCP has additional OAuth, proxy, Cloudflare, and connector checks.
Follow `docs/features/ai/mcp.md`.

## Rollback

Rollback is environment-specific:

- Prefer redeploying the last known-good image/commit.
- Do not reverse irreversible data migrations without an approved repair plan.
- Restore a database only after confirming the exact target and recovery point.
- Preserve provider callbacks and payment evidence during application rollback.

Record what changed, the rollback boundary, and post-rollback verification.

## Troubleshooting

| Symptom | First checks |
| --- | --- |
| Asset missing from Vite manifest | Build assets or run the dev server |
| Queue work not processing | Worker health, queue name, Redis connectivity |
| Wrong redirects/cookies | `APP_URL`, trusted proxy, forwarded headers |
| Database connection failure | Compose service health and environment values |
| OAuth/MCP discovery failure | `docs/features/ai/mcp.md` |
| Portal contract mismatch | `docs/portal-repos.md` and matching API docs |
