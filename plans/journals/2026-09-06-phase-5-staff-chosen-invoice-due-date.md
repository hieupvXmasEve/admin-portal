---
title: Phase 5 staff-chosen invoice due date
date: 2026-09-06
summary: Invoice due_date is the single staff-chosen date; Batch Studio no longer invents now()+30d.
---

# Phase 5 staff-chosen invoice due date

## What happened
Phase 5 of `plans/260904-1114-finance-flow-redesign` landed. `student_invoices.due_date` was NOT NULL and five Finance writers filled `now()+30d`. Staff already chose a date at DNG commit, but that date never reached the invoice, so portal overdue and aging used a fabricated date. Reused invoices kept the first batch's date forever.

## Decision
Authoritative due date = staff date at DNG commit. Charge generation does not invent a date (null until chosen). Existing invoices are updated through `InvoiceGenerationService::applyStaffChosenDueDate` inside `SettlementMutationGuard` (`markChanged`). Reminders read invoice due_date first via `StaffChosenDueDate`. Column made nullable. No `billing_cycles` writer.

## Checks
- `addDays(30)` gone from `app/Modules/Finance`
- StaffChosenDueDateTest, CreateBatchDngFromChargesActionTest, GenerateEgcChargesTest: 28 passed after widening EGC `?string $dueDate` helpers (TypeError under strict_types)
- StudentFinancePresentationTest + CollectionProgressViewTest + GetStudentFeeSummaryQueryTest passed
- GetDueInvoicesSummaryQueryTest failed `missing_currency` on a fixture without obligation — same class of failure independent of due_date
- BatchChargeCommitTest EGC-only token 404 for Student 1 is pre-existing on HEAD

## Next steps
Phase 6 tuition notice. Before production land, count invoices whose overdue/open status will flip when staff next commits a DNG due date. Do not commit from this session unless asked.

> Historical work record — not durable authority. Prefer docs/specs/ADRs for current decisions.
