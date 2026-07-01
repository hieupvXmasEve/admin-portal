# Deployment Guide

Last updated: 2026-03-23  
Owner: Platform Team  
Status: Operational baseline

## 1) Scope

This guide documents the actual scripts used in this repo:

- dev stack: [`scripts/dev.sh`](/Users/hunt2412/hieupvdev/project/swinx/scripts/dev.sh)
- local production-like stack: [`scripts/local-prod.sh`](/Users/hunt2412/hieupvdev/project/swinx/scripts/local-prod.sh)
- production stack: [`scripts/prod.sh`](/Users/hunt2412/hieupvdev/project/swinx/scripts/prod.sh)
- Ubuntu host deploy helper: [`scripts/deploy-ubuntu.sh`](/Users/hunt2412/hieupvdev/project/swinx/scripts/deploy-ubuntu.sh)
- production DB backup: [`scripts/backup-database.sh`](/Users/hunt2412/hieupvdev/project/swinx/scripts/backup-database.sh)
- server bootstrap: [`scripts/server-setup.sh`](/Users/hunt2412/hieupvdev/project/swinx/scripts/server-setup.sh)

## 2) General Rules

- Use [`.env.example`](/Users/hunt2412/hieupvdev/project/swinx/.env.example) as the only template.
- `dev`, `local-prod`, and `production` each use a different compose file and project name.
- Production must not auto-import `init.sql` when the app or DB restarts.
- Database durability comes from the persistent DB volume plus SQL backups stored outside the container lifecycle.

## 3) Prepare `.env`

Create `.env` from template:

```bash
cp .env.example .env
```

Minimum production-oriented values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_HOST=db
DB_PORT=3306
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_password
DB_ROOT_PASSWORD=your_root_password

REDIS_HOST=redis
REDIS_PORT=6379

QUEUE_CONNECTION=redis
QUEUE_WORKER_QUEUES=default,emails,notifications
QUEUE_WORKER_SLEEP=1
QUEUE_WORKER_TRIES=3

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls

SERVER_NAME=your-domain.com
ACME_EMAIL=admin@your-domain.com
RUN_MIGRATIONS=false
BACKUP_RETENTION_DAYS=14
```

Important values:

- `DB_ROOT_PASSWORD`: used for DB health checks and production backup dumps.
- `RUN_MIGRATIONS`: image/runtime flag, but `prod.sh deploy` still runs migrations explicitly.
- `BACKUP_RETENTION_DAYS`: how many days compressed SQL backups are kept in `backups/db/`.

## 4) Development Stack

Script: [`scripts/dev.sh`](/Users/hunt2412/hieupvdev/project/swinx/scripts/dev.sh)  
Compose: [`docker/docker-compose.dev.yml`](/Users/hunt2412/hieupvdev/project/swinx/docker/docker-compose.dev.yml)

What it starts:

- `app`
- `vite`
- `queue`
- `scheduler`
- `db`
- `redis`
- `mailpit`

Start dev:

```bash
./scripts/dev.sh start
```

Stop dev:

```bash
./scripts/dev.sh stop
```

Restart dev:

```bash
./scripts/dev.sh restart
```

Rebuild from scratch:

```bash
./scripts/dev.sh rebuild
```

See status:

```bash
./scripts/dev.sh status
```

See logs:

```bash
./scripts/dev.sh logs
./scripts/dev.sh logs app
./scripts/dev.sh logs db
```

Open shell inside app:

```bash
./scripts/dev.sh shell
```

Run artisan:

```bash
./scripts/dev.sh artisan migrate
./scripts/dev.sh artisan schedule:list
./scripts/dev.sh artisan queue:work
```

Run composer:

```bash
./scripts/dev.sh composer install
```

Run npm:

```bash
./scripts/dev.sh npm run build
```

Run tests:

```bash
./scripts/dev.sh test
./scripts/dev.sh test --filter=Identity
```

Open MariaDB shell:

```bash
./scripts/dev.sh mysql
```

Default dev endpoints:

- app: `http://localhost:8000`
- vite: `http://localhost:5173`
- mailpit UI: `http://localhost:8026`

## 5) Local Production-Like Stack

Script: [`scripts/local-prod.sh`](/Users/hunt2412/hieupvdev/project/swinx/scripts/local-prod.sh)  
Compose: [`docker/docker-compose.local-prod.yml`](/Users/hunt2412/hieupvdev/project/swinx/docker/docker-compose.local-prod.yml)

Use this when you want to test production-style behavior locally with FrankenPHP + HTTPS.

