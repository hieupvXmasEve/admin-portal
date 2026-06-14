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
./scripts/dev.sh artisan pint app/Modules/Academic app/Services/V1/Student app/Services/AssessmentReportService.php app/Http/Resources tests/Feature/Api/V1
(cd FE/student-nuxt && pnpm lint && pnpm typecheck && pnpm build)
(cd FE/lecturer-nuxt && pnpm lint && pnpm typecheck && pnpm build)
git diff --check -- app docs FE/student-nuxt FE/lecturer-nuxt
```

## Acceptance Evidence

The story is complete when backend tests pass, both portal validation commands
pass or record unrelated pre-existing diagnostics, and screenshots or component
render evidence show default and custom grade display.
