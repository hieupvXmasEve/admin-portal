# Validation

## Proof Strategy

Prove that normal Due Calendar reminders only target active billable students,
that lifecycle-blocked DNG requests remain visible on a separate review page, and
that destructive resolutions cannot bypass permission, current-state checks, or
audit reason requirements.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Active-due predicate includes only `Student::FINANCIAL_STATUSES` and excludes deferred/dropout/dropout_transfer. |
| Unit | Exception reason resolver maps deferred, dropout, dropout_transfer, inactive/non-financial, and missing-student rows correctly. |
| Integration | Due Calendar list excludes lifecycle exceptions from rows, summary counts, export, and pagination totals. |
| Integration | Lifecycle exception page lists the excluded DNG requests with the same semester/campus scope as Finance operations. |
| Integration | Student reminder action skips crafted item ids for deferred/dropout students and reports skipped counts. |
| Integration | Parent reminder action skips crafted item ids for deferred/dropout students and reports skipped counts. |
| Integration | Acknowledge/keep-as-debt writes review status, reason, acting user id, and does not mutate DNG or charges. |
| Integration | `cancel_dng` refuses paid, reconciled, cancelled, missing, or non-cancellable DNG requests. |
| Integration | `cancel_dng_and_void_linked_charge` requires `void_finance_charges` and records every affected linked charge/invoice line in metadata. |
| Authorization | Users without view permission cannot open the page. |
| Authorization | Users without `create_finance_payments` cannot cancel DNG requests from the page. |
| Authorization | Users without `void_finance_charges` cannot run charge-voiding resolution. |
| Frontend | Page renders filters, summary cards, table, detail drawer, empty state, and modal without text overflow at desktop and mobile widths. |
| Frontend | Destructive actions show impact preview and require a non-empty reason before submit. |
| Logs/Audit | Review records and DNG cancel audit payload/response are persisted for successful destructive actions. |
| Regression | Existing `/finance/dng/payment-requests` cancel behavior still works and remains terminal for late webhook/reconciliation events. |

## Fixtures

- Student in `intake_course` with overdue `pushed_to_dng` request.
- Student in `deferred` with overdue `pushed_to_dng` request and matching
  defer action/defer case when possible.
- Student in `dropout` with overdue `pushed_to_dng` request.
- Student in `dropout_transfer` with overdue `pushed_to_dng` request.
- Student in a non-financial status such as `pending` or `inactive` with an open
  DNG request.
- DNG request already paid/bridged to a payment.
- DNG request linked to one charge.
- DNG request linked to multiple charges through `DngPaymentRequestCharge`.
- User with view-only finance operations permission.
- User with DNG cancel permission.
- User with charge void permission.

## Commands

Add exact commands after implementation exists. Expected targeted checks:

```text
./scripts/dev.sh test tests/Feature/Finance/Operations/LifecycleDueExceptionsTest.php
./scripts/dev.sh test tests/Feature/Finance/Operations/DueCalendarLifecycleFilterTest.php
./scripts/dev.sh test tests/Feature/Finance/Dng
./scripts/dev.sh composer exec pint -- --dirty
./scripts/dev.sh npm exec eslint -- resources/js/pages/Finance/Operations/LifecycleExceptions.vue resources/js/pages/Finance/Operations/DueCalendar.vue
./scripts/dev.sh npm exec prettier --check resources/js/pages/Finance/Operations/LifecycleExceptions.vue resources/js/pages/Finance/Operations/DueCalendar.vue
./scripts/dev.sh npm run type-check
```

## Acceptance Evidence

Add command output, browser screenshots, and any production/backfill audit counts
after verification exists. Do not mark the story implemented until lifecycle
exceptions are proven excluded from Due Calendar and included in the new review
page.
