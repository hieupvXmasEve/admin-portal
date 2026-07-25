---
id: ADR-0025
title: "Early-study defer window requires Academic and Student Services confirmation"
status: accepted
date: 2026-07-06
owner: "Academic + Finance + Student Services"
last_verified: 2026-07-25
scope: architecture-decision
---

# Early-study defer window requires Academic and Student Services confirmation

## Context

A student may start studying and then defer during the first two weeks from the
first class session of the covered study scope. At that point the school needs a
different rule from ordinary completed/failed/retake handling: the student has
started attendance, but the early window may still allow the term or selected
courses to be preserved.

## Decision

Within the early-study defer window, Academic Affairs and Student Services must
confirm that the case is eligible and define the covered study scope, usually
the whole term. If the student has fully paid the relevant fee, the paid fee is
preserved for that covered scope under the term tuition entitlement model. If
the student has partially paid, Finance preserves only the real paid cash as
available balance, closes the old obligation, and does not grant a full term
tuition entitlement from that partial payment alone. When the student returns,
Swinx creates the normal return-study obligation, applies the preserved balance
first, and collects any remaining amount. If the student has not paid, the old
obligation is closed and must no longer be collected; when the student returns,
they pay the new return-study obligation.

Finance must not split the paid fee into fixed per-course cash. The confirmation
establishes study scope; Finance then preserves real paid cash, closes the
unpaid old obligation, or applies the partial-payment rule according to the
payment state.

## Boundaries

- This decision applies only inside the first-two-week early-study defer window.
- Missing Academic Affairs or Student Services confirmation stops automatic
  preservation and routes the case to review.
- Paid means the relevant covered fee is confirmed fully paid. Unpaid means
  there is no preserved cash and no old collectible balance.
- Partial payment is not full payment: it preserves only actual paid cash as
  balance and does not create the full term tuition entitlement unless Finance
  and Student Services explicitly approve a top-up or exception.
- Ambiguous payment state stops for Finance review before money changes.
- The rule does not make failed-course retakes free. Retake charges remain a
  separate obligation after a clear failed result.
- Cases outside the early-study defer window need a separate decision before
  automatic Finance settlement.

## Consequences

- Students who paid and defer early keep the covered study right without binding
  cash to individual courses.
- Students who partially paid and defer early keep only the paid cash as balance
  unless an explicit review grants a fuller entitlement.
- Students who did not pay are not chased for the old early-deferred obligation;
  they pay when they return to study.
- Operations must capture the Academic Affairs and Student Services
  confirmation before Finance mutates money.

## Implementation Boundary

This ADR records business semantics for a Finance concern (defer/bảo lưu, settlement, tuition entitlement, or transfer). It does not authorize Academic and Finance to share Eloquent models, foreign keys, or polymorphic source pointers.

Any implementation of this decision must enter Finance through the Finance Intake Contract defined in ADR-0026, using the neutral Obligation Source Reference (`source_system`, `source_kind`, `source_ref`). Academic may send pricing facts and may keep local projections for display, but Finance owns pricing, obligations, credits, discounts, settlement, DNG, and audit.

If current code uses legacy links such as `finance_charge_id`, `source_type/source_id`, or direct `App\Models\FinanceCharge` access, treat that as migration context only, not target architecture.
