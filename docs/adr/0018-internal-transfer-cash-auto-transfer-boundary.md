---
id: ADR-0018
title: "Internal transfer cash auto-transfer requires same program and unchanged rules"
status: accepted
date: 2026-07-06
owner: "Academic + Finance"
last_verified: 2026-07-25
scope: architecture-decision
---

# Internal transfer cash auto-transfer requires same program and unchanged rules

**Implementation priority:** Parked for a separate internal-transfer settlement
workflow. This ADR must not expand or block FIN-REV-020 defer settlement.

## Context

Internal academic transfer/chuyển trường nội bộ covers three cases:

- campus-only transfer: same program, different campus;
- program-only transfer: same campus, different program;
- combined transfer: different campus and different program.

All three can affect Finance because they may change the owning campus,
tuition plan, discount or scholarship rules, DNG collection responsibility,
and which obligation the student's paid cash should settle.

The product decision needed here is not whether transfer is allowed
academically. The decision is when Swinx may automatically carry paid cash from
the old context into the new context without Finance review.

## Decision

Swinx may auto-transfer available paid cash during an internal transfer only
when all of these are true:

- the transfer is campus-only;
- the program does not change;
- the tuition plan, fee schedule, discount, and scholarship rules do not change;
- the source cash is real paid cash or otherwise unapplied cash that can legally
  settle the new obligation;
- there is no live DNG/provider state that must be cancelled or resolved first;
- the old obligation and the new obligation can be matched without ambiguity.

If the transfer changes program, Swinx must route the case to Finance review
before money is moved or applied. This includes program-only transfer and
combined campus + program transfer.

If the transfer is campus-only but tuition, fee schedule, discount, or
scholarship rules change, Swinx must also route the case to Finance review.

## Boundaries

- Auto-transfer does not mean hiding the new obligation. The destination study
  context still has its own payable obligation; carried cash settles that
  obligation through normal allocation.
- Auto-transfer does not create synthetic debt, synthetic credit, or a parallel
  settlement ledger. Existing charges, invoice lines, payments, payment
  applications, and DNG records remain the money source of truth.
- Finance review must compare old obligation, new obligation, paid cash,
  discount/scholarship eligibility, DNG state, and any remaining surplus or
  shortfall before deciding how to settle the case.
- This ADR does not yet decide the full Finance review outcome set for program
  transfer. That should be defined in the internal transfer settlement workflow.

## Consequences

- Same-program campus transfer can stay operationally light when the money rules
  are truly identical.
- Program transfer is treated as high-risk because the student may be moving
  into a different tuition plan or discount regime.
- Combined campus + program transfer follows the stricter path and requires
  Finance review.
- DNG collection after transfer must not proceed until the applicable balance,
  old/new obligation mapping, and any provider blockers are resolved.
- This decision is recorded now so it is not lost, but implementation planning
  should stay separate from defer/bảo lưu. FIN-REV-020 remains the first
  workflow to handle.

## Implementation Boundary

This ADR records business semantics for a Finance concern (defer/bảo lưu, settlement, tuition entitlement, or transfer). It does not authorize Academic and Finance to share Eloquent models, foreign keys, or polymorphic source pointers.

Any implementation of this decision must enter Finance through the Finance Intake Contract defined in ADR-0026, using the neutral Obligation Source Reference (`source_system`, `source_kind`, `source_ref`). Academic may send pricing facts and may keep local projections for display, but Finance owns pricing, obligations, credits, discounts, settlement, DNG, and audit.

If current code uses legacy links such as `finance_charge_id`, `source_type/source_id`, or direct `App\Models\FinanceCharge` access, treat that as migration context only, not target architecture.
