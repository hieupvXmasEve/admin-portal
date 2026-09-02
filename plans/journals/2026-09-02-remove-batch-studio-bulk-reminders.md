---
title: Remove Batch Studio bulk reminders
date: 2026-09-02
summary: Deleted /finance/batch-studio/reminders; due-calendar remains the only staff reminder surface.
---

# Remove Batch Studio bulk reminders

## What happened
Removed the Batch Studio "Nhắc nợ hàng loạt" wizard because Due Calendar already owns sending due-item reminders.

## Decision
Delete the wrapper (page, GET/POST/preview routes, unique assembler/FormRequests, hub tile). Delete the Charges/Invoices/Payments "Nhắc nợ hàng loạt" lookup-bar button. Old URLs 404 — no redirect. Keep SendDueItemRemindersAction, SendDueItemParentRemindersAction, ListDueItemsQuery, DueCalendar.

## Verification
BatchStudioAuthzTest (incl. 404), BatchPreviewLineTest, SendDueItem* tests, DueReminderExamResitTest passed. Review 9/10, 0 critical. Regenerated ziggy.js.

## Next steps
Commit if requested. Portal impact: none.

> Historical work record — not durable authority. Prefer docs/specs/ADRs for current decisions.
