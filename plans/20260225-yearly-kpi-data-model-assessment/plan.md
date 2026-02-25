---
title: "Yearly KPI Data Model Readiness"
description: "Assess schema readiness for yearly intake/status KPI table and define minimal gap-closing plan."
status: pending
priority: P2
effort: 8h
branch: dev
tags: [planning, data-model, reporting, academic]
created: 2026-02-25
---

## Objective

Confirm if current data model can reliably produce yearly columns:
`Year intake`, `Intake Pre.Uni (GC)`, `INTAKE COURSE`, `DEFER`, `DO`, `CHANGE CAMPUS`, `GRADUATED`, `BB2`, `PENDING`, `DO-Transfer`, `Pending RATE`, `DF RATE`, `DO RATE`, `GRADUATED RATE`, `NE`.

## Scope and boundaries

- In scope: `students`, `student_action_logs`, `semesters`, `enrollments`, `course_registrations`, graduation-related entities for BB2 mapping.
- Out of scope: UI redesign, dashboard visual changes, finance KPIs.

## Findings snapshot

- Core entities and semester keys exist.
- Mixed grains (snapshot/event/semester) create ambiguity.
- Missing formal mapping for `BB2`, `NE`, and rate denominators.
- Graduation year placement lacks explicit `graduated_at` or equivalent event type.

## Phases

### Phase 1: Semantic contract (2h)

- Define each KPI as one canonical expression:
  - `source`, `predicate`, `time_key`, `count_unit`, `denominator`.
- Decision table:
  - snapshot metric vs event metric.
  - cohort-year vs calendar-year.
- Deliverable: `docs/reporting/yearly-kpi-metric-contract.md`.

### Phase 2: Data model gap closure (3h)

- Add minimal schema/event support:
  - Option A: `students.graduated_at`.
  - Option B: new graduation action/event in `student_action_logs`.
- Decide and encode `BB2` as:
  - explicit status enum, or
  - deterministic derived state from `graduation_applications`.
- Define `NE` derivation rule via enrollment/registration absence.

### Phase 3: Query contract and validation (3h)

- Build one canonical SQL view/query per year grain (cohort or calendar) in reporting module.
- Add reconciliation checks:
  - no double-count per student/metric/year unless explicitly allowed.
  - sum of rate numerators <= denominator.
- Add test fixtures for edge cases:
  - transfer + dropout same year,
  - defer then resume,
  - admitted pending without enrollment.

## Risks

- Dual-source status (`students.status` vs `student_action_logs`) drift.
- Historical backfill quality for graduation timing.
- Legacy intake fields (`intake_course` vs `intake_major`) inconsistency.

## Acceptance criteria

- Each requested column has one unambiguous mapping.
- Rate formulas produce reproducible output under tests.
- Yearly table returns deterministic results for same snapshot date.

## Dependencies

- Business owner sign-off on `BB2`, `NE`, denominator semantics.
- Data governance sign-off on cohort-year vs calendar-year.

## Report reference

- `plans/reports/2026-02-25-yearly-kpi-data-model-assessment.md`

## Unresolved questions

- Final business definition for `BB2` and `NE`.
- Preferred year grain for executive reporting.
- Whether `GRADUATED` is cohort-outcome or event-year measure.
