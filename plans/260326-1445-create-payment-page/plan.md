---
title: "Manual Payment Create Page"
description: "Staff records cash/bank transfer payments already received. NOT for DNG gateway."
status: approved
priority: P1
effort: 4h
branch: dev
tags: [finance, payments, vue, inertia]
created: 2026-03-26
---

# Manual Payment Create Page

## Scope

**For**: Staff manually recording payments already received (cash, bank transfer, other).
**Not for**: DNG gateway payments (own flow: DngPaymentRequest → webhook → bridgeToPayment).
**Not for**: Bulk import (has own Import page).

## Overview

Add `create`/`store` to `PaymentController`. Vue full-page at `/finance/payments/create`. Two modes:

1. **Manual mode**: Staff enters amount freely, picks method/date/notes → `recordPayment()`
2. **Record + Auto-allocate mode**: Staff enters amount, ticks "auto-allocate" → `recordPayment()` then `autoAllocatePayment()` (oldest_first strategy, system-controlled)

## Charges Reference Panel

Outstanding charges displayed as **read-only list** (no checkboxes). Provides:
- Total outstanding balance shown as summary number
- "Use outstanding total" button to prefill amount field
- Individual charge rows are informational only — no selection affordance
- Staff cannot select which charges to allocate; auto-allocate always uses oldest_first

This avoids checkbox semantics that imply "selection for action" when backend doesn't honor per-charge selection.

## Phases

| # | Phase | Status | File |
|---|-------|--------|------|
| 1 | Backend: Route, Controller, FormRequest | pending | [phase-01](./phase-01-backend.md) |
| 2 | Frontend: Create.vue page | pending | [phase-02](./phase-02-frontend.md) |
| 3 | Integration: Button on Index, test | pending | [phase-03](./phase-03-integration.md) |

## Key Decisions

- **Reuse** `PaymentService::recordPayment()` — no new service method
- **Reuse** `PaymentService::autoAllocatePayment()` — oldest_first, system-controlled
- **Reuse** `StudentCombobox.vue` for student search
- **Reuse** `getOutstandingCharges()` / `getStudentBalance()` for reference display
- **Route**: `/finance/payments/create` (matches existing finance route prefix)
- **Charges endpoint**: JSON route within finance web group, gated by `create_finance_payment` permission
- **FormRequest**: `StorePaymentRequest` — enforces `method` in [cash, bank_transfer, other] (excludes gateway, import; wallet deferred to v2 if business confirms)
- **source convention**: Enum values — `manual_cash`, `manual_bank_transfer`, `manual_other`. Auto-derived from method on backend, not free-text input.
- **No new Action** — controller calls PaymentService directly (matches `storeImport` pattern)
- **Permission**: `create_finance_payment` — applied to both create page and charges endpoint
- **auto_allocate UX**: Checkbox labeled "Automatically apply to outstanding charges (oldest first)" — clear it's system-controlled
- **Overpayment**: Allowed — unapplied amount stays as credit (existing `unapplied_amount` accessor)

## Test Strategy

- Store valid data → Payment created, status=completed, source=manual_cash, redirect to Show
- Store with auto_allocate=true → Payment created + allocations exist (oldest_first)
- Store with method=gateway → 422 validation error
- Store with method=import → 422 validation error
- Store with amount=0 → 422
- Store with future paid_at → 422
- Overpayment → Payment created, unapplied_amount > 0
- Charges endpoint without permission → 403

## Dependencies

- `StudentCombobox.vue` — emits `select` with student data
- `useApi` composable — fetch charges after student selection
- `PaymentService` — `recordPayment()`, `autoAllocatePayment()`, `getOutstandingCharges()`, `getStudentBalance()`
