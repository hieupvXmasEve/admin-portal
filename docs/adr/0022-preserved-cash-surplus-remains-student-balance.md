# Preserved cash surplus remains student balance

**Status:** Accepted
**Date:** 2026-07-06
**Owner:** Academic + Finance

## Context

When a student returns from defer, preserved cash may be greater than the newly
created return-study obligation. Swinx must decide whether the excess is
consumed, refunded, allocated elsewhere, or left visible for Finance follow-up.

Consuming the excess would take real paid cash without a matching obligation.
Refunding or reallocating it automatically would make a Finance decision without
checking legal applicability, student context, or other current obligations.

## Decision

When applicable preserved cash exceeds the return-study obligation, Swinx
allocates only up to the outstanding amount of that obligation. Any remaining
preserved or otherwise unapplied paid cash stays on the student's Finance
account as `Còn dư`.

For term-level tuition, a course that is covered by the settled term entitlement
but not offered until a later term is not a cash surplus. It remains covered
study scope; see ADR-0023.

The surplus may later be allocated to another valid obligation, refunded through
an approved refund flow, or left for Finance review. It must not be consumed by
defer settlement, hidden by the return-study flow, or pushed into DNG
collection.

## Boundaries

- This decision applies to defer/bảo lưu return-study settlement.
- DNG must not collect for an obligation already fully settled by preserved
  cash.
- Missing class availability does not turn part of term-level tuition into
  `Còn dư` when the covered study scope remains open.
- Surplus handling is a Finance follow-up workflow, not part of automatic
  return-study settlement.
- This decision does not create a new money ledger; existing payment,
  allocation, refund, and review mechanisms remain the source of truth.

## Consequences

- Student 360 and Finance review views must still surface the remaining `Còn dư`.
- Return-study settlement can produce zero amount due without losing the
  remaining paid balance.
- Any later allocation or refund has its own audit reason and approval path.

## Implementation Boundary

This ADR records business semantics for a Finance concern (defer/bảo lưu, settlement, tuition entitlement, or transfer). It does not authorize Academic and Finance to share Eloquent models, foreign keys, or polymorphic source pointers.

Any implementation of this decision must enter Finance through the Finance Intake Contract defined in ADR-0026, using the neutral Obligation Source Reference (`source_system`, `source_kind`, `source_ref`). Academic may send pricing facts and may keep local projections for display, but Finance owns pricing, obligations, credits, discounts, settlement, DNG, and audit.

If current code uses legacy links such as `finance_charge_id`, `source_type/source_id`, or direct `App\Models\FinanceCharge` access, treat that as migration context only, not target architecture.
