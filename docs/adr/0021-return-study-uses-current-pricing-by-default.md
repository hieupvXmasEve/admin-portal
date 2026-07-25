---
id: ADR-0021
title: "Return-study uses current pricing by default"
status: accepted
date: 2026-07-06
owner: "Academic + Finance"
last_verified: 2026-07-25
scope: architecture-decision
---

# Return-study uses current pricing by default

## Context

`PRESERVE` defer protects real paid cash, but it could be mistaken for a promise
that the student keeps the old tuition price or old fee rules. That would turn a
cash carry-forward decision into a price guarantee and could undercharge or
misprice the return-study period.

## Decision

When a student returns from defer, Swinx prices the return-study obligation using
the current tuition, fee, discount, scholarship, campus, program, and term rules
that apply to the return-study period. Preserved cash is applied only after that
new obligation is created.

For term-level tuition, this pricing applies to the return-study entitlement,
not to individual delayed course offerings. Once the relevant term entitlement
is settled, covered courses that are only offered later do not create new
tuition charges merely because they occur in a later term; see ADR-0023.

Keeping the old price or old fee rule is allowed only through an explicit
Finance review decision. It is not inferred from `PRESERVE`.

## Boundaries

- This decision applies to defer/bảo lưu settlement.
- Preserved cash is not a price lock.
- Old tuition, discount, scholarship, installment, or fee rules must not be
  cloned from the original deferred obligation onto the return-study obligation.
- Current pricing must not be interpreted as a per-course charge formula for
  term-level tuition.
- If the return also changes campus or program, that transfer dimension must
  follow the separate internal-transfer settlement boundary.

## Consequences

- Return-study charges reflect the student's actual return-study context.
- Preserved cash can reduce the amount collected without hiding the true current
  obligation.
- Any promise to keep an old price becomes auditable Finance review, not silent
  automatic behavior.

## Implementation Boundary

This ADR records business semantics for a Finance concern (defer/bảo lưu, settlement, tuition entitlement, or transfer). It does not authorize Academic and Finance to share Eloquent models, foreign keys, or polymorphic source pointers.

Any implementation of this decision must enter Finance through the Finance Intake Contract defined in ADR-0026, using the neutral Obligation Source Reference (`source_system`, `source_kind`, `source_ref`). Academic may send pricing facts and may keep local projections for display, but Finance owns pricing, obligations, credits, discounts, settlement, DNG, and audit.

If current code uses legacy links such as `finance_charge_id`, `source_type/source_id`, or direct `App\Models\FinanceCharge` access, treat that as migration context only, not target architecture.
