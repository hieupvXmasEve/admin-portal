---
phase: 3
title: "Shared contract & watchlist query"
status: done
priority: P1
effort: "0.5d"
dependencies: [1]
---

# Phase 3: Shared contract & watchlist query

## Overview

Data layer for the watchlist page. Finance exposes "students carrying an
adjustment" through a new Shared contract; Academic Progression composes it
with verdict, GPA, attendance, and current course registrations.

## Requirements

- Functional: one query returns per-student rows: adjustment terms
  (original/adjusted/effective), target semester, proposal state
  (none|pending|approved|rejected + restored_amount of latest approved), verdict
  (clean|still_failing|not_finalized) **evaluated progressively** — latest
  semester with finalized grades ≥ target semester, plus `evaluated_semester`
  label field (validation decision) — semester + cumulative GPA, current
  registrations (unit code/name), attendance % + absent count per course.
  Include ALL carrying rows: no proposal, pending, rejected, approved-partial
  (validation decision).
- Non-functional: campus-scoped; no Finance→Academic import; pagination-safe
  (avoid loading whole campus into memory unbounded — follow defer-return
  watchlist's approach).

## Architecture

```
Academic Progression: GetScholarshipRestorationWatchlistQuery
  ├─ Shared\Contracts\ScholarshipRestorationWatchlistReader   ← implemented in Finance
  │    └─ wraps ListPendingRestorationReviewsQuery + proposal state + effective amount
  ├─ ScholarshipRestorationVerdictQuery        (Progression, existing)
  ├─ GetStudentAcademicAttendanceSummaryQuery  (Delivery, existing)
  ├─ ListStudentCourseRegistrationsQuery       (Delivery, existing)
  └─ GpaCalculation (model, scopeBySemester)
```

Contract returns plain DTO array (Shared\Contracts convention — copy shape of
`Shared/Contracts/Finance/ScholarshipAdjustmentPreviewReader`). Verdict is
progressive: for each row pick the latest semester ≥ adjustment target semester
whose grades are finalized for that student, call
`ScholarshipRestorationVerdictQuery::verdict($studentId, $thatSemesterId)`
(semester is already a parameter), and return the verdict + `evaluated_semester`.
If no semester ≥ target is finalized yet → `not_finalized` with target semester
as label.

## Related Code Files

- Create: `app/Shared/Contracts/Finance/ScholarshipRestorationWatchlistReader.php`
  (interface + DTO; method e.g. `listCarried(int $campusId): array`)
- Create: `app/Modules/Finance/Queries/ListCarriedScholarshipAdjustmentsQuery.php`
  (impl: extends/wraps `ListPendingRestorationReviewsQuery` logic + includes rows
  with pending/rejected proposals AND approved-partial ones still carrying; eager-loads proposals)
- Modify: `app/Modules/Finance/Providers/FinanceServiceProvider.php` (~:88) —
  bind contract to impl beside the existing `ScholarshipAdjustmentPreviewReader` binding
- Create: `app/Modules/Academic/Progression/Queries/GetScholarshipRestorationWatchlistQuery.php`
  (composition + filters: semester, verdict, proposal state, search)
- Tests: `tests/Feature/Finance/` for the Finance query;
  `tests/Feature/Academic/Progression/` (or repo's existing Progression test dir —
  match `GetDeferReturnWatchlistQuery` test location) for composition query
- NOTE: run Academic test files explicitly (whole-dir run aborts on known
  class_sessions CHECK constraint flake)

## Implementation Steps

1. Define DTO fields from Phase 4 UI needs (list above) — keep flat, BE-formatted
   dates (repo convention from defer-return watchlist).
2. Finance query + contract impl + binding.
3. Progression composition query; batch student ids to the Delivery queries
   (avoid per-row N+1 — check whether attendance summary query accepts one
   student at a time; if so, chunk + collect).
4. Filters: target semester, verdict, proposal state, name/code search.
5. Unit/feature tests: campus scoping, proposal-state bucketing, progressive
   verdict picks latest finalized semester ≥ target (and falls back to
   not_finalized when none), a partial-restored row shows effective amount.

## Todo

- [x] Contract + DTO
- [x] Finance impl + binding
- [x] Progression composition query with filters
- [x] Tests green

## Success Criteria

- [x] Query returns complete row set for a seeded campus scenario
- [x] Approved-partial rows present with effective amount; approved-full rows absent
- [x] No Finance→Academic import (existing arch tests stay green)

## Risk Assessment

- Attendance query is per-student → potential N+1 on big campuses; mitigate by
  chunking; page size follows defer-return pattern. Escalate to a bulk query
  only if measured slow (YAGNI).