Important behavior:

- `local-prod` is intentionally production-like
- only the web app is exposed on host ports
- `db` and `redis` stay private inside the Docker network
- `mailpit` is not part of `local-prod`
- database starts empty unless you explicitly import a dump yourself

Start:

```bash
./scripts/local-prod.sh start
```

Stop:

```bash
./scripts/local-prod.sh stop
```

Restart:

```bash
./scripts/local-prod.sh restart
```

Rebuild:

```bash
./scripts/local-prod.sh rebuild
```

See status:

```bash
./scripts/local-prod.sh status
```

See logs:

```bash
./scripts/local-prod.sh logs
./scripts/local-prod.sh logs app
```

Open shell:

```bash
./scripts/local-prod.sh shell
```

Run artisan:

```bash
./scripts/local-prod.sh artisan migrate --force
```

Open MariaDB shell:

```bash
./scripts/local-prod.sh mysql
```

Check HTTPS:

```bash
./scripts/local-prod.sh test-ssl
```

Create a manual local-prod backup:

```bash
./scripts/local-prod.sh backup
```

Default local production endpoint:

- `https://localhost:8443/up`

## 6) Production Stack

Script: [`scripts/prod.sh`](/Users/hunt2412/hieupvdev/project/swinx/scripts/prod.sh)  
Compose: [`docker/docker-compose.production.yml`](/Users/hunt2412/hieupvdev/project/swinx/docker/docker-compose.production.yml)

What it starts:

- `app`
- `queue`
- `scheduler`
- `db`
- `redis`

Important behavior:

- only the web app is exposed publicly
- `db` and `redis` are private services inside the Docker network
- app containers always reach them through `DB_HOST=db` and `REDIS_HOST=redis`

Primary deploy:

```bash
./scripts/prod.sh deploy
```

What `deploy` does:

1. validates `.env`
2. asks for explicit `PRODUCTION` confirmation
3. creates `backups/` and `logs/`
4. builds and starts the production stack
5. runs `php artisan migrate --force`

Start existing production stack:

```bash
./scripts/prod.sh start
```

Stop:

```bash
./scripts/prod.sh stop
```

Restart:

```bash
./scripts/prod.sh restart
```

See status:

```bash
./scripts/prod.sh status
```

See logs:

```bash
./scripts/prod.sh logs
./scripts/prod.sh logs app
./scripts/prod.sh logs db
```

Open shell:

```bash
./scripts/prod.sh shell
```

Open MariaDB shell:

```bash
./scripts/prod.sh mysql
```

Run artisan:

```bash
./scripts/prod.sh artisan optimize:clear
./scripts/prod.sh artisan queue:restart
```

Health check:

```bash
./scripts/prod.sh health
curl -fsS https://your-domain.com/up
```

## 7) Manual SQL Import

Important rule:

- local/dev: you may import SQL manually whenever needed
- production: do not auto-import `init.sql` on app start or container restart

Manual import into the running dev DB:

```bash
docker exec -i swinx-db-dev mariadb -uroot -proot asia < docker/mysql/init.sql
```

Manual import of another dump file:

```bash
docker exec -i swinx-db-dev mariadb -uroot -proot asia < /path/to/your.sql
```

If you want `init-test.sql` to match `init.sql`:

```bash
cp docker/mysql/init.sql docker/mysql/init-test.sql
```

If the DB volume already exists, MariaDB will not rerun `/docker-entrypoint-initdb.d/*` automatically.  
In that case, either import manually as above, or destroy the DB volume and recreate the stack.

Reset dev DB volume completely:

```bash
./scripts/dev.sh stop
docker compose --env-file .env -f docker/docker-compose.dev.yml -p swinx-dev down -v
./scripts/dev.sh start
```

## 8) Production Backup

Manual production backup:

```bash
./scripts/backup-database.sh
```

What it does:

- calls [`scripts/prod.sh`](/Users/hunt2412/hieupvdev/project/swinx/scripts/prod.sh) in `backup` mode
- dumps production DB using:
  - `--single-transaction`
  - `--routines`
  - `--triggers`
- uses MySQL root credentials from `.env`
- writes SQL file into `backups/db/`
- compresses the result as `.sql.gz`
- deletes old backups older than `BACKUP_RETENTION_DAYS`

Generated file format:

```text
backups/db/production-YYYYMMDD_HHMMSS.sql.gz
```

Manual raw backup without wrapper script:

```bash
./scripts/prod.sh backup
```

Or custom path:

