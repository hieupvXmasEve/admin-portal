# Docker Layout

Docker packaging is FrankenPHP-only.

## Files

| File | Purpose |
| --- | --- |
| `Dockerfile` | Development image (bind-mount source) |
| `Dockerfile.production` | Production image (baked assets) |
| `docker-compose.dev.yml` | Local dev stack |
| `docker-compose.local-prod.yml` | Local production-like stack (TLS self-signed) |
| `docker-compose.production.yml` | Full production (container binds 80/443 + ACME) |
| `docker-compose.host.yml` | **Host deploy** — one port, behind Cloudflare/nginx |
| `Caddyfile.dev` | Dev Caddy config |
| `Caddyfile.local-prod` | Local prod TLS |
| `Caddyfile.prod` | Production ACME TLS |
| `Caddyfile.proxy` | HTTP behind reverse proxy (host deploy) |
| `nginx-proxy-snippet.conf` | aaPanel/nginx `proxy_pass` reference |

## Deployment modes

| Mode | Script | When to use |
| --- | --- | --- |
| Development | `./scripts/dev.sh` | Local coding, HMR, tests |
| Local prod test | `./scripts/local-prod.sh` | Test production build locally |
| Host (recommended) | `./scripts/host.sh` | Server with aaPanel/Cloudflare — **point domain at one port** |
| Full TLS in Docker | `./scripts/prod.sh` | Dedicated server with ports 80/443 free for Caddy ACME |

## Host deploy (simplest)

```bash
cp .env.example .env
# Set APP_ENV=production, APP_DEBUG=false, APP_URL=https://your-domain.com, TRUSTED_PROXIES=*

./scripts/host.sh deploy
```

Then point your domain at `http://127.0.0.1:8080` (or `HOST_APP_PORT`):

- **Cloudflare (orange cloud):** DNS A record → server IP; ensure WAF allows the hostname.
- **aaPanel nginx:** use `docker/nginx-proxy-snippet.conf` in the site vhost.

Verify:

```bash
curl -fsS http://127.0.0.1:8080/up
curl -fsS https://your-domain.com/.well-known/oauth-authorization-server
```

## Service topology

**Development:** `app`, `vite`, `queue`, `scheduler`, `db`, `redis`, `mailpit`

**Host / production:** `app`, `queue`, `scheduler`, `db`, `redis`

## Environment

- Use `.env.example` as the template.
- Copy to `.env` and adjust per environment.
- Do not maintain separate `.env.docker.*` files.