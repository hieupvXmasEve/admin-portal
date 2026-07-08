# Forfeit defer closes the original obligation without unpaid penalty debt

**Status:** Accepted
**Date:** 2026-07-06
**Owner:** Academic + Finance

## Context

When a student defers without preserving tuition, Swinx must decide whether the
old fee remains collectible, whether already-paid money is kept, and whether any
unpaid portion becomes a penalty.

Keeping the old obligation collectible beside the later return-study obligation
would double-count the same deferred scope. Treating unpaid balance as forfeited
money would invent money the school never received.

## Decision

For `FORFEIT` defer, the original obligation for the deferred scope is closed by
the defer lifecycle and must no longer be collectible. Already-paid real cash is
consumed as forfeited cash, capped by actual payment evidence. Any unpaid
portion is not preserved, not consumed, and not converted into penalty debt.

Consuming already-paid cash requires a separate free-text Finance reason. This
forfeit reason is distinct from the Academic reason for the defer action. It
does not require a separate permission and must not be constrained to a fixed
reason list.

A separate Finance review decision may still require collection when there is a
specific business policy that the school has already delivered a service and the
student still owes money. That is outside automatic `FORFEIT` settlement.

Unpaid early-study defer inside the first two weeks is also outside `FORFEIT`:
there is no paid cash to consume, and the old obligation is closed rather than
collected. See ADR-0025.

## Boundaries

- This decision applies to defer/bảo lưu settlement, not internal transfer.
- This decision does not create unpaid penalty or forfeiture debt.
- Every `FORFEIT` settlement that consumes paid cash must record a free-text
  forfeit reason. Missing reason stops the mutation.
- Automatic full-scope `FORFEIT` settlement is allowed only when the source
  obligation maps directly to the deferred scope and there is no live DNG
  request, discount/scholarship ambiguity, allocation ambiguity, or
  delivered-service collection policy to review.
- Live DNG-linked cases must follow the DNG cancellation lifecycle before the
  linked obligation is closed or consumed.
- Course-scope defer against semester-level tuition remains manual Finance
  review unless a later accepted decision defines a safe allocation formula.

## Consequences

- A no-preserve defer does not leave both the old obligation and the future
  return-study obligation collectible.
- Finance can audit kept money as real paid cash, not as synthetic receivable.
- Cases where the school intentionally still wants to collect unpaid money need
  explicit Finance review rather than automatic defer settlement.

## Implementation Boundary

This ADR records business semantics for a Finance concern (defer/bảo lưu, settlement, tuition entitlement, or transfer). It does not authorize Academic and Finance to share Eloquent models, foreign keys, or polymorphic source pointers.

Any implementation of this decision must enter Finance through the Finance Intake Contract defined in ADR-0026, using the neutral Obligation Source Reference (`source_system`, `source_kind`, `source_ref`). Academic may send pricing facts and may keep local projections for display, but Finance owns pricing, obligations, credits, discounts, settlement, DNG, and audit.

If current code uses legacy links such as `finance_charge_id`, `source_type/source_id`, or direct `App\Models\FinanceCharge` access, treat that as migration context only, not target architecture.
