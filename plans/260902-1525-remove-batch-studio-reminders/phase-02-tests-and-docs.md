---
title: "Phase 2: Tests and docs"
status: todo
---

# Phase 2: Tests and docs

## Overview

Replace reminder-wizard tests with absence/authz updates. Prove due-calendar send still works. Update staff guide because `Hub.vue` is a `source:` of finance-office pages.

## Requirements

- [x] `BatchReminderCommitTest` deleted (route gone)
- [x] `BatchStudioAuthzTest` hub assertion drops `jobs.reminder`
- [x] Shared due-item reminder tests unchanged and still pass
- [x] Optional: one test that GET `/finance/batch-studio/reminders` is 404 for an authenticated operator
- [x] docs-site VI + EN + KO (+ ZH) finance-office: Batch Studio describes charge generation (and DNG if already implied), not bulk reminders; remind staff to use **DNG Due Reminders**
- [x] Pint dirty + eslint/prettier on changed Vue/TS; `./scripts/check-docs.sh` and `./scripts/check-docs-freshness.sh` if docs-site changed

## Related Code Files

- Delete: `tests/Feature/Finance/Batch/BatchReminderCommitTest.php`
- Modify: `tests/Feature/Finance/Batch/BatchStudioAuthzTest.php`
- Keep: `tests/Feature/Finance/SendDueItemRemindersActionTest.php`
- Keep: `tests/Feature/Finance/SendDueItemParentRemindersActionTest.php`
- Keep: `tests/Feature/Finance/Operations/DueReminderExamResitTest.php`
- Modify: `docs-site/src/content/docs/finance-office/index.md`
- Modify: `docs-site/src/content/docs/en/finance-office/index.md`
- Modify: `docs-site/src/content/docs/ko/finance-office/index.md`
- Modify: `docs-site/src/content/docs/zh/finance-office/index.md` if it lists Hub.vue

## Implementation Steps

1. Delete `BatchReminderCommitTest.php`.
2. Update hub authz test: only `charge_generation` and `dng_push`.
3. Add 404 test for the old path if cheap (`get('/finance/batch-studio/reminders')->assertNotFound()`).
4. Update finance-office staff guide locales: Vietnamese first, then EN/KO/ZH. Do not mention routes or permission codes.
5. Run:
   - `./scripts/dev.sh artisan test --compact --filter=BatchStudioAuthzTest`
   - `./scripts/dev.sh artisan test --compact --filter=SendDueItemRemindersActionTest`
   - `./scripts/dev.sh artisan test --compact --filter=SendDueItemParentRemindersActionTest`
   - any new 404 test
   - pint --dirty, eslint/prettier on changed frontend files
   - docs freshness if docs-site touched

## Todo

- [x] PHP tests
- [x] Staff guide locales
- [x] Scoped verification

## Success Criteria

- [x] No test references `finance.batch-studio.reminders`
- [x] Due-item send tests pass
- [x] Authz hub test matches two-job hub
- [x] docs-freshness green for Hub.vue source pages

## Risk Assessment

- Freshness fails if Hub.vue changes and a locale page is skipped. Touch all locales that list Hub.vue in `source:`.
