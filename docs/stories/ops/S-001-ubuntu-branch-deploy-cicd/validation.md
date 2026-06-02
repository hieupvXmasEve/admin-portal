# Validation

## Proof Strategy

Prove the workflow is syntactically correct, the deploy script is shell-safe, and
server deploy commands can be run against dev before enabling production deploy.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | No app unit tests required for workflow-only change. |
| Integration | GitHub Actions dry run or manual `workflow_dispatch` to dev. |
| E2E | Browser/API smoke check against dev domain after deploy. |
| Platform | SSH connection, target path, binary availability: `git`, `php84`, `composer84`, `pnpm`. |
| Performance | Confirm build duration is acceptable on server. |
| Logs/Audit | GitHub Actions logs include target path, commit, migration status, and health result. |

## Fixtures

- Server: `root@157.10.186.103`
- Dev path: `/www/wwwroot/dev-x.asia-vn.edu.vn/asia-admin-portal/`
- Production path: `/www/wwwroot/x.metropolia.edu.vn/asia-admin-portal/`
- Additional production/shared path to confirm: `/www/wwwroot/x.asia-vn.edu.vn/public_html/`

## Commands

Candidate validation commands after implementation:

```text
bash -n scripts/deploy-ubuntu.sh
./scripts/harness query matrix
```

GitHub Actions proof after secrets are configured:

```text
workflow_dispatch target=dev run_migrations=false
push dev
push main
workflow_dispatch target=production run_migrations=true
```

Server-side smoke commands to run through CI or manually:

```text
git status --short
php84 artisan migrate:status
php84 artisan about
```

## Acceptance Evidence

Implementation artifacts are prepared. Validation has not been run yet in this
turn; run syntax checks and a dev workflow dispatch after GitHub secrets are
configured.
