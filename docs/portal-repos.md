# Portal Repos

Last updated: 2026-05-31
Owner: Platform Team
Status: Active workflow baseline

## Purpose

Swinx exposes student and lecturer API surfaces that are consumed by separate
Nuxt portal repositories kept under `FE/` for local multi-repo work. These
portal repositories are intentionally ignored by the Swinx git repository and
must remain independently versioned.

## Repo Map

| Surface | Local path | Backend base | Backend route files | Frontend API base |
| --- | --- | --- | --- | --- |
| Student portal | `FE/student-nuxt` | `/api/v1/student` | `routes/api/v1/student.php`, `app/Modules/Identity/routes/api.php` | `NUXT_PUBLIC_API_BASE` |
| Lecturer portal | `FE/lecturer-nuxt` | `/api/v1/lecturer` | `routes/api/v1/lecturer.php`, `app/Modules/Identity/routes/api.php` | `NUXT_PUBLIC_API_BASE` |

## Trigger Rules

Treat portal impact as explicit story metadata:

```text
Portal impact: none | student | lecturer | both
```

Use `student` when changing:

- `routes/api/v1/student.php`
- student auth/context routes in `app/Modules/Identity/routes/api.php`
- controllers/resources/requests/actions that shape `/api/v1/student/*`
- docs under `docs/api/student/`

Use `lecturer` when changing:

- `routes/api/v1/lecturer.php`
- lecturer auth/me/check-science routes in `app/Modules/Identity/routes/api.php`
- controllers/resources/requests/actions that shape `/api/v1/lecturer/*`
- docs under `docs/api/lecturer/`

Use `both` when a shared identity, notification, realtime, envelope, auth,
or CORS change affects both surfaces.

## Required Workflow

When portal impact is not `none`:

1. Run `./scripts/portal-status.sh` before edits to see nested repo state.
2. Inspect only the affected backend route/controller/resource/request/action
   and the matching FE composable, store, page, and `shared/types` files.
3. Update backend API docs in `docs/api/student/` or `docs/api/lecturer/`.
4. Update the affected portal code in the matching nested repo.
5. Run targeted backend validation and portal validation.
6. Record Swinx changes and portal changes separately in the final summary.

Do not move backend API logic into the portal repos. Do not commit or stage
portal files from the Swinx repository. If a portal repo has unrelated dirty
changes, work around them and do not revert them.

## Validation Commands

Backend commands must use the Docker wrapper:

```bash
./scripts/dev.sh artisan route:list
./scripts/dev.sh test
./scripts/dev.sh artisan pint
```

Run narrower backend checks when the touched area has a targeted test.

Student portal checks:

```bash
cd FE/student-nuxt
pnpm lint
pnpm typecheck
pnpm build
```

Lecturer portal checks:

```bash
cd FE/lecturer-nuxt
pnpm lint
pnpm typecheck
pnpm build
```

If a command cannot be run because dependencies, env, or services are missing,
state that explicitly and record the gap in the story evidence.

## Current Contract Posture

The portals currently use `$api` wrappers and handwritten TypeScript types.
OpenAPI/generated clients are not yet the enforced contract. Until generated
clients exist, contract safety comes from:

- backend API docs for the touched surface,
- matching portal `shared/types` updates,
- portal `pnpm typecheck`,
- targeted backend feature tests for request/response behavior.

## Token Control

Do not scan all of `FE/` by default. Start from the changed API path and search
for exact endpoint fragments or type names inside the matching portal repo:

```bash
rg -n "'/finance|/finance|Finance" FE/student-nuxt/app FE/student-nuxt/shared FE/student-nuxt/docs
rg -n "'/courses|/courses|Course" FE/lecturer-nuxt/app FE/lecturer-nuxt/shared FE/lecturer-nuxt/docs
```

Only broaden the search when the first pass does not reveal the consumer.

## Unresolved Questions

- Should Swinx introduce OpenAPI specs and generated Nuxt clients for student
  and lecturer surfaces?
- Should portal validation be wrapped by Swinx helper scripts once CI is
  restored?
