# Exec Plan

## Goal

Create a safe, branch-based CI/CD workflow for Swinx on the existing Ubuntu PHP
server so routine dev and production deployments no longer require manual SSH
command sequences.

## Scope

In scope:

- GitHub Actions deployment workflow.
- Branch-to-server-path mapping.
- Safe server-side deploy command sequence for Laravel + Vite assets.
- Documentation for required GitHub secrets and server prerequisites.
- Dev deploy first, production deploy after approval.

Out of scope:

- Docker production migration.
- Database restore/rollback automation.
- Portal repo deployment.
- API/frontend feature behavior changes.

## Risk Classification

Risk flags:

- Audit/security.
- External systems.
- Existing behavior.
- Weak proof.
- Multi-domain.

Hard gates:

- Production deploy automation.
- Migration execution on live environments.
- SSH secret handling.

## Work Phases

1. Confirm branch names and exact production path behavior.
2. Implement server-side deploy helper and GitHub Actions workflow.
3. Document required GitHub secrets and server prerequisites.
4. Run syntax checks only after explicit approval.
5. Trigger dev deploy first.
6. Deploy production from `main`; run production migrations only through manual dispatch.
7. Record trace and any backlog items.

## Stop Conditions

Pause for human confirmation if:

- The production target should deploy to one path vs two paths.
- The development branch is not `dev` or `develop`.
- Production migrations should require manual approval instead of running automatically.
- The server paths are not git worktrees.
- Any unrelated dirty server-side change would be overwritten by checkout/reset.
