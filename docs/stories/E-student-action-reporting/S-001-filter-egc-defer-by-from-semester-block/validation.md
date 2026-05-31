# Validation

## Proof Strategy

Prove the audit snapshot at the write boundary, correction boundary, report
query, export mapping, and import mapper. Verify the migration separately
against the local dataset after it runs.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Import mapper accepts Block 1/Block 2, rejects invalid values, and defaults blank EGC defer values to Block 1. |
| Integration | EGC defer create requires and persists a valid block; major-program defer leaves it null; EGC action edit corrects the block; report query filters `from_semester_id` without matching return-only rows; report query filters Block 1/Block 2; export includes the stored block. |
| E2E | Manual browser smoke on student-action create, detail correction, report filtering, and export after targeted checks pass. |
| Platform | Not required. |
| Performance | Confirm the source-block filter uses an indexed audit column. |
| Logs/Audit | Post-migration SQL shows no EGC defer audit record missing a source block and no major-program defer populated with one. |

## Fixtures

- One EGC student with `status = intake_pre_uni_gc`.
- One major-program student with `status = intake_course`.
- Two semesters where one action references the selected semester only as its
  return semester.
- EGC defer action logs with Block 1 and Block 2.
- Historical EGC defer action log with a null source block before migration
  verification.

## Commands

```text
./scripts/dev.sh artisan migrate
./scripts/dev.sh test tests/Feature/Academic/StudentActionDeferBlockTest.php
./scripts/dev.sh composer exec pint -- --test app/Models/StudentActionLog.php app/Modules/Academic tests/Feature/Academic/StudentActionDeferBlockTest.php database/migrations
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run format:check
git diff --check
```

## Acceptance Evidence

Add results after verification.
