## Context Links

- [SendPaymentRemindersAction.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php)
- [BillingOperationsController.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Http/Api/Admin/BillingOperationsController.php)
- [GetBillingDashboardStudentsQuery.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Queries/Operations/GetBillingDashboardStudentsQuery.php)
- [Student.php](/Users/hunt2412/hieupvdev/project/swinx/app/Models/Student.php)
- [ParentProfile.php](/Users/hunt2412/hieupvdev/project/swinx/app/Models/ParentProfile.php)

## Overview

- Priority: High
- Status: pending
- Purpose: lock student-based request contract and unpaid-invoice/parent resolution path before touching UI.

## Data Flow

- Input: `student_ids[]`, optional `semester_id` if dashboard filter must constrain invoice scope.
- Transform:
  1. validate selected student ids
  2. load students in current campus
  3. eager-load parent profiles -> parent user
  4. load candidate invoices for those students
  5. compute live remaining via `SettlementService`
  6. keep only invoices with `remaining > 0`
  7. build recipient rows `{student_id, invoice_id, parent_user_id, recipient_email}`
- Output: normalized payload for delivery action plus preview counts.

## Requirements

- Keep selection student-based in dashboard.
- Reject empty selection.
- Campus scoping must remain enforced.
- Do not trust cached invoice totals alone; use settlement snapshot.
- Deduplicate recipients by `invoice_id + recipient_email`.

## Architecture

- Prefer new request contract in existing admin finance API controller.
- Keep heavy resolution logic out of controller; move to action/query helper if action file grows.
- MVP should not introduce background batch orchestration unless sync request proves too slow.

## Related Code Files

- Modify: `app/Modules/Finance/Http/Api/Admin/BillingOperationsController.php`
- Modify: `app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php`
- Optional create: `app/Modules/Finance/Queries/Operations/ResolveReminderRecipientsQuery.php`

## Implementation Steps

1. Change endpoint validation to accept `student_ids`.
2. Resolve selected students from dashboard scope, campus-scoped.
3. Load semester invoices for selected students; if semester-scoped, require and use `semester_id`.
4. Compute unpaid invoices using `deriveInvoiceSnapshot`.
5. Load linked parent profiles and their `user` relation.
6. Build deduped recipient fanout rows and skip rows with missing/invalid email.
7. Return counters that distinguish skipped reasons.

## Edge Cases

- Student selected but no invoices in current semester.
- Student has invoices but all fully settled.
- Parent profile exists but `user_id` null.
- Parent user exists but email blank/invalid.
- Two parent profiles point to same email.
- One student has multiple unpaid invoices.

## Test Matrix

- Unit: invoice filtering by `remaining > 0`, recipient dedupe, skipped reason counters.
- Integration: API rejects invalid ids, campus leakage blocked, semester filter respected.
- E2E: dashboard selected students invoke student-based endpoint and show summary toast.

## Rollback

- Revert request contract to `invoice_ids`.
- Remove student resolution query/helper.

## Success Criteria

- Backend accepts student ids and resolves only unpaid invoice + parent recipient rows.
- No sends attempted for students without outstanding balance.

## Unresolved Questions

- Semester-scoped only vs all unpaid invoices per selected student.
