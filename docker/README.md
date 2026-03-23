# Docker Layout

Docker packaging is FrankenPHP-only.

## Files

- `docker/Dockerfile`: development image
- `docker/Dockerfile.production`: production image
- `docker/docker-compose.dev.yml`: local dev stack
- `docker/docker-compose.local-prod.yml`: local production-like stack
- `docker/docker-compose.production.yml`: production stack
- `docker/Caddyfile.dev`: dev Caddy/FrankenPHP config
- `docker/Caddyfile.local-prod`: local production Caddy config
- `docker/Caddyfile.prod`: production Caddy config
- `docker/start-frankenphp.sh`: dev bootstrap
- `docker/start-frankenphp-production.sh`: production bootstrap

## Environment

- Use `.env.example` as the template.
- Copy it to `.env` and adjust values per environment.
- Do not maintain separate `.env.docker.*` files.

## Service Topology

Development:
- `app`
- `vite`
- `queue`
- `scheduler`
- `db`
- `redis`
- `mailpit`

Production:
- `app`
- `queue`
- `scheduler`
- `db`
- `redis`

## Commands

```bash
./scripts/dev.sh start
./scripts/local-prod.sh start
./scripts/prod.sh deploy
```
