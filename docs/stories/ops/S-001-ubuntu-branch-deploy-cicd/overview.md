# S-001 Ubuntu Branch Deploy CI/CD

## Current Behavior

Swinx deployment to the Ubuntu PHP server is manual. The operator SSHes into
`root@157.10.186.103`, changes into each deployed source directory, and runs the
Laravel/PHP/frontend commands by hand.

Known target paths from the operator request:

- Dev: `/www/wwwroot/dev-x.asia-vn.edu.vn/asia-admin-portal/`
- Production: `/www/wwwroot/x.metropolia.edu.vn/asia-admin-portal/`
- Production/shared domain path to confirm: `/www/wwwroot/x.asia-vn.edu.vn/public_html/`

The repository currently has GitHub workflow files, but they are commented out
and not active.

## Target Behavior

Pushes to the development branch automatically deploy the dev source so the
team can test immediately. After dev is stable and merged to `main`, pushes to
`main` deploy the production source path(s) on the same server.

The CI/CD flow should use the existing Ubuntu host model and existing host
binaries such as `php84`, `composer84`, and `pnpm`, rather than introducing a
Docker deployment model.

Portal impact: none

## Affected Users

- Platform/operator maintaining Swinx deployments.
- Admin/staff users indirectly affected by deployment downtime or failed deploys.

## Affected Product Docs

- `docs/deployment-guide.md`
- `.github/workflows/deploy.yml`
- A future deploy helper script if approved.

## Non-Goals

- No Docker migration.
- No API contract changes.
- No student or lecturer portal code changes.
- No database schema changes beyond running already-existing migrations during deploy.
- No automatic rollback implementation in the first slice unless explicitly approved.
