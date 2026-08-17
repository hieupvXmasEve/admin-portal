---
phase: 1
title: "Action log gap in placement initialize"
status: completed
priority: P1
effort: "3h"
dependencies: []
---

# Phase 1: Action log gap in placement initialize

## Overview

`InitializeStudentPlacementAction` classifies a student (EGC stage + level, or
`intake_course` when IELTS meets threshold) and writes `program_enrollments` +
`academic_progression_events`, but not `student_action_logs`. The CLI
`students:create-egc-action-logs` exists only to backfill that gap. Close the
gap at the source.

## Requirements

- Functional: every successful placement init writes exactly one
  `student_action_logs` row inside the same DB transaction.
- Row shape (mirror `CreateEgcStudentActionLogsCommand` conventions):
  - EGC path: `action_type=STUDENT_ENROLLMENT_NE`, `previous_status='pending'`,
    `new_status='intake_pre_uni_gc'`, reason `'Student completes NE enrollment.'`
  - IELTS-qualified path: `action_type=STUDENT_ENROLLMENT_NE`,
    `previous_status='pending'`, `new_status='intake_course'`, reason
    `'Student completes enrollment and directly enters course stage.'`
  - Common: `changed_by_user_id` = `$userId`, `from_semester_id` =
    `$data['semester_id']`, `missing_documents=false`, `notes` =
    `$data['notes'] ?? null`.
- Non-functional: no behavior change for already-placed students — existing
  `InvalidProgressionState` guard stays the dedupe mechanism.

## Architecture

Single insert added inside the existing `DB::transaction` closure in
`InitializeStudentPlacementAction::run`, after the `$enrollment->update(...)`
call, using the already-resolved `$stage` to pick new_status/reason. No new
classes, no contract changes.

## Related Code Files

- Modify: `app/Modules/Academic/Progression/Actions/Placement/InitializeStudentPlacementAction.php`
- Modify (tests): `tests/Feature/Academic/Progression/PlacementOwnershipTest.php`
  (or the existing placement feature test file covering initialize — add log
  assertions there; create a sibling test file only if none covers initialize)

## Implementation Steps

1. In `InitializeStudentPlacementAction`, after the enrollment update, create
   `StudentActionLog` with the row shape above (`$stage === 'intake_pre_uni_gc'`
   → EGC variant, else course variant).
2. Extend placement initialize feature tests: assert one log row with correct
   previous/new status, action_type, from_semester_id for both paths (placement
   test → EGC; IELTS ≥ threshold → course).
3. Assert re-init throws and log count stays 1.
4. Run: `./scripts/dev.sh artisan test tests/Feature/Academic/Progression/ --compact`
   (run file-scoped; whole Academic dir has known pre-existing failure).

## Success Criteria

- [x] EGC init → 1 log `pending → intake_pre_uni_gc`
- [x] IELTS-qualified init → 1 log `pending → intake_course`
- [x] Second init throws, no extra log
- [x] Placement tests green (PlacementOwnershipTest 5 passed; Progression dir 69 passed)

## Risk Assessment

Low. Additive insert in existing transaction. Convention risk
(`previous_status='pending'` vs live `active`) settled with user — matches
backfilled data.
