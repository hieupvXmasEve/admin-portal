---
id: ADR-0016
title: "Partial defer is course scope, not amount scope"
status: accepted
date: 2026-07-06
owner: "Academic + Finance"
last_verified: 2026-07-25
scope: architecture-decision
---

# Partial defer is course scope, not amount scope

**Context.** Earlier Finance defer notes and code used `PARTIAL` as if it were a
money policy: preserve or consume part of an already-paid amount. That model is
too vague for operations because it asks Finance to split a semester-level
tuition charge by amount before the product has a course-fee allocation rule.
The actual business meaning is simpler: a student may defer only selected
courses instead of the whole semester.

**Decision.** A partial defer means **course-scope defer**: the defer applies to
selected `course_registrations` and is represented by `defer_case_items`.
Partial defer is not an amount-based policy. Swinx must not ask staff to enter a
"partial amount", must not auto-calculate a percentage such as 50%, and must not
consume or preserve money merely because a case is called partial.

For term-level tuition, selected course registrations identify academic scope
only. They are not a formula for assigning a fixed amount of tuition to each
course; see ADR-0023.

For new behavior, the primary distinction is scope:

- `FULL`: the whole semester enrollment is deferred.
- `COURSES`: only selected course registrations are deferred.

The money policy remains separate:

- `PRESERVE`: keep real paid cash available.
- `FORFEIT`: consume only real paid cash that the school is allowed to keep.

Legacy rows or code paths that use `fee_policy = PARTIAL` are historical or
needs-review data. They should be classified and migrated or corrected by a
separate implementation slice, not auto-settled as an amount rule.

**Consequences.**

- Course-scope defer can be auto-settled only when the source charge maps
  directly to the selected course registration.
- Semester-level tuition charges with only some courses deferred are always
  manual Finance review in the accepted current model. Swinx must not auto-split
  them by credits, course count, duration, or any other formula.
- Any future automatic split formula for semester-level tuition requires a
  separate ADR/story before implementation.
- Manual Finance review has three accepted outcomes:
  - **Keep fee unchanged**: leave the semester-level obligation collectible.
  - **Manual release/adjustment**: staff enters an explicit amount and free-text
    reason to release, adjust, or reallocate through approved Finance repair
    operations. The amount must not exceed real paid cash available on the
    student account. If no cash has been paid, there is nothing to release.
  - **Follow up later**: do not change money now; keep the case visible as a
    Finance follow-up with a note.
- Reducing or voiding unpaid obligations is not the same as releasing paid cash
  and must be handled as a separate reviewed Finance repair decision, not hidden
  inside manual release.
- UI and requests should avoid presenting "partial amount" as a business input.
  Staff should choose selected courses, then choose the allowed money policy for
  those courses where Finance can map the obligation safely.
- Existing 50% / preserve-amount calculations for `PARTIAL` are legacy behavior
  to remove or quarantine behind review.

## Implementation Boundary

This ADR records business semantics for a Finance concern (defer/bảo lưu, settlement, tuition entitlement, or transfer). It does not authorize Academic and Finance to share Eloquent models, foreign keys, or polymorphic source pointers.

Any implementation of this decision must enter Finance through the Finance Intake Contract defined in ADR-0026, using the neutral Obligation Source Reference (`source_system`, `source_kind`, `source_ref`). Academic may send pricing facts and may keep local projections for display, but Finance owns pricing, obligations, credits, discounts, settlement, DNG, and audit.

If current code uses legacy links such as `finance_charge_id`, `source_type/source_id`, or direct `App\Models\FinanceCharge` access, treat that as migration context only, not target architecture.
