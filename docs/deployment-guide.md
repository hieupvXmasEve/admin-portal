# Production Deployment Guide

Last updated: 2026-03-05  
Owner: Platform Team  
Status: Operational baseline

## 1) Prerequisites

| Requirement | Minimum |
|-------------|---------|
| Docker | 20.10+ |
| Docker Compose | 2.0+ |
| Disk space | 10GB+ |
| RAM | 4GB+ |

Verify:

```bash
docker --version
docker-compose --version
```

## 2) Environment Setup

Required files:

- `.env.docker.production` — production environment variables
- `ssl/privkey.pem` and `ssl/fullchain.pem` — SSL certificates (auto-generated if missing)

Create production env file from template:

```bash
cp .env.example .env.docker.production
```

Edit `.env.docker.production` with production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_HOST=db
DB_DATABASE=swinburne
DB_USERNAME=swinx_prod_user
DB_PASSWORD=<secure-password>

QUEUE_CONNECTION=database
BROADCAST_CONNECTION=ably
ABLY_KEY=<your-ably-key>

# Notification V2
NOTIFICATION_V2_ENABLED=true
NOTIFICATION_V2_WRITE_MODE=v2
NOTIFICATION_V2_READ_MODE=legacy

# FrankenPHP
SERVER_NAME=your-domain.com
ACME_EMAIL=admin@your-domain.com
```

## 3) Deployment Steps

### 3.1 Quick Deploy (Recommended)

```bash
cd docker
./scripts/deploy-production.sh
```

Script performs:

1. Validates Docker prerequisites
2. Copies `.env.docker.production` to `.env`
3. Creates SSL certificates if missing
4. Backs up database (if running)
5. Builds and starts containers
6. Runs migrations
7. Caches config/routes/views/events
8. Creates storage link and Ziggy routes
9. Health check verification

### 3.2 Manual Deploy

```bash
cd docker

# Build containers
docker-compose -f docker-compose.production.yml build --no-cache

# Start services
docker-compose -f docker-compose.production.yml up -d

# Run migrations
docker-compose -f docker-compose.production.yml exec app php artisan migrate --force

# Cache Laravel
docker-compose -f docker-compose.production.yml exec app php artisan config:cache
docker-compose -f docker-compose.production.yml exec app php artisan route:cache
docker-compose -f docker-compose.production.yml exec app php artisan view:cache
docker-compose -f docker-compose.production.yml exec app php artisan event:cache

# Storage link
docker-compose -f docker-compose.production.yml exec app php artisan storage:link
```

## 4) Service Architecture

Containers:

| Service | Container | Ports |
|---------|-----------|-------|
| App (FrankenPHP) | `swinx-app` | 80, 443, 443/udp |
| MySQL 8.0 | `swinx-db` | 3306 |

FrankenPHP features:

- Built-in HTTP/2 and HTTP/3
- Automatic HTTPS with Let's Encrypt (set `SERVER_NAME` and `ACME_EMAIL`)
- OPcache optimized for production

## 5) Queue Worker Setup

Queue worker is required for Notification V2 delivery jobs.

### 5.1 Inside Container

```bash
docker-compose -f docker-compose.production.yml exec app \
    php artisan queue:work --sleep=1 --tries=3
```

### 5.2 Supervisor (Recommended)

Create `/etc/supervisor/conf.d/swinx-worker.conf`:

```ini
[program:swinx-worker]
process_name=%(program_name)s_%(process_num)02d
command=docker exec swinx-app php artisan queue:work --sleep=1 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/swinx-worker.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start swinx-worker:*
```

## 6) Scheduler Setup

Laravel scheduler runs scheduled commands including `notifications:process-outbox`.

### 6.1 Host Cron

Add to crontab:

```bash
* * * * * docker exec swinx-app php artisan schedule:run >> /dev/null 2>&1
```

### 6.2 Verify Scheduled Commands

```bash
docker exec swinx-app php artisan schedule:list
```

Key scheduled commands:

| Command | Frequency | Purpose |
|---------|-----------|---------|
| `notifications:process-outbox --limit=100` | Every minute | Process notification outbox |
| `sessions:update-statuses` | Every 30 min | Update class session statuses |
| `events:process-completions` | Hourly | Process event completions |

## 7) Notification V2 Deployment Checklist

Pre-deploy:

- [ ] Set `NOTIFICATION_V2_ENABLED=true`
- [ ] Set `NOTIFICATION_V2_WRITE_MODE=v2`
- [ ] Configure `BROADCAST_CONNECTION=ably` and `ABLY_KEY`
- [ ] Verify queue worker is running
- [ ] Verify scheduler cron is active

Post-deploy verification:

```bash
# Check outbox processing
docker exec swinx-app php artisan notifications:process-outbox --limit=10

# Verify queue worker
docker exec swinx-app php artisan queue:monitor database

# Check notification tables
docker exec swinx-db mysql -u swinx_prod_user -p -e \
    "SELECT status, COUNT(*) FROM notification_event_outbox GROUP BY status;"
```

Monitor via admin pages:

- `/admin/notifications/ops/outbox` — Outbox events
- `/admin/notifications/ops/deliveries` — Delivery attempts
- `/admin/notifications/ops/messages` — All messages

## 8) Database Backup

Automated backup during deploy keeps 7 most recent backups.

Manual backup:

```bash
docker-compose -f docker-compose.production.yml exec -T db mysqldump \
    -u root -p \
    --all-databases --routines --triggers > backups/manual_$(date +%Y%m%d).sql
```

Restore:

```bash
docker-compose -f docker-compose.production.yml exec -T db mysql \
    -u root -p < backups/manual_20260305.sql
```

## 9) Health Checks

Application health endpoint:

```bash
curl http://localhost/up
```

Container health:

```bash
docker-compose -f docker-compose.production.yml ps
docker stats --no-stream
```

## 10) Rollback Procedures

### 10.1 Application Rollback

```bash
# Stop containers
docker-compose -f docker-compose.production.yml down

# Checkout previous version
git checkout <previous-tag>

# Rebuild and deploy
./scripts/deploy-production.sh
```

### 10.2 Notification V2 Rollback

Disable V2 writes without full rollback:

```env
NOTIFICATION_V2_WRITE_MODE=off
NOTIFICATION_V2_READ_MODE=legacy
```

Then:

```bash
docker-compose -f docker-compose.production.yml exec app php artisan config:cache
```

## 11) Useful Commands

| Task | Command |
|------|---------|
| View logs | `docker-compose -f docker-compose.production.yml logs -f` |
| App shell | `docker exec -it swinx-app bash` |
| Artisan | `docker exec swinx-app php artisan <command>` |
| MySQL CLI | `docker exec -it swinx-db mysql -u swinx_prod_user -p` |
| Clear cache | `docker exec swinx-app php artisan optimize:clear` |
| Rebuild cache | `docker exec swinx-app php artisan optimize` |

## Unresolved Questions

- Should queue worker run as separate container for horizontal scaling?
- What is the backup retention policy beyond 7 days?
