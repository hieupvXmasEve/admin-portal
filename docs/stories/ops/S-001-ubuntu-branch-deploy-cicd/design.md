# Design

## Domain Model

This is an operational workflow change, not a product-domain model change.

## Application Flow

Proposed flow:

1. GitHub Actions listens for branch pushes and manual dispatch.
2. `dev`/`develop` deploys to `/www/wwwroot/dev-x.asia-vn.edu.vn/asia-admin-portal/`.
3. `main` deploys to the production path(s).
4. The workflow connects to `root@157.10.186.103` using a GitHub Actions SSH key
   secret.
5. On the server, the deploy command enters the configured path, confirms it is
   a git worktree, fetches the branch, checks out the target commit, installs PHP
   dependencies, installs frontend dependencies, builds assets, clears and
   rebuilds Laravel caches, checks migration status, optionally runs migrations,
   and restarts Laravel queue workers.
6. Health checks call `/up` or `/health` after deploy.

## Interface Contract

Expected GitHub secrets:

- `DEPLOY_HOST`: `157.10.186.103`
- `DEPLOY_USER`: `root`
- `DEPLOY_SSH_KEY`: private key allowed to SSH into the server
- `DEPLOY_PORT`: optional, defaults to `22`

Branch/path mapping:

- Development branch: `dev` to `/www/wwwroot/dev-x.asia-vn.edu.vn/asia-admin-portal/`.
- Production branch: `main` to `/www/wwwroot/x.metropolia.edu.vn/asia-admin-portal/`.
- Production branch: `main` to `/www/wwwroot/x.asia-vn.edu.vn/public_html/`.

## Data Model

No schema changes are introduced. Deploy may run existing Laravel migrations.
Production auto-migration is disabled for push-triggered deploys. It can be
enabled only from manual workflow dispatch with `run_migrations=true`.

## UI / Platform Impact

The implementation should create an operational GitHub Actions workflow and,
optionally, a repo-local shell helper for safe server-side deploy steps.

## Observability

Deploy logs should include:

- branch and commit SHA
- target environment
- target path
- dependency install/build progress
- migration status
- cache clear/build status
- health check result

## Alternatives Considered

1. Build on server after `git fetch` and checkout.
2. Build assets in GitHub Actions and upload release artifacts by `rsync`.
3. Build Docker images and deploy containers.

Recommended first slice is option 1 because it matches the current server model
and minimizes infrastructure change.