```bash
./scripts/prod.sh backup backups/db/custom-name.sql
```

## 9) Backup Commands By Environment

Each environment has its own MariaDB container and its own data volume.

Container mapping:

- dev: `swinx-db-dev`
- local-prod: `swinx-db-local-prod`
- production: `swinx-db`

Backup `dev`:

```bash
docker exec swinx-db-dev mariadb-dump -uroot -proot asia > backups/dev-$(date +%Y%m%d_%H%M%S).sql
```

Backup `local-prod`:

```bash
./scripts/local-prod.sh backup
```

Or directly:

```bash
docker exec swinx-db-local-prod mariadb-dump -uroot -proot asia > backups/local-prod-$(date +%Y%m%d_%H%M%S).sql
```

Backup `production`:

```bash
./scripts/backup-database.sh
```

Or directly:

```bash
docker exec swinx-db mariadb-dump --single-transaction --routines --triggers -uroot -p'YOUR_ROOT_PASSWORD' your_db > backups/db/production-$(date +%Y%m%d_%H%M%S).sql
```

Important:

- backup only saves the current state of that environment's DB
- if `local-prod` DB is empty, its backup file will also be nearly empty
- importing data into `dev` does not make `local-prod` or `production` have the same data
- `local-prod` and `production` do not publish DB or Redis ports to the host

## 10) Production Backup Schedule

Default schedule:

- `00:00` every day
- `12:00` every day

Installed by [`scripts/server-setup.sh`](/Users/hunt2412/hieupvdev/project/swinx/scripts/server-setup.sh):

```cron
0 0,12 * * * appuser cd /path/to/app && ./scripts/backup-database.sh >> /path/to/app/logs/db-backup.log 2>&1
```

This means:

- backups run from the host
- backups survive app container restart
- the SQL dump is stored under the project `backups/` directory on disk

## 11) Restore From Backup

Restore a production-style dump into a running MariaDB container:

```bash
gunzip -c backups/db/production-20260323_120000.sql.gz | docker exec -i swinx-db mariadb -uroot -p'YOUR_ROOT_PASSWORD' your_db
```

Restore an uncompressed SQL file:

```bash
docker exec -i swinx-db mariadb -uroot -p'YOUR_ROOT_PASSWORD' your_db < backups/db/production-20260323_120000.sql
```

Restore into local dev DB:

```bash
gunzip -c backups/db/production-20260323_120000.sql.gz | docker exec -i swinx-db-dev mariadb -uroot -proot asia
```

Before restore, verify:

- target DB name
- root password
- whether you are overwriting live production data

## 12) Dev DB Restore

Use this when you want to restore the `dev` database from a backup file and replace the current `dev` DB state completely.

Important:

- this is a clean restore, not an import on top of the current DB
- the current `dev` DB volume will be deleted
- if you want rollback safety, create one more backup first

Optional safety backup before restore:

```bash
docker exec swinx-db-dev mariadb-dump -uroot -proot asia > backups/dev-before-restore-$(date +%Y%m%d_%H%M%S).sql
```

Clean restore flow for a `.sql` file:

```bash
./scripts/dev.sh stop
docker compose --env-file .env -f docker/docker-compose.dev.yml -p swinx-dev down -v
./scripts/dev.sh start
docker exec -i swinx-db-dev mariadb -uroot -proot asia < backups/dev-20260323_161405.sql
```

If the backup file is `.sql.gz`:

```bash
./scripts/dev.sh stop
docker compose --env-file .env -f docker/docker-compose.dev.yml -p swinx-dev down -v
./scripts/dev.sh start
gunzip -c backups/dev-20260323_161405.sql.gz | docker exec -i swinx-db-dev mariadb -uroot -proot asia
```

Verify after restore:

```bash
docker exec swinx-db-dev mariadb -uroot -proot asia -e "SHOW TABLES; SELECT COUNT(*) AS users_count FROM users;"
```

Notes:

- `down -v` deletes the `dev` DB volume and wipes current `dev` data
- `./scripts/dev.sh start` recreates the empty DB service
- the final `docker exec ... < backup.sql` loads the backup into the fresh DB

## 13) Production Topology

- `app`: web traffic via FrankenPHP + Caddy
- `queue`: background jobs
- `scheduler`: scheduled commands
- `db`: MariaDB with persistent `db_data` volume
- `redis`: cache, session, and queue backend

This runtime model replaces host supervisor for app processes.  
Host cron is still used for infrastructure tasks such as database backups and cert renewal.

## 14) Quick Command Reference

Dev:

