---
id: ADR-0024
title: "Curriculum changes use academic equivalence, not money proration"
status: accepted
date: 2026-07-06
owner: "Academic + Finance"
last_verified: 2026-07-25
scope: architecture-decision
---

# Curriculum changes use academic equivalence, not money proration

## Context

After a defer, the curriculum or program structure may change: course names may
change, replacement courses may be introduced, or the later term may contain
fewer or more courses. If Finance tries to price the return by course count or a
fixed per-course amount, it will break the term-tuition model and may charge or
refund incorrectly.

## Decision

When a deferred study scope returns into a changed curriculum, Academic must map
the old covered scope to equivalent or replacement courses. If a new course is
an approved equivalent/replacement for the covered deferred scope, it remains
covered by the existing term tuition entitlement and does not create a new
tuition charge. If a course is outside the covered scope, or the student is
retaking it after failing, it is a separate obligation.

Finance must not calculate a tuition difference from the number of old courses,
new courses, credits, or a fixed per-course amount. If the equivalence mapping
is unclear, the case stops for Academic + Finance review.

## Boundaries

- This decision applies to defer/bảo lưu return-study settlement.
- Academic owns equivalence/replacement mapping; Finance owns whether a mapped
  outcome creates or does not create a money obligation.
- Program/campus transfer remains a separate internal-transfer settlement
  workflow unless it is only needed as context for the Academic equivalence
  decision.
- Direct course-level fees may still be charged when the source obligation is
  truly course-level and outside the covered term entitlement.

## Consequences

- A student is not charged just because the replacement course sits in a later
  term or the later curriculum has a different course count.
- Finance reports can distinguish covered return-study scope from new
  obligations such as failed-course retakes.
- Ambiguous curriculum mappings become review work, not automatic money
  mutation.

## Implementation Boundary

This ADR records business semantics for a Finance concern (defer/bảo lưu, settlement, tuition entitlement, or transfer). It does not authorize Academic and Finance to share Eloquent models, foreign keys, or polymorphic source pointers.

Any implementation of this decision must enter Finance through the Finance Intake Contract defined in ADR-0026, using the neutral Obligation Source Reference (`source_system`, `source_kind`, `source_ref`). Academic may send pricing facts and may keep local projections for display, but Finance owns pricing, obligations, credits, discounts, settlement, DNG, and audit.

If current code uses legacy links such as `finance_charge_id`, `source_type/source_id`, or direct `App\Models\FinanceCharge` access, treat that as migration context only, not target architecture.
