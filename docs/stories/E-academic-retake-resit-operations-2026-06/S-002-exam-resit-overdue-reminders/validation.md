# Validation

## Proof Strategy

The story is complete when HQ staff can open the new Finance UI Due Reminders
entry, immediately see DNG due rows plus PTL exam-resit overdue context, and send
safe reminders from the same page without changing paid-state ownership.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Classifier for exam-resit due/reminder states: paid, unpaid not due, overdue, needs charge/DNG, blocked by lifecycle exception, missing email. |
| Integration | `ListDueItemsQuery` includes PTL rows with linked `ExamResitAttempt` metadata; rows without DNG are visible but not remindable; send action updates reminder timestamps only on success. |
| Integration | Existing DNG reminder behavior for non-PTL fee types is unchanged. |
| E2E | Staff opens Due Reminders from new sidebar, filters PTL/overdue, previews selected rows, sends reminders, and sees updated last-reminder state. |
| Platform | Targeted TypeScript/lint/format checks for touched Finance UI files. |
| Performance | Due Reminders remains paginated and campus/semester scoped; no full-table materialization for routine filters. |
| Logs/Audit | Reminder delivery/skips are logged with source ids and actor; no manual paid state is written. |

## Fixtures

- PTL `ExamResitAttempt` scheduled and unpaid, overdue by more than the grace
  window, with linked pushed DNG request.
- PTL `ExamResitAttempt` scheduled and unpaid but not yet overdue.
- PTL `ExamResitAttempt` overdue but missing charge/DNG, requiring handoff.
- Paid PTL attempt that must not appear as remindable.
- Non-PTL DNG due row proving existing behavior remains.
- Lifecycle-exception student/DNG row proving reminder block.
- Student with missing email proving skipped state.

## Commands

Expected validation shape after implementation:

```bash
./scripts/dev.sh test tests/Feature/Finance/Operations/DueReminderExamResitTest.php
./scripts/dev.sh test tests/Feature/Finance/Operations/DueCalendarTest.php
./scripts/dev.sh test tests/Feature/Academic/ExamResit
./scripts/dev.sh npm exec eslint resources/js/pages/Finance/Operations/DueCalendar.vue resources/js/constants/menu-sidebar.ts
./scripts/dev.sh npm exec -- prettier --check resources/js/pages/Finance/Operations/DueCalendar.vue resources/js/constants/menu-sidebar.ts
./scripts/dev.sh composer exec pint -- --test app/Modules/Finance app/Modules/Academic tests/Feature/Finance/Operations tests/Feature/Academic/ExamResit
git diff --check
```

If repo-wide `vue-tsc` remains blocked by container memory, record the filtered
diagnostics or baseline limitation and do not claim it passed.

## Acceptance Evidence

First-slice implementation (2026-06-21).

### What shipped

- `app/Modules/Finance/Support/ExamResitDueClassifier.php` (+ `ExamResitDueClassification`
  DTO): paid/overdue/needs_charge_or_dng/blocked classifier; overdue derived from
  scheduled sitting time + `late_payment_grace_days_snapshot` (default 14), with a
  stored `payment_deadline` taking precedence.
- `app/Modules/Finance/Support/ExamResitDngLinkResolver.php`: batched DNG↔attempt
  linkage (direct `finance_charge_id` + `dng_payment_request_charges` pivot) and
  `touchLinkedAttempts` mirroring.
- `app/Modules/Finance/Support/ExamResitDueRowPresenter.php`: shared PTL row context.
- `ListDueItemsQuery` enriched (PTL rows get source context + reminder_state) with
  new `source` / `fee_type` filters; non-PTL rows unchanged. New
  `ListExamResitHandoffQuery` surfaces overdue exam-resit sources without an active
  DNG (needs_charge_or_dng, not remindable), DB-scoped + bounded.
- `BillingOperationsController::dueCalendar` passes `handoffItems` + the new filters.
- `SendDueItemRemindersAction` / `SendDueItemParentRemindersAction` mirror
  `last_reminded_at` onto linked attempts only after a successful send (recipient
  selection + delivery unchanged — hard gates respected).
- `DueCalendar.vue`: source/fee-type filters, PTL badges + exam context, remindable
  gating (non-remindable rows cannot be selected), and an overdue-PTL handoff list
  linking to the DNG flow. Sidebar entry moved from Legacy → Finance Office
  `Thu & Đối soát` (`menu-sidebar.ts`).

### Commands run

```
./scripts/dev.sh test tests/Feature/Finance/Operations/ExamResitDueClassifierTest.php \
    tests/Feature/Finance/Operations/DueReminderExamResitTest.php
# => 13 passed (63 assertions)

./scripts/dev.sh test tests/Feature/Finance/Operations \
    tests/Feature/Finance/SendDueItemRemindersActionTest.php \
    tests/Feature/Finance/SendDueItemParentRemindersActionTest.php
# => 62 passed, 1 failed (BillingExceptionsPaginationTest — pre-existing query-count
#    boundary failure, fails in isolation, touches none of this story's code)

./scripts/dev.sh test tests/Feature/Academic/ExamResit tests/Feature/Finance/Batch
# => 118 passed (401 assertions) — Academic source + Batch reminder/DNG safety unchanged

./scripts/dev.sh composer exec pint -- --test <changed php>   # clean after auto-fix
./scripts/dev.sh npm exec -- eslint  resources/js/pages/Finance/Operations/DueCalendar.vue resources/js/constants/menu-sidebar.ts   # clean
./scripts/dev.sh npm exec -- prettier --check <same>          # unchanged (formatted)
git diff --check                                              # no whitespace errors
```

### Baseline gaps / notes

- Whole-project `vue-tsc` is not run here (documented to OOM in the dev container);
  per-file ESLint is clean. Run type-check on host/CI.
- `DueCalendar.vue` keeps its existing `router.get` filter pattern (not migrated to
  `useDataTable`) to stay minimal-diff on a high-risk, existing-behavior surface.
- Handoff (`needs_charge_or_dng`) rows are surfaced via a dedicated, separately
  paginated companion list on the same page rather than interleaved into the DNG
  paginator — keeps the main list DB-paginated with no full-table materialization.
- `payment_deadline` / `payment_overdue_at` columns are not written by ACAD-RET-001;
  overdue is derived live. No historical paid/DNG evidence is overwritten.
