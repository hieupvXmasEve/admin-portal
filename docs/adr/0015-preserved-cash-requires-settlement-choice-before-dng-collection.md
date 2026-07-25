---
id: ADR-0015
title: "Preserved cash requires a settlement choice before DNG collection"
status: accepted
date: 2026-07-06
owner: "Academic + Finance"
last_verified: 2026-07-25
scope: architecture-decision
---

# Preserved cash requires a settlement choice before DNG collection

**Context.** A full-scope `PRESERVE` defer releases real paid cash from the
original deferred obligation and leaves that cash available on the student's
Finance account. Per ADR-0017, when the student later re-enrolls, the new
registration should create a normal payable charge and preserved cash should
settle that charge through normal allocation. Without an explicit settlement
choice, staff could generate or push a DNG request for that new charge while the
student's preserved cash is still unapplied, creating a practical
double-collection risk.

**Decision.** Before Swinx creates or pushes a DNG collection request for a
student's re-enrollment charge, the Finance workflow must surface applicable
student cash and record an explicit settlement choice:

- **Use balance**: apply legally allocatable preserved or otherwise unapplied
  cash to the selected obligation first. DNG may collect only the remaining
  balance. If the cash exceeds the selected obligation, apply only up to the
  obligation's outstanding amount and leave the surplus as `Còn dư`; see
  ADR-0022.
- **Do not use balance**: leave the applicable cash unapplied and continue with
  provider collection only as an explicit operator choice with an
  operator-readable free-text reason and audit trail.

If allocation is blocked or the cash cannot be matched safely, the workflow
must stop and surface a Finance review state instead of silently choosing either
path.

**Boundaries.**

- This does not make the future re-enrollment free. The new registration still
  creates a normal charge; preserved cash is then applied through the existing
  allocation ledger.
- The future re-enrollment registration is a new return-study registration. The
  original deferred registration remains historical/non-billable and must not be
  reactivated or reused for collection.
- This does not create a new defer settlement ledger. The source of truth
  remains charges, invoice lines, payments, payment applications, and DNG
  request records.
- This does not change DNG provider payload semantics. It changes Swinx's
  precondition for creating or pushing a DNG request.
- "Do not use balance" is a deliberate collection option, not the default. The
  staff member must see the available balance and confirm why it is not being
  applied.
- "Do not use balance" does not require a separate permission beyond the normal
  DNG collection permission, but it always requires a free-text reason.
- Manual review is required when allocation is ambiguous, but silent DNG push is
  never allowed when relevant unapplied cash exists.

**Consequences.**

- Batch DNG and any per-student DNG push path must present a balance-use option
  when a relevant unapplied balance exists. The default should be "use balance"
  when the cash can be allocated safely.
- Preview screens may show both the charge balance and the amount that would be
  collected after the selected balance-use option.
- Commit/audit data must store the balance-use choice and the free-text reason
  whenever staff chooses not to use balance; no extra permission gate or
  controlled reason list should be added for that override.
- Defer/re-enrollment tests should prove the sequence: preserve releases cash,
  re-enrollment creates a normal charge, the default path uses preserved cash,
  and the override path records a reason when staff chooses not to use balance.
- Tests should also prove that excess preserved cash is not consumed or sent to
  DNG after the selected obligation is fully settled.

## Implementation Boundary

This ADR records business semantics for a Finance concern (defer/bảo lưu, settlement, tuition entitlement, or transfer). It does not authorize Academic and Finance to share Eloquent models, foreign keys, or polymorphic source pointers.

Any implementation of this decision must enter Finance through the Finance Intake Contract defined in ADR-0026, using the neutral Obligation Source Reference (`source_system`, `source_kind`, `source_ref`). Academic may send pricing facts and may keep local projections for display, but Finance owns pricing, obligations, credits, discounts, settlement, DNG, and audit.

If current code uses legacy links such as `finance_charge_id`, `source_type/source_id`, or direct `App\Models\FinanceCharge` access, treat that as migration context only, not target architecture.
