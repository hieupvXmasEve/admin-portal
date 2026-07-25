---
id: ADR-0020
title: "Defer does not automatically carry forward discounts or scholarships"
status: accepted
date: 2026-07-06
owner: "Academic + Finance"
last_verified: 2026-07-25
scope: architecture-decision
---

# Defer does not automatically carry forward discounts or scholarships

## Context

`PRESERVE` defer carries forward real paid cash, but discount and scholarship
records reduce a specific fee obligation under a policy. Treating those
non-cash reductions like preserved cash can double-reduce future tuition or
apply an old rule to a different term, campus, program, or fee policy.

## Decision

When a student returns from defer, Swinx must not automatically carry forward
discounts or scholarships from the original deferred obligation. The
return-study obligation is created normally; preserved cash may settle it, but
discount/scholarship entitlement must be recalculated from the current rules or
confirmed through Finance review.

This keeps real cash separate from non-cash reductions. A discount or
scholarship is not a preserved balance.

## Boundaries

- This decision applies to defer/bảo lưu settlement only.
- Automatic defer settlement may release or consume real paid cash, but must not
  clone old discount/scholarship rows onto the return-study obligation.
- If keeping a scholarship/discount is a business promise, Finance review must
  record that decision and apply it to the return-study obligation explicitly.
- Any case where discount/scholarship meaning cannot be resolved from current
  rules stops for Finance review.

## Consequences

- Return-study charges show their own current discount/scholarship basis.
- Preserved cash allocation cannot accidentally stack with a copied old
  discount.
- Historical discount/scholarship records stay attached to the original
  obligation they reduced.

## Implementation Boundary

This ADR records business semantics for a Finance concern (defer/bảo lưu, settlement, tuition entitlement, or transfer). It does not authorize Academic and Finance to share Eloquent models, foreign keys, or polymorphic source pointers.

Any implementation of this decision must enter Finance through the Finance Intake Contract defined in ADR-0026, using the neutral Obligation Source Reference (`source_system`, `source_kind`, `source_ref`). Academic may send pricing facts and may keep local projections for display, but Finance owns pricing, obligations, credits, discounts, settlement, DNG, and audit.

If current code uses legacy links such as `finance_charge_id`, `source_type/source_id`, or direct `App\Models\FinanceCharge` access, treat that as migration context only, not target architecture.
