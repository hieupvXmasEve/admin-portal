## Context Links

- [Dashboard.vue](/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Finance/Operations/Dashboard.vue)
- [DueCalendar.vue](/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Finance/Operations/DueCalendar.vue)
- [api.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/routes/api.php)

## Overview

- Priority: Medium
- Status: pending
- Purpose: add student-based bulk action in dashboard and verify behavior with targeted tests.

## UI Changes

- Add row selection to `Dashboard.vue` table.
- Bulk action button label should stay student-oriented, e.g. `Send Parent Reminders (N)`.
- Button enabled only when at least one student selected.
- On success, clear selection and optionally reload only students data.
- Show summary toast using backend counters; do not over-promise all sends succeeded.

## API Changes

- Keep `POST /api/v1/finance/operations/send-reminders`.
- Change payload to `student_ids[]` and optionally `semester_id`.
- No new route needed for MVP unless invoice-based due-calendar reminder flow must remain separate.

## Affected Files

- Modify: `resources/js/pages/Finance/Operations/Dashboard.vue`
- Optional modify: `resources/js/types/finance.ts` or local interfaces if adding reminder stats to rows
- Modify: `app/Modules/Finance/routes/api.php` only if route split needed

## Implementation Steps

1. Add row selection state to dashboard table.
2. Keep selection scoped to student rows, not invoice rows.
3. Add bulk action button near dashboard actions.
4. Submit `student_ids` to existing reminder endpoint.
5. Reload relevant Inertia props after success.
6. Add/adjust tests for API and UI interaction.

## Test Matrix

- Unit frontend: selection count, button disable/enable, payload shape.
- Feature/API: selected students with mixed balances/parents returns correct counters.
- Integration: `last_reminder_at` updates only for invoices with at least one successful send.
- Regression: due calendar still renders and its reminder UI is either removed or explicitly left unsupported.

## MVP Scope Recommendation

- Use dashboard only.
- Reuse existing route.
- No new DB table.
- No per-parent reminder history UI.
- No async batch job unless measured latency forces it.

## Rollback

- Remove dashboard selection + button.
- Restore prior reminder flow ownership to due calendar only.

## Success Criteria

- Finance staff can perform reminder sends from student list without switching to invoice list.
- UI stays aligned with existing student-based workflow.

## Unresolved Questions

- Should due calendar reminder action be removed, kept invoice-based, or redirected to the new student-based flow?
