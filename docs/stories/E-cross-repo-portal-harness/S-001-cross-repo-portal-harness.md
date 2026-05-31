# S-001 Cross-Repo Portal Harness Workflow

## Status

implemented

## Lane

normal

## Product Contract

When a Swinx change affects student-facing or lecturer-facing API behavior, the
agent must treat the matching Nuxt portal under `FE/` as an adjacent repo that
may need coordinated edits and validation. The Laravel backend remains the
source of truth for API behavior; the gitignored portal repos remain separate
repositories and are not committed through the Swinx repository.

## Relevant Product Docs

- `docs/HARNESS.md`
- `docs/FEATURE_INTAKE.md`
- `docs/portal-repos.md`
- `docs/api/student/`
- `docs/api/lecturer/`
- `FE/student-nuxt/AGENTS.md`
- `FE/lecturer-nuxt/AGENTS.md`

## Portal Impact

both

## Acceptance Criteria

- Agent instructions explain when Swinx work must inspect/update
  `FE/student-nuxt` or `FE/lecturer-nuxt`.
- Harness workflow includes cross-repo API contract and validation expectations.
- The workflow keeps `FE/` gitignored and preserves independent frontend repos.
- Future API stories can name affected backend routes, frontend files, and
  validation commands without requiring broad repo scans.

## Design Notes

- Commands:
  - Backend: `./scripts/dev.sh test`, `./scripts/dev.sh artisan route:list`,
    targeted Pint, and relevant feature tests.
  - Student portal: `pnpm lint`, `pnpm typecheck`, `pnpm build` in
    `FE/student-nuxt` when touched.
  - Lecturer portal: `pnpm lint`, `pnpm typecheck`, `pnpm build` in
    `FE/lecturer-nuxt` when touched.
- Queries:
  - Use targeted `rg` on routes/controllers/docs and matching FE feature folders.
- API:
  - Student base: `/api/v1/student`
  - Lecturer base: `/api/v1/lecturer`
  - Identity auth routes live in `app/Modules/Identity/routes/api.php`.
- Tables:
  - No schema changes for this Harness setup.
- Domain rules:
  - Do not move API logic into portal repos.
  - Do not import frontend assumptions into backend modules.
  - Public API shape changes must update backend docs and affected FE types/code.
- UI surfaces:
  - `FE/student-nuxt`
  - `FE/lecturer-nuxt`

## Validation

| Layer | Expected proof |
| --- | --- |
| Unit | Not required for docs-only workflow setup. |
| Integration | Harness matrix/backlog queries show the story and no DB/manual drift. |
| E2E | Not required for docs-only workflow setup. |
| Platform | Confirm `FE/` remains gitignored and nested repos are independently detectable. |
| Release | Final summary lists any docs/scripts changed and commands run. |

## Harness Delta

Add a small cross-repo portal workflow document and wire future intake/story
guidance so agents know when to touch portal repos after backend API changes.

## Evidence

- `bash -n scripts/portal-status.sh` passed.
- `git diff --check` passed for Swinx workflow docs/script changes.
- `git -C FE/student-nuxt diff --check -- AGENTS.md` passed.
- `git -C FE/lecturer-nuxt diff --check -- AGENTS.md` passed.
- `./scripts/portal-status.sh` confirmed `FE/student-nuxt` and
  `FE/lecturer-nuxt` are ignored by Swinx and are independent nested git repos.
- `./scripts/harness query matrix` shows this story in the Harness matrix.
