---
title: "student placement worklist"
description: "Dedicated Academic-department page listing approved-but-unclassified students, per-row classify into EGC (+ level) or intake_course, with student_action_logs written automatically by placement initialize."
status: completed
priority: P2
effort: "1.5d"
tags: [academic, progression, placement, frontend]
created: 2026-08-17
blockedBy: []
blocks: []
---

# student placement worklist

## Overview

Admissions approves an application → student account + `students` row +
primary `program_enrollments` row (`study_stage = NULL`). A **different
department (Academic)** then classifies the student: EGC
(`intake_pre_uni_gc` + English level) or straight major (`intake_course`,
IELTS ≥ threshold). Today that classification exists only as a per-student
form buried in the student lifecycle tab (`EgcControls.vue` →
`students.placement.initialize`), it writes **no `student_action_logs`**
(the CLI `students:create-egc-action-logs` backfills them by hand), and
there is no way to see which students still need classification.

This plan (a) makes `InitializeStudentPlacementAction` write the action log
itself, and (b) adds a dedicated campus-scoped worklist page where Academic
staff see unclassified students and classify them in place.

Brainstorm evidence (this session): approve flow =
`ApproveApplicationAction` → `MaterializeProgramEnrollmentAction` leaves
`study_stage NULL`; canonical lifecycle source = primary
`program_enrollments` row (per completed plan
`260817-0017-retire-legacy-studentsstatus-reads`).

## Key Decisions (binding)

| Area | Decision |
|---|---|
| Log convention | Match CLI backfill: `previous_status='pending'`, `new_status='intake_pre_uni_gc'` or `'intake_course'`, `action_type=STUDENT_ENROLLMENT_NE`, `from_semester_id` = request `semester_id` |
| Write endpoint | Reuse existing `POST students/{student}/placement/initialize` — no new write route |
| Unclassified definition | Primary `program_enrollments` row with `study_stage IS NULL` and `enrollment_status NOT IN (withdrawn, graduated)` |
| Campus scope | `session('current_campus_id')` pattern (same as StudentRegistry StudentController) |
| Permissions | Whole page + classify: `change_student_status` (validated — dept-specific operational page, not gated by broad `view_student`) |
| Form in worklist | Small self-contained dialog posting to existing route/request; do NOT extract from 26K `EgcControls.vue` (follow-up if drift hurts) |
| Module owner | Academic/Progression — controller, query, routes all live there |

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Placement initialize writes `student_action_logs` in same transaction (kills manual CLI runs for new data) | P1 |
| 2 | Dedicated worklist page: campus-scoped list of unclassified students | P1 |
| 3 | Classify from the worklist via dialog (EGC + level / IELTS → course) | P2 |
| 4 | Sidebar entry + sidebar tests + docs-site kept green | P2 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Action log gap in placement initialize](./phase-01-action-log-gap-in-placement-initialize.md) | Completed |
| 2 | [Worklist backend query controller routes](./phase-02-worklist-backend-query-controller-routes.md) | Completed |
| 3 | [Worklist UI page and sidebar entry](./phase-03-worklist-ui-page-and-sidebar-entry.md) | Completed |

## Success Criteria

- [x] Init placement (EGC path) creates exactly one `student_action_logs` row `pending → intake_pre_uni_gc`; IELTS-qualified path creates `pending → intake_course`; re-init attempt cannot duplicate (init already throws `InvalidProgressionState` on second run)
- [x] Worklist shows only current-campus students whose primary enrollment has `study_stage IS NULL`; classified students disappear from the list
- [x] Classification from worklist produces enrollment update + action log + progression event identical to lifecycle-tab flow (same action, same request)
- [x] `SidebarMenuStructureTest` + other menu-sidebar consumers green; docs-site updated (Placement Worklist section added to student-services vi/en/ko/zh; freshness markers refreshed on all 68 anchored pages; gate + build green)
- [x] Feature tests green: `tests/Feature/Academic/Progression/PlacementWorklistTest.php` + log assertions added to placement tests

## Risks

- **Sidebar plan collision:** pending plan `260816-2125-sidebar-menu-ia-restructure`
  rewrites `menu-sidebar.ts`. Whichever lands second rebases the menu entry;
  entry placement follows the new IA if it lands first. Coordination note, not a blocker.
- **`previous_status='pending'` is a convention, not the live status** (fresh
  students are `status='active'`): deliberately matches existing backfilled
  rows so reporting stays uniform. Confirmed with user in brainstorm.
- **Duplicate-log guard:** relies on `InitializeStudentPlacementAction`'s
  existing already-placed guard rather than a log `exists` check — acceptable
  because init is the only writer for this transition.

## Validation Log

### Session 1 — 2026-08-17

**Verification Results**
- Claims checked: 14 (Standard tier: Fact Checker + Contract Verifier)
- Verified: 14 | Failed: 0 | Unverified: 0
- Evidence highlights: `StudentActionLog` fillable covers
  `from_semester_id`/`previous_status`/`new_status` (app/Models/StudentActionLog.php:21-45);
  dialog options source pinned to `LifecycleFormOptions::placementOptions()`
  (StudentAcademicSummaryController.php:276, EgcControls.vue:51-61);
  `PlacementOwnershipTest` already exercises `InitializeStudentPlacementAction`.

**Decisions**
1. Worklist filter: show all `study_stage IS NULL` except
   withdrawn/graduated (deferred/pending unclassified stay visible).
2. Page permission: `can:change_student_status` for the whole page
   (dept-specific operational surface), not `view_student`.
3. V1 filters: search (code/name) + program dropdown.

### Whole-Plan Consistency Sweep
- Propagated decisions 2-3 into phase-02 (requirements, route middleware,
  steps, tests, success criteria) and phase-03 (program filter in UI).
- Grep across plan dir: no stale `view_student` gating, no contradictory
  filter definitions remain. Zero unresolved contradictions.

## Open Questions

None — previous_status convention, department split, filters, and permission
gate confirmed by user.

<!-- slug: student-placement-worklist -->
