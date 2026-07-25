---
title: Swinx Script Index
status: canonical
owner: Platform Team
last_verified: 2026-07-25
scope: repository-scripts
---

# Swinx Script Index

Use repository wrappers instead of reconstructing Docker, Artisan, or deployment
commands manually.

## Development

| Script | Purpose |
| --- | --- |
| `dev.sh` | Development Compose lifecycle and application commands |
| `dev-env.sh` | Development environment support |
| `setup-project.sh` | Initial project setup |
| `validate-setup.sh` | Setup validation |
| `reset-local-asia-db.sh` | Explicit local Asia database reset |
| `reset-local-dev-asia-db.sh` | Explicit local development database reset |

Common usage:

```bash
./scripts/dev.sh start
./scripts/dev.sh status
./scripts/dev.sh artisan <command>
./scripts/dev.sh composer <command>
./scripts/dev.sh npm <command>
./scripts/dev.sh test --filter=<test>
```

Database reset scripts are destructive. Resolve and verify the exact local
target before running them.

## Deployment and operations

| Script | Purpose |
| --- | --- |
| `local-prod.sh` | Local production-style Compose lifecycle |
| `host.sh` | Host-proxy deployment lifecycle |
| `prod.sh` | Full production Compose lifecycle |
| `deploy-ubuntu.sh` | Ubuntu branch deployment helper |
| `deploy.sh` | Existing deployment helper; inspect active workflow before use |
| `deploy-production.sh` | Existing production helper; inspect active workflow before use |
| `server-setup.sh` | Server bootstrap |
| `backup-database.sh` | Database backup implementation |
| `monitor-production.sh` | Production monitoring helper |
| `pre-push.sh` | Repository pre-push validation |

See `docs/deployment-guide.md` before production operations.

## Integrations

| Script | Purpose |
| --- | --- |
| `portal-status.sh` | Read-only status for Swinx and nested Nuxt portals |
| `fix-oauth-key-permissions.sh` | Repair OAuth key permissions |
| `validate-email-deployment.sh` | Validate email deployment configuration |

Run `./scripts/portal-status.sh` before student- or lecturer-facing API changes.

## Documentation

| Script | Purpose |
| --- | --- |
| `check-docs.sh` | Validate canonical docs, metadata, links, ADR IDs, and grading JSON |

```bash
./scripts/check-docs.sh
```
