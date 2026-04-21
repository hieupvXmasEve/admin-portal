## Context Links

- [SendPaymentRemindersAction.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php)
- [EmailService.php](/Users/hunt2412/hieupvdev/project/swinx/app/Services/EmailService.php)
- [create_email_logs_table.php](/Users/hunt2412/hieupvdev/project/swinx/database/migrations/2025_08_13_192654_create_email_logs_table.php)

## Overview

- Priority: High
- Status: pending
- Purpose: refactor delivery from single student email per invoice to parent fanout with explicit audit semantics.

## Key Insights

- `EmailService::sendSingleEmail()` already writes `email_logs`; no new audit table required for MVP.
- `student_invoices.last_reminder_at` is invoice-level only; it cannot answer which parent got the email.
- Existing action increments sent/failed per invoice; new plan needs counter semantics per email attempt and per skipped invoice/student.

## Delivery Rules

- For each unpaid invoice, send to each deduped linked parent email.
- Update `last_reminder_at` once invoice has at least one successful parent send in this request.
- Catch per-recipient failures; continue remaining recipients/invoices.
- Return counters:
  - `sent_count`
  - `failed_count`
  - `skipped_no_balance_count`
  - `skipped_no_parent_email_count`
  - `students_processed_count`

## Related Code Files

- Modify: `app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php`
- Optional create: `app/Modules/Finance/Data/ReminderRecipientData.php` or lightweight array contract only

## Implementation Steps

1. Split action into resolution step and delivery loop.
2. Reuse existing payment reminder email template data, but ensure student/invoice values come from invoice context.
3. Deduplicate recipient emails before send.
4. On each success, rely on `email_logs` for audit trail.
5. Update `invoice.last_reminder_at` once per invoice after first success.
6. Aggregate response counters and user-facing message.

## Risk Assessment

- High likelihood / medium impact: partial parent sends leave mixed state. Mitigation: invoice timestamp updates after first success; response includes failures.
- Medium likelihood / high impact: large selections create slow synchronous requests. Mitigation: MVP limit selected rows in UI or server validation; queue/batch as follow-up only if needed.

## Backwards Compatibility

- Do not remove existing reminder email template.
- Keep route name stable if possible; only payload changes.
- If another page still sends `invoice_ids`, either keep compatibility temporarily or move that page in same change window.

## Rollback

- Restore prior invoice loop and student-email-only recipient.
- Ignore parent resolution path.

## Success Criteria

- Parent fanout works without new schema.
- Audit remains queryable through `email_logs`.

## Unresolved Questions

- Whether to store extra reminder metadata inside `email_logs.metadata` for easier filtering.