```bash
./scripts/dev.sh start
./scripts/dev.sh status
./scripts/dev.sh logs app
./scripts/dev.sh artisan migrate
./scripts/dev.sh mysql
```

Local production:

```bash
./scripts/local-prod.sh start
./scripts/local-prod.sh status
./scripts/local-prod.sh test-ssl
./scripts/local-prod.sh backup
```

Production:

```bash
./scripts/prod.sh deploy
./scripts/prod.sh status
./scripts/prod.sh logs app
./scripts/prod.sh artisan queue:restart
./scripts/backup-database.sh
```

## 15) Ubuntu Host Branch CI/CD

Workflow: [`.github/workflows/deploy.yml`](/Users/hunt2412/hieupvdev/project/swinx/.github/workflows/deploy.yml)  
Server helper: [`scripts/deploy-ubuntu.sh`](/Users/hunt2412/hieupvdev/project/swinx/scripts/deploy-ubuntu.sh)

This CI/CD path is for the current Ubuntu/PHP host deployment model. It does
not use Docker on the server. GitHub Actions connects over SSH, checks out the
target branch in the existing server worktree, then runs the Laravel and Vite
build sequence with host binaries.

Branch mapping:

| Branch | Environment | Server path | Domain |
| --- | --- | --- | --- |
| `dev` | development | `/www/wwwroot/dev-x.asia-vn.edu.vn/asia-admin-portal` | `dev-x.asia-vn.edu.vn` |
| `main` | production | `/www/wwwroot/x.metropolia.edu.vn/asia-admin-portal` | `x.metropolia.edu.vn` |
| `main` | production | `/www/wwwroot/x.asia-vn.edu.vn/public_html` | `x.asia-vn.edu.vn` |

Required GitHub secrets:

```text
DEPLOY_HOST=157.10.186.103
DEPLOY_USER=root
DEPLOY_SSH_KEY=<private key with server SSH access>
DEPLOY_PORT=22
```

Deploy behavior:

1. Abort if the target path is not a git worktree.
2. Abort if tracked server-side changes exist, so CI does not overwrite manual edits. The generated `resources/js/ziggy.js` file is ignored for this dirty-check because deploy regenerates it before asset build.
3. Fetch the target branch from `origin`.
4. Check out the branch and reset to the pushed commit for push-triggered deploys.
5. Run `composer84 install` with optimized autoloading.
6. Run `php84 artisan optimize:clear`.
7. Run `php84 artisan ziggy:generate`.
8. Run `pnpm install --frozen-lockfile` when `pnpm-lock.yaml` exists.
9. Run `pnpm build`.
10. Restore generated `resources/js/ziggy.js` after build to keep the server worktree clean for the next deploy.
11. Run `php84 artisan migrate:status`.
12. Run `php84 artisan migrate --force` only when migrations are enabled.
13. Run `php84 artisan optimize`.
14. Run `php84 artisan queue:restart`.
15. External GitHub Actions `curl` health checks are intentionally skipped because aaPanel/WAF rules can return `403` to GitHub-hosted runners even when `/up` is reachable in a browser.

Migration policy:

- Push to `dev`: migrations run by default.
- Push to `main`: migrations do not run by default; only `migrate:status` runs.
- Manual workflow dispatch can run production migrations by selecting
  `target=production` and `run_migrations=true`.

Manual server-side command after the repo has already been checked out:

```bash
cd /www/wwwroot/dev-x.asia-vn.edu.vn/asia-admin-portal
DEPLOY_ENVIRONMENT=development DEPLOY_RUN_MIGRATIONS=true bash scripts/deploy-ubuntu.sh
```

Production manual example without migrations:

```bash
cd /www/wwwroot/x.metropolia.edu.vn/asia-admin-portal
DEPLOY_ENVIRONMENT=production DEPLOY_RUN_MIGRATIONS=false bash scripts/deploy-ubuntu.sh
```

## 16) MCP Server (ChatGPT / Claude connector)

The staff MCP endpoint is `POST /mcp/swinx` with OAuth discovery at `/.well-known/oauth-authorization-server`.

New clones on aaPanel/nginx + Cloudflare often need **server-side** fixes (nginx `.well-known` routing, Passport keys, Cloudflare WAF) that are not part of `git pull`. See the dedicated runbook:

- [MCP Server Deployment & Troubleshooting](mcp-server-deployment.md)

## Unresolved Questions

- Should production database stay containerized in all environments, or move to managed DB outside Compose?
- Should Redis stay containerized in production, or move to managed Redis before scale-out?
