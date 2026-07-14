# Tuition Settlement Model v2

Last updated: 2026-03-25
Status: Proposed architecture baseline
Owner: Finance / Platform

## Goal

Fix tuition settlement so the system can correctly handle:

- multi-line invoices
- voucher and scholarship reductions
- payments that span multiple invoices for the same student
- void on a single fee line
- releasing the correct remaining cash for future invoices
- staff-facing balances that match business truth

This document defines the target source of truth, schema delta, and void/release flow.

## Current Problem

The current finance model mixes:

- charge generation
- invoice rendering
- discount representation
- payment allocation

Current production path:

- `student_invoices`
- `invoice_lines`
- `finance_charges`
- `payments`
- `payment_allocations(charge_id)`

Main defect:

- voucher is represented as a negative charge/invoice line
- payment is allocated to positive `charge_id`
- when one line is voided, the system cannot deterministically release the exact amount that should remain available for the next invoice

This causes cases where:

- invoice net payable is `10M`
- raw allocation is still `15M`
- staff sees contradictory numbers

## Architecture Principles

1. `Payment` is the canonical cash receipt for a student.
2. `Payment applications` spend that receipt across invoice lines, possibly across multiple invoices of the same student.
3. Student available cash is a derived aggregate, not a primary ledger object.
4. Voucher and scholarship are non-cash reductions, not payment rows and not negative charge settlement targets.
5. Void never deletes financial history. It reverses or releases rows with audit trail.

## Source of Truth Matrix

| Entity                         | Source of truth                                                  | Derived/cache                                |
| ------------------------------ | ---------------------------------------------------------------- | -------------------------------------------- |
| Cash received                  | `payments`                                                       | —                                            |
| Cash applied to invoice line   | `payment_applications`                                           | —                                            |
| Unapplied cash per payment     | derived from `payment_applications`                              | `payments.cached_unapplied_amount` if needed |
| Available cash per student     | aggregate of unapplied cash across payments                      | do not store as primary field                |
| Non-cash reduction             | `invoice_discounts`                                              | —                                            |
| Discount allocation to line    | `discount_allocations`                                           | —                                            |
| Student-level non-cash balance | `student_balance_ledger`                                         | —                                            |
| Invoice totals                 | derived from lines + discount allocations + payment applications | `cached_total_*`                             |

## Current-to-Target Mapping

Keep:

- `student_invoices`
- `invoice_lines`
- `payments`
- `voucher_applications`
- `invoice_discounts` as the canonical discount header table

Add:

- `payment_applications`
- `discount_allocations`

Deprecate as settlement truth:

- `payment_allocations`
- negative `finance_charges` used as voucher or scholarship settlement rows

Do not revive legacy path:

- `invoice_items`
- `InvoicePaymentService`

## Target Data Model

### 1. Invoice lines

`invoice_lines` remains the canonical billable line table.

Required delta:

```sql
ALTER TABLE invoice_lines
  ADD COLUMN status TEXT NOT NULL DEFAULT 'active'
    CHECK (status IN ('active', 'void')),
  ADD COLUMN voided_at TIMESTAMPTZ,
  ADD COLUMN void_reason TEXT;
```

Rules:

- void changes status, does not delete the row
- only `status = active` lines participate in current payable totals

### 2. Payment applications

Replace `payment_allocations(charge_id)` with line-level applications.

```sql
CREATE TABLE payment_applications (
  id               UUID PRIMARY KEY,
  payment_id       UUID NOT NULL REFERENCES payments(id),
  invoice_line_id  UUID NOT NULL REFERENCES invoice_lines(id),
  amount           NUMERIC(15,2) NOT NULL,
                   CHECK (amount <> 0),

  entry_type       TEXT NOT NULL CHECK (entry_type IN ('application', 'reversal')),
  source_ref_id    UUID,
  source_ref_type  TEXT,

  applied_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_by       BIGINT REFERENCES users(id)
);
```

Rules:

- one payment can apply to many lines
- one payment can cross invoices for the same student
- application entries use positive `amount`; reversal entries use negative `amount`
- cumulative applied amount on a line must never exceed the outstanding net due of the line at time of application
- canonical query for unapplied cash:

```sql
payments.amount - COALESCE(SUM(payment_applications.amount), 0)
```

Recommended implementation pattern:

- never update existing financial entries for settlement math
- insert a negative reversal entry when cash must be released back to the payment

This is the immutable ledger pattern to standardize in BE.

### 3. Invoice discounts

Use `invoice_discounts` as the canonical header table for all non-cash reductions.

Required semantic delta:

- `voucher`
- `scholarship`
- `waiver`
- `early_payment`

Suggested target shape:

```sql
CREATE TABLE invoice_discounts (
  id              UUID PRIMARY KEY,
  invoice_id      UUID NOT NULL REFERENCES student_invoices(id),
  discount_type   TEXT NOT NULL CHECK (discount_type IN (
                    'voucher', 'scholarship', 'waiver',
                    'early_payment'
                   )),
  source_ref_id   UUID,
  source_ref_type TEXT,
  total_amount    NUMERIC(15,2) NOT NULL,
  status          TEXT NOT NULL DEFAULT 'active'
                  CHECK (status IN ('active', 'reversed', 'expired')),
  approved_by     BIGINT REFERENCES users(id),
  memo            TEXT,
  created_at      TIMESTAMPTZ DEFAULT now()
);
```

### 4. Discount allocations

Store how each discount is allocated down to line level.

```sql
CREATE TABLE discount_allocations (
  id                    UUID PRIMARY KEY,
  invoice_discount_id   UUID NOT NULL REFERENCES invoice_discounts(id),
  invoice_line_id       UUID NOT NULL REFERENCES invoice_lines(id),
  amount                NUMERIC(15,2) NOT NULL,
                        CHECK (amount <> 0),

  entry_type            TEXT NOT NULL CHECK (entry_type IN ('allocation', 'release', 'reversal')),
  source_ref_id         UUID,
  source_ref_type       TEXT,

  created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
  allocation_rule       TEXT NOT NULL
);
```

Rules:

- default allocation rule is current line chronology order; explicit `seq_order` is deferred from v2
- allocation entries use positive `amount`; release/reversal entries use negative `amount`
- discount allocation participates in line net due
- if a line holding discount is voided, discount must be reallocated to the next eligible active line when business rule says the discount stays

### 5. Student balance ledger

`student_balance_ledger` is only for student-level non-cash balance adjustments.

```sql
CREATE TABLE student_balance_ledger (
  id              UUID PRIMARY KEY,
  student_id      BIGINT NOT NULL,
  amount          NUMERIC(15,2) NOT NULL,
  direction       TEXT NOT NULL CHECK (direction IN ('credit', 'debit')),
  source_type     TEXT NOT NULL CHECK (source_type IN (
                    'manual_student_credit',
                    'refund_reversal',
                    'waiver_adjustment'
                   )),
  source_ref_id   UUID,
  status          TEXT NOT NULL DEFAULT 'unapplied'
                  CHECK (status IN ('unapplied', 'applied', 'expired')),
  memo            TEXT,
  created_at      TIMESTAMPTZ DEFAULT now()
);
```

Explicit rule:

- `line_void` from cash-paid lines must not create a `student_balance_ledger` row
- released cash goes back to the original `payment` via derived unapplied balance

### 6. Cached invoice totals

Rename invoice totals so no one treats them as primary truth.

```sql
ALTER TABLE student_invoices RENAME COLUMN total_gross TO cached_total_gross;
ALTER TABLE student_invoices RENAME COLUMN total_discount TO cached_total_discount;
ALTER TABLE student_invoices RENAME COLUMN total_net TO cached_total_net;
ALTER TABLE student_invoices RENAME COLUMN total_paid TO cached_total_paid;
```

Rules:

- cache only
- only written by `recalculate_invoice_snapshot()`
- no other write path allowed

## Core Business Rules

1. Voucher allocation strategy: current line chronology order in v2; explicit sequence management is deferred
2. If one line is voided, voucher remains and is reallocated according to the same rule
3. Payment applications must not exceed line net due
4. Only active lines and active discount allocations contribute to current payable
5. Payment unapplied balance is per payment, not a standalone student ledger object

## Void / Release Flow

Case:

