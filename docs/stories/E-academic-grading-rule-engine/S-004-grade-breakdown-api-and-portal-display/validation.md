# Validation

## Proof Strategy

Prove backend response shape is backwards-compatible, custom-scheme courses use
the new display object, default courses still render through existing fields,
and both portal repos typecheck after consuming the new optional fields.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Presenter hides internal calculation fields and formats default/custom display labels. |
| Integration | Student grade API and lecturer grade matrix include optional `grade_display`. |
| E2E | Portal grade screens render custom numeric 0-5 and pass/fail labels from mocked API data. |
| Platform | Run portal status and keep nested git states separate. |
| Performance | Presenter operates on loaded records and does not add N+1 queries. |
| Logs/Audit | API contract docs updated; no write audit expected. |

## Fixtures

- Default weighted academic record.
- Metropolia numeric 0-5 academic record with component breakdown.
- Metropolia pass/fail academic record with requirement status.

## Commands

```bash
./scripts/portal-status.sh
./scripts/dev.sh test tests/Feature/Api/V1/Student/GradeBreakdownApiTest.php
./scripts/dev.sh test tests/Feature/Api/V1/Lecturer/GradeBreakdownApiTest.php
# Pint runs via composer exec (this project has no `artisan pint` command) and
# `--dirty` keeps it scoped to changed files instead of repo-wide pre-existing
# style debt.
./scripts/dev.sh composer exec pint -- --test --dirty
(cd FE/student-nuxt && pnpm typecheck && pnpm build)
(cd FE/lecturer-nuxt && pnpm typecheck && pnpm build)
git diff --check -- app docs FE/student-nuxt FE/lecturer-nuxt
```

> Note: `pnpm lint` is intentionally omitted from the gate. Both portals' bare
> `pnpm lint` fails on a pristine checkout (`eslint-plugin-pnpm` rule
> `pnpm/json-enforce-catalog`: `pnpm-workspace.yaml not found` while linting
> `package.json`) — a pre-existing tooling diagnostic unrelated to this story.
> `pnpm typecheck` and `pnpm build` both pass and cover the contract changes.

## Acceptance Evidence

The story is complete when backend tests pass, both portal validation commands
pass or record unrelated pre-existing diagnostics, and screenshots or component
render evidence show default and custom grade display.
