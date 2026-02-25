# Codebase Summary

Last updated: 2026-02-25  
Owner: Platform Team  
Status: Current-state snapshot  
Primary source: `repomix-output.xml` (generated 2026-02-25)

## 1) Snapshot Method

Generated with:

```bash
repomix -o repomix-output.xml
```

Latest repomix summary:
- Total files packed: `2,010`
- Total tokens: `3,135,998`
- Output file: `repomix-output.xml`

Top token-heavy files include large artifacts (`release-manifest.json`, static html, large data JSON), so architectural interpretation should prioritize app/runtime code paths.

## 2) Repository Shape (File Counts)

Current counts:
- `app`: 826
- `resources`: 739
- `database`: 233
- `docs`: 108
- `routes`: 49
- `config`: 21
- `scripts`: 12

## 3) Runtime Architecture Baseline

- Laravel 12 monolith with hybrid layering:
  - module domains in `app/Modules/*`
  - large shared layer in `app/Services/*`, `app/Models/*`, and shared HTTP layers
- Vue 3 + Inertia frontend in `resources/js/*`

Verified entry points:
- `bootstrap/app.php`
- `routes/web.php`
- `routes/api.php`
- `resources/js/app.ts`
- `resources/js/ssr.ts`

## 4) Auth and API Baseline

- Student and lecturer v1 routes are protected with Sanctum + actor middleware patterns.
- Identity module refresh endpoints for student/lecturer/parent are protected.
- Token TTL in Identity login/refresh actions is standardized to 8 hours.
- Refresh flow is currently issue-new-token then revoke-current-token.

Open drift retained in code:
- Public `/api/system-config*` routes in `routes/api.php`.
- Finance API routes in `app/Modules/Finance/routes/api.php` use `web` + `auth`.

## 5) Frontend Contract Baseline

Current frontend integration posture:
- `route(...)` helper usage is dominant (`523` matches in `resources/js` search snapshot).
- Literal-path pockets remain (`router.visit('/...')` and direct `/api/...` calls), which can drift during route refactors.

## 6) Ops/Delivery Baseline

- CI workflows exist but are disabled (fully commented out): `deploy.yml`, `lint.yml`, `tests.yml`.
- Script/compose path drift still present (missing helper scripts; root path assumptions vs `docker/*` files).
- Deployment/runtime artifacts still expose security risks (hardcoded defaults/credentials and public DB port mapping in production compose files).

## 7) Documentation Baseline

Core baseline docs are maintained in:
- `docs/project-overview-pdr.md`
- `docs/code-standards.md`
- `docs/system-architecture.md`
- `docs/project-roadmap.md`
- `docs/deployment-guide.md`
- `docs/design-guidelines.md`

## 8) Immediate Documentation Priorities

1. Keep auth matrix and risk statements synchronized with route changes.
2. Track CI/deploy drift as current-state risk until remediated.
3. Tighten source-of-truth discipline (owner/status/last-updated in core docs).
4. Keep unresolved decisions explicit at doc end sections.

## Unresolved Questions

- Should repomix metrics exclude heavy generated/static artifacts for engineering trend reporting?
- What is the formal placement policy for new business logic: `app/Modules/*` vs `app/Services/*`?
