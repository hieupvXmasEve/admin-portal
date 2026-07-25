---
title: Portal Repository Workflow
status: canonical
owner: Platform Team
last_verified: 2026-07-25
scope: cross-repository-portals
---

# Portal Repository Workflow

Swinx exposes student and lecturer APIs consumed by separate Nuxt repositories
under `FE/`. Those directories are ignored by Swinx and keep independent Git
history.

## Repository map

| Surface | Local repository | Backend base | Canonical contract |
| --- | --- | --- | --- |
| Student | `FE/student-nuxt` | `/api/v1/student` | `docs/api/student/` |
| Lecturer | `FE/lecturer-nuxt` | `/api/v1/lecturer` | `docs/api/lecturer/` |

Identity/context routes may also live in
`app/Modules/Identity/routes/api.php`.

## Impact declaration

Every change to a portal-consumed contract records:

```text
Portal impact: none | student | lecturer | both
```

Student impact includes student routes, auth/context, resources, requests,
response envelopes, docs, or shared notification behavior.

Lecturer impact includes lecturer routes, auth/context, resources, requests,
response envelopes, docs, or shared notification behavior.

## Required workflow

1. Run `./scripts/portal-status.sh` before portal or portal-facing API edits.
2. Inspect the exact backend route/controller/request/resource and matching
   portal endpoint/type consumer.
3. Update the canonical backend API contract.
4. Update only the affected nested portal.
5. Keep Swinx and portal Git states separate.
6. Run targeted backend validation.
7. Run the affected portal's lint, typecheck, and build.
8. Report backend and portal changes separately.

Do not stage nested portal files from Swinx. Preserve unrelated dirty changes
inside each repository.

## Portal validation

Student:

```bash
cd FE/student-nuxt
pnpm lint
pnpm typecheck
pnpm build
```

Lecturer:

```bash
cd FE/lecturer-nuxt
pnpm lint
pnpm typecheck
pnpm build
```

If environment or dependencies prevent a check, report the exact gap.

## Contract posture

Portal clients currently use API wrappers and handwritten TypeScript contracts.
Until generated OpenAPI clients are adopted, safety depends on:

- canonical Markdown API contracts;
- matching portal types/composables/stores;
- targeted backend request/response tests;
- portal typecheck and build.

Search only the affected portal first. Broaden scope only when endpoint or type
consumers cannot be found.
