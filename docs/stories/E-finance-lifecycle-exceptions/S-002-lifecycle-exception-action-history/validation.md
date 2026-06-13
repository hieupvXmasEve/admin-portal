# Validation

## Proof Strategy

Prove that every lifecycle exception action writes an immutable event, the
shared history page renders searchable action history across DNG payment
requests, and permission/campus scope matches the lifecycle exception page.

Also prove the history remains accessible from the standalone route after the
current DNG request leaves the active lifecycle exception queue because it was
cancelled or resolved.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | History event builder maps acknowledge, keep-as-debt, route-to-settlement, cancel, void, success, and failure outcomes. |
| Unit | History event metadata keeps impact preview and failure summaries without storing computed balances as source of truth. |
| Integration | Acknowledge appends an `acknowledged` event and updates the current review state. |
| Integration | Keep-as-debt after acknowledge appends a second event without overwriting the first event. |
| Integration | Route-to-settlement appends an event with staff reason and linked context metadata. |
| Integration | DNG cancellation appends `cancel_requested` and then `cancel_succeeded` or `cancel_failed`. |
| Integration | Charge voiding appends `void_requested` and then `void_succeeded` or `void_failed`. |
| Integration | Shared history route returns events for a DNG request that no longer appears in the active lifecycle exception list. |
| Integration | Shared history filters work by DNG request id, DNG item id, student search, actor, event type, review status, exception reason, and date range. |
| Integration | Lifecycle exceptions `View history` action deep-links to the shared history page with the DNG request filter applied. |
| Authorization | Users without lifecycle exception view permission cannot open the history page. |
| Authorization | Campus-scoped users cannot view history for out-of-scope DNG requests. |
| Frontend | Finance Operations menu exposes the standalone history page. |
| Frontend | Lifecycle exceptions table and detail drawer render a `View history` action using the shared history route. |
| Frontend | History page renders filters, paginated table, row expansion or detail panel, empty state, failure state, and long reasons without text overflow. |
| Logs/Audit | Event rows include actor, action type, target DNG request id, timestamps, before/after status, and staff reason. |
| Regression | Existing DNG payment request audit and cancellation pages still behave as before. |

## Fixtures

- Deferred student with overdue `pushed_to_dng` request.
- Dropout student with overdue `pushed_to_dng` request.
- Lifecycle exception review with no history events for legacy empty-state
  coverage.
- Resolved lifecycle exception review whose DNG request no longer appears in the
  active lifecycle exception list.
- Lifecycle exception review with acknowledge, keep-as-debt, and
  route-to-settlement events.
- DNG request eligible for cancellation.
- DNG request where cancellation fails.
- DNG request linked to one finance charge.
- User with lifecycle exception view permission.
- User without lifecycle exception view permission.
- Campus-scoped users for in-scope and out-of-scope DNG requests.

## Commands

Add exact commands after implementation exists. Expected targeted checks:

```text
./scripts/dev.sh test tests/Feature/Finance/Operations/LifecycleDueExceptionHistoryTest.php
./scripts/dev.sh test tests/Feature/Finance/Operations/LifecycleDueExceptionsTest.php
./scripts/dev.sh test tests/Feature/Finance/Dng
./scripts/dev.sh composer exec pint -- --dirty
./scripts/dev.sh npm exec eslint -- resources/js/pages/Finance/Operations/LifecycleExceptionHistory.vue resources/js/pages/Finance/Operations/LifecycleExceptions.vue
./scripts/dev.sh npm exec prettier --check resources/js/pages/Finance/Operations/LifecycleExceptionHistory.vue resources/js/pages/Finance/Operations/LifecycleExceptions.vue
./scripts/dev.sh npm run type-check
```

## Acceptance Evidence

Add command output, browser screenshots, and representative history rows after
implementation. Do not mark this story implemented until at least one
multi-action lifecycle exception can be proven from database event rows through
the rendered history page.
