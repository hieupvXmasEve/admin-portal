# 09 — Restore a parseable Finance baseline

Status: completed

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Restore a truthful, executable Finance verification baseline before further retirement work. Remove Finance code and tests that are proven to describe retired behavior, fix any retained source that cannot be loaded, and correct documentation that still describes removed source pointers.

Retain permanent Finance components that still have supported responsibilities. In particular, `DngPaymentService` is not legacy merely by name: its guarded provider push and access responsibilities remain valid until a separate supported replacement exists.

## Acceptance criteria

- [x] Every retained Finance PHP source file parses and autoloads successfully.
- [x] The duplicate `FinanceObligation` import in the retired retake-course action is removed, or the action and its tests are deleted after proving that they have no supported runtime responsibility.
- [x] Tests for retired behavior are removed or replaced with tests at the active canonical seam; no skipped or unloadable test is presented as passing evidence.
- [x] Runtime source, container bindings, routes, jobs, commands, and tests have no references to code removed by this slice.
- [x] `DngPaymentService` and any other still-supported component are preserved unless all of their active responsibilities and callers have first moved to an approved replacement.
- [x] Architecture documentation no longer describes `egc_blocks.finance_charge_id` or another removed source-side Finance pointer as current architecture.
- [x] Focused Finance tests and PHP syntax/autoload checks pass from a clean process.

## Verification

- Removed `CreateRetakeCourseChargeAction` and its dedicated test after repository-wide caller scans found no route, container binding, job, command, or supported caller. The active `CreateRetakeCourseRegistrationAction`/Finance Intake flow and cancellation coverage remain in place.
- Removed `RetakeResitChargeSourceTest`, which asserted retired polymorphic source pointers instead of the canonical FinanceObligation source triple.
- Preserved `DngPaymentService`: it remains bound as a singleton and has active webhook, reconciliation, receipt-capture, student-access, and guarded-reservation callers.
- Updated the current Finance architecture, codebase summary, roadmap, and schema map to resolve EGC Finance evidence through `FinanceObligation` source identity instead of `egc_blocks.finance_charge_id`.
- Clean-process verification: `./scripts/dev.sh composer dump-autoload --optimize`; a fresh Artisan process parsed all 318 Finance PHP files and autoloaded all 316 class-bearing Finance files; `./scripts/dev.sh artisan test --compact tests/Feature/Academic/RetakeCourse/CreateRetakeCourseRegistrationActionTest.php tests/Feature/Academic/ExamResit/CreateExamResitAttemptActionTest.php tests/Feature/Academic/RetakeCourse/CancelRetakeCourseRegistrationActionTest.php tests/Feature/Finance/CancellationOperationTest.php` passed 48 tests / 186 assertions. Pint and `git diff --check` passed.
- Quality gaps: `./scripts/dev.sh test` exited `255` with no output; `./scripts/dev.sh npm run type-check` exhausted the 2 GiB Node heap (`134`); `./scripts/dev.sh npm run lint` found 4,783 existing errors, chiefly generated nested-portal files and unrelated Vue sources. This slice changes no frontend or portal code.

## Comments

- 2026-07-15: Completed the parseable-baseline repair. The removed action was unreachable; retained canonical intake/cancellation and DNG service responsibilities are verified by active callers and focused tests.

## Blocked by

- None — can start immediately.
