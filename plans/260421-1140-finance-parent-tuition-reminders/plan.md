---
title: "Finance parent tuition reminders"
description: "Plan student-based finance action that emails linked parents only for students with unpaid outstanding balance."
status: pending
priority: P2
effort: 6h
branch: dev
tags: [finance, reminders, parents, email, dashboard]
created: 2026-04-21
---

# Plan Overview

- Goal: add finance reminder send flow from student selection, emailing all linked parent users for selected students that still owe money.
- MVP: trigger from `Billing Dashboard`, keep selection student-based, send one reminder per unpaid invoice to each linked parent email, skip students with zero outstanding or no valid parent email.
- New DB tables: none for MVP. Reuse `email_logs` for delivery audit and keep `student_invoices.last_reminder_at` as invoice-level last send marker. Optional future table only if per-student/per-parent reminder history becomes required.

## Phases

| Phase | File | Status | Notes |
| --- | --- | --- | --- |
| 01 | `phase-01-backend-contract-and-query-shape.md` | pending | define student-based request, invoice/parent resolution, auth |
| 02 | `phase-02-reminder-delivery-and-audit.md` | pending | action refactor, email fanout, timestamp/audit behavior |
| 03 | `phase-03-dashboard-ui-and-tests.md` | pending | selection UX, API call, tests, rollout checks |

## Affected Files

- `app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php`
- `app/Modules/Finance/Http/Api/Admin/BillingOperationsController.php`
- `app/Modules/Finance/routes/api.php`
- `app/Modules/Finance/Queries/Operations/GetBillingDashboardStudentsQuery.php`
- `resources/js/pages/Finance/Operations/Dashboard.vue`
- Optional: new Finance request/action/query support files if current controller/action becomes too large

## API / UI Contract

- API request changes from `invoice_ids[]` to `student_ids[]` for the dashboard flow.
- Backend resolves selected students -> semester invoices with positive `remaining` -> linked parent profiles -> parent users with valid email.
- UI adds bulk selection + `Send Parent Reminders` action on dashboard; no change to student-based selection requirement.

## Dependencies

- Uses current settlement truth from `SettlementService::deriveInvoiceSnapshot`.
- Uses existing parent linkage: `Student -> parentProfiles() -> user`.
- Uses existing email infrastructure: `EmailService`, `email_logs`.

## Risks

- High: duplicate fanout if a parent is linked multiple ways or shares email across profiles. Mitigate by deduping per invoice + email address before send.
- Medium: misleading success counts if student selected but no unpaid invoices / no parent emails. Mitigate by returning detailed counters: skipped_no_balance, skipped_no_parent_email, failed_count, sent_count.
- Medium: `last_reminder_at` is invoice-level, not recipient-level. Mitigate by documenting MVP limitation and using `email_logs` for delivery audit.

## Rollback

- UI rollback: hide dashboard bulk reminder button.
- API rollback: keep old invoice-based endpoint or revert controller validation/action contract.
- Data rollback: none; only queued/sent emails and invoice `last_reminder_at` updates. No schema rollback needed for MVP.

## Success Criteria

- Admin can select students on dashboard and send reminders without leaving student list view.
- Only selected students with positive outstanding balance generate sends.
- All linked parent user emails for qualifying students are targeted once per unpaid invoice per request.
- Response counters explain sent vs skipped vs failed outcomes.
- No new tables required for MVP.

## Unresolved Questions

- Should student email continue receiving the same reminder in this flow, or parents only?
- Should `last_reminder_at` update when at least one parent send succeeds, or only when all targeted parents for that invoice succeed?
- Should reminder scope cover all unpaid invoices across semesters for the student, or only invoices in the dashboard-selected semester?
