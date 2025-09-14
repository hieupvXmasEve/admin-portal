# Notification System Deployment & Configuration Guide

This guide covers environment configuration, deployment steps, monitoring, and scaling for the Notification System (REST + WebSockets + Web Push).

## Components

- REST API (Laravel)
- Laravel Reverb WebSocket server (real-time broadcasting)
- Web Push (VAPID keys)
- Laravel Queue workers (for retries/logging as applicable)

## Environment Variables

Add/update the following in `.env` (see `.env.example`):

### Broadcasting & Reverb
```
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=swinx-app
REVERB_APP_KEY=swinx-key
REVERB_APP_SECRET=swinx-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=*
REVERB_SCALING_ENABLED=true
REVERB_MAX_CONNECTIONS=1000
REVERB_ENABLE_STATISTICS=true
REVERB_ENABLE_LOGGING=true

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Web Push (VAPID)
```
WEBPUSH_VAPID_SUBJECT=mailto:admin@example.com
WEBPUSH_VAPID_PUBLIC_KEY=
WEBPUSH_VAPID_PRIVATE_KEY=
```

Generate VAPID keys (choose one):
- PHP package CLI, or
- Node-based `web-push` CLI: `npx web-push generate-vapid-keys`

## Deployment Steps

1) Build frontend assets
```
npm ci
npm run build
```

2) Migrate & cache
```
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3) Start Reverb server
```
php artisan reverb:start --host=${REVERB_SERVER_HOST} --port=${REVERB_SERVER_PORT}
```

4) Run queue workers (if used)
```
php artisan queue:work --queue=default --sleep=1 --tries=3 --max-time=3600
```

5) Scheduler (for maintenance/cleanup when enabled)
```
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

## Process Management

### systemd (Reverb)
```
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/app
ExecStart=/usr/bin/php artisan reverb:start --host=0.0.0.0 --port=8080
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

### Supervisor (Queue)
```
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/artisan queue:work --sleep=1 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/app/storage/logs/worker.log
```

## Monitoring & Troubleshooting

- Reverb health: `http://REVERB_HOST:REVERB_PORT/health`
- Reverb stats: `http://REVERB_HOST:REVERB_PORT/stats` (if enabled)
- Laravel logs: `storage/logs/laravel.log`
- Reverb logs (if redirected): `storage/logs/reverb.log`
- Verify broadcasting config: `php artisan config:show broadcasting`

Common issues:
- Connection refused: Ensure Reverb is running; host/port reachable; firewalls allow traffic.
- Auth failures: Ensure Sanctum token present on Echo `auth.headers`.
- CORS/origins: Set `REVERB_ALLOWED_ORIGINS` to permit frontend origins.
- Port in use: Change `REVERB_SERVER_PORT` or stop conflicting service.
- Web Push unsupported: Ensure VAPID keys configured and HTTPS in production.

## Scaling & Performance

- Enable Redis-backed scaling: `REVERB_SCALING_ENABLED=true` and configure Redis (host, port, auth).
- Horizontal scale Reverb behind a load balancer; sticky sessions usually not required for WebSockets with Reverb.
- Tune limits: `REVERB_MAX_CONNECTIONS`, `REVERB_APP_MAX_MESSAGE_SIZE`.
- Observe metrics: enable statistics; add external monitoring/alerts for connection count and error rates.

## Security Notes

- Keep `REVERB_APP_SECRET` and VAPID private key secret.
- Use TLS (`REVERB_SCHEME=https`) in production with valid certs.
- Scope admin-only analytics and send endpoints via Laravel Gates/Policies.

---

For local setup and testing, see `docs/notification-broadcasting-setup.md` for end-to-end connection checks and sample Echo configuration.