- Line 1 gross `15M`
- Line 2 gross `15M`
- voucher `5M`
- voucher allocated to Line 1 by current line chronology order
- payment `25M`
- applications:
    - `10M` to Line 1
    - `15M` to Line 2

Void Line 2:

1. Reverse all active `payment_applications` on Line 2
2. Insert negative reversal entries for the released amount
3. Mark Line 2 `void`
4. Because voucher is on Line 1, no discount reallocation is needed in this specific case
5. Recalculate invoice snapshot

Result:

- Line 1 net due stays `10M`
- active applied cash stays `10M`
- payment unapplied becomes `15M`
- no `student_balance_ledger` row is created

Void Line 1:

1. Reverse active `payment_applications` on Line 1
2. Mark Line 1 `void`
3. Release active `discount_allocations` on Line 1
4. Reallocate the discount to the next eligible active line, which is Line 2 in this example
5. Recalculate line net due and active payment applications
6. Release any excess payment from Line 2 if it now exceeds the discounted net due

This second case is why discount allocation must be stored explicitly.

## Snapshot Recalculation

`recalculate_invoice_snapshot()` must derive:

- `cached_total_gross`
    - sum gross of active lines
- `cached_total_discount`
- sum net discount allocations on active lines
- `cached_total_net`
    - gross minus discount
- `cached_total_paid`
- sum net payment applications on active lines
- invoice status
    - `paid` when `cached_total_net <= cached_total_paid`
    - otherwise follow due-date and draft rules

No caller may update cached totals directly.

## Migration Strategy

Phase 1:

- create `payment_applications`
- create `discount_allocations`
- extend `invoice_lines`
- extend `invoice_discounts`

Phase 2:

- migrate negative voucher and scholarship charges into `invoice_discounts`
- migrate `payment_allocations(charge_id)` into `payment_applications(invoice_line_id)`
- calculate line-level net due under current line chronology order

Phase 3:

- switch read models and staff UI to new settlement truth
- remove legacy `payment_allocations` after runtime migration completes

Phase 4:

- drop old `payment_allocations` and `invoice_items` from active schema
- stop generating negative `finance_charges` for voucher or scholarship

### Post-canonical restore contract

After canonical Finance backfill, a supported restore begins only from a
database backup taken after that backfill. The running application contains no
command that upgrades an older business-data snapshot. To recover an older
snapshot, restore it with the matching pre-cleanup release before returning to
the current release.

## Guardrails

- Never store student available cash as the primary balance field
- Never apply payment above line net due
- Never delete financial history on void
- Never model voucher as a payment-equivalent row
- Never let a cash release create a second non-cash credit object

## Acceptance Example

Expected result for:

- 2 English levels = `30M`
- voucher = `5M`
- payment received = `25M`
- later void 1 level

Expected business truth:

- invoice keeps only the studied level
- voucher still applies using current line chronology order
- payment applications on the voided line are reversed
- released `15M` becomes unapplied cash on the original payment
- unapplied cash can be auto-applied to a future invoice for the same student

## Implementation Notes for Current Repo

Current repo confirms:

- `payments` belong to `student_id`, not `invoice_id`
- one payment can already span multiple unpaid invoices of the same student
- current auto-allocation path is charge-based and must be replaced or wrapped during migration

Relevant current files:

- [Payment.php](/Users/hunt2412/hieupvdev/project/swinx/app/Models/Payment.php)
- [PaymentAllocation.php](/Users/hunt2412/hieupvdev/project/swinx/app/Models/PaymentAllocation.php)
- [StudentInvoice.php](/Users/hunt2412/hieupvdev/project/swinx/app/Models/StudentInvoice.php)
- [InvoiceLine.php](/Users/hunt2412/hieupvdev/project/swinx/app/Models/InvoiceLine.php)
- [AutoAllocatePaymentsAction.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Actions/AutoAllocatePaymentsAction.php)

## Decision Summary

This architecture is approved when these three statements remain true:

> `Payment` is the canonical cash receipt of the student, not an invoice-bound object.
>
> `Payment applications` spend that receipt across invoice lines and may cross invoices within the same student.
>
> Student available cash is a derived aggregate, not a primary ledger object.
