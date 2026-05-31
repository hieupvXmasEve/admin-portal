# Validation

## Proof Strategy

Prove the bulk link parser, campus scoping, preview contract, and transaction
write behavior with focused feature tests. Then run targeted frontend checks for
the touched Vue and TypeScript files.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Code normalization collapses separators and duplicate values. |
| Integration | Preview returns matched, unmatched, skipped, and same-action-type linkable action log data. |
| Integration | Confirm links only eligible current-campus action logs of the selected type to the decision. |
| Integration | Cross-campus student codes are not linked. |
| E2E | Not required unless browser access is available; feature is covered by request + component checks. |
| Platform | Docker wrapper commands. |
| Performance | Validate max code count; no unbounded paste. |
| Logs/Audit | Bulk link emits an application log entry. |

## Fixtures

- One decision record.
- Current-campus students with linkable action logs.
- Current-campus student with no matching action log.
- Other-campus student with an action log.
- Unknown pasted code.

## Commands

```text
./scripts/dev.sh test tests/Unit/Academic/StudentCodeParserTest.php tests/Feature/Academic/StudentDecisionBulkLinkTest.php
./scripts/dev.sh artisan pint --dirty
./scripts/dev.sh composer exec pint -- --dirty
./scripts/dev.sh npm exec -- prettier --check resources/js/pages/Admin/Reports/StudentDecisions/Show.vue resources/js/utils/routes.ts
./scripts/dev.sh npm exec -- eslint resources/js/pages/Admin/Reports/StudentDecisions/Show.vue resources/js/utils/routes.ts
./scripts/dev.sh npm exec -- node --max-old-space-size=4096 node_modules/vue-tsc/bin/vue-tsc.js --noEmit --pretty false
./scripts/dev.sh artisan route:list --name=reports.student-decisions
git diff --check
```

## Acceptance Evidence

- Combined parser unit and bulk-link feature tests passed with 4 tests and 27
  assertions.
- `./scripts/dev.sh artisan route:list --name=reports.student-decisions`
  passed and lists the new preview and bulk-link routes.
- `./scripts/dev.sh artisan pint --dirty` failed because this application does
  not define an Artisan `pint` command; Pint passed through Composer for the
  touched PHP files.
- Targeted Prettier check and ESLint passed for the touched Student Decisions
  page and route helper file.
- Repo-wide `vue-tsc` still fails on pre-existing diagnostics outside the
  touched Student Decisions files; filtered diagnostics contain no errors for
  `resources/js/pages/Admin/Reports/StudentDecisions/Show.vue` or
  `resources/js/utils/routes.ts`.
- `git diff --check` passed.
