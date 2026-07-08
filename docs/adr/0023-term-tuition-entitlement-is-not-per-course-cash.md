# Term tuition entitlement is not per-course cash

**Status:** Accepted
**Date:** 2026-07-06
**Owner:** Academic + Finance

## Context

Swinx tuition is commonly charged by term. Paying the term-level tuition gives a
student the right to study the curriculum courses covered by that term. That
right is not a fixed money amount attached to each course, because later program
structures may contain fewer or more courses and term tuition may change.

## Decision

For defer/bảo lưu settlement, Swinx must treat term-level tuition as a study
entitlement over the covered term scope, not as per-course cash. Course
registrations and defer case items identify what study scope is covered; they do
not define a formula for splitting the paid money across courses.

When a student returns from a paid/preserved term entitlement, the student may
study the covered courses as classes become available. If a covered course is
not offered until a later term, that later class remains covered by the same
entitlement and should not create a new tuition charge for that course. A new
charge is required only for a separate obligation, such as retaking a course the
student failed.

If the curriculum changes, Academic equivalence mapping decides whether a new
or replacement course satisfies the covered scope. Finance must not prorate the
entitlement by old/new course count; see ADR-0024.

## Boundaries

- This decision applies to term-level tuition. A direct course-level fee can
  still be handled as a course-level obligation when the source charge maps
  directly to that course.
- Course-scope defer against semester-level tuition remains manual Finance
  review unless a later decision defines a safe entitlement mapping.
- Preserved cash surplus is not created merely because some covered courses are
  taught later. Surplus exists only after the relevant entitlement/obligation is
  fully settled and real paid cash remains unapplied.
- Program or curriculum changes that make the covered term scope ambiguous must
  stop for Academic + Finance review.

## Consequences

- Swinx must not allocate a fixed amount of preserved cash to each course in a
  term-level tuition defer.
- Delayed offerings under the same covered term scope do not create extra
  tuition charges.
- Retake-after-fail charges remain separate from defer return-study settlement.
- Curriculum changes use Academic equivalence mapping, not Finance proration.

## Implementation Boundary

This ADR records business semantics for a Finance concern (defer/bảo lưu, settlement, tuition entitlement, or transfer). It does not authorize Academic and Finance to share Eloquent models, foreign keys, or polymorphic source pointers.

Any implementation of this decision must enter Finance through the Finance Intake Contract defined in ADR-0026, using the neutral Obligation Source Reference (`source_system`, `source_kind`, `source_ref`). Academic may send pricing facts and may keep local projections for display, but Finance owns pricing, obligations, credits, discounts, settlement, DNG, and audit.

If current code uses legacy links such as `finance_charge_id`, `source_type/source_id`, or direct `App\Models\FinanceCharge` access, treat that as migration context only, not target architecture.
