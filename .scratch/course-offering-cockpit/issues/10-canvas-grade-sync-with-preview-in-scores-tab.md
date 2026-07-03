# Canvas grade sync with preview in the cockpit Scores tab

Status: done

## Parent

`.scratch/course-offering-cockpit/PRD.md` (Course Offering Cockpit)

## Origin

Grilling session 2026-07-03. Decisions recorded in `docs/adr/0014-post-completion-grade-changes-flow-only-through-recalculate.md`; glossary terms **Canvas grade sync** and **Sync preview** added to `CONTEXT.md`.

## What to build

A "Sync from Canvas" action in the cockpit Scores tab (`/course-offerings/{id}?tab=scores`) for offerings that are **not completed** and have a `mapped` Canvas course mapping.

Flow: select students → preview → apply.

1. **Selection — student-level only.** Checkbox per row in the scores grid plus select-all. Selection unit is the student; preview and apply cover *all* Canvas-mapped assessment component details of the selected students (no per-cell selection).
2. **Preview (dry-run).** Backend fetches Canvas submissions for the selected students and returns a diff **without writing anything**:
   - Changed cells only: student, component, detail, old score → new score (points + percentage). Cells whose current `score_status` is `disputed` carry a warning badge but are still included.
   - Canvas course total per student: `AcademicRecord.final_percentage` old → new (omitted when the syllabus uses a custom grading engine — the existing guard in `CanvasGradeSyncService` stays authoritative).
   - Unmatched students (SIS ID mismatch, no Canvas enrollment) listed in their own section with the reason — never silently dropped.
   - Summary counts: N changes across M students, K unchanged, U unmatched.
3. **Apply.** Backend **re-fetches from Canvas** (the preview payload is never echoed back — Canvas at apply time is the source of truth) and writes for the selected students only:
   - `AssessmentComponentDetailScore` create/update — overwrite unconditionally, matching existing bulk-sync behavior (the preview is the safety net; no skip logic for `final`/`disputed`).
   - Canvas course total → `AcademicRecord.final_percentage` + letter grade, respecting the custom-grading-engine guard.
   - Returns the same summary shape (synced/created/updated/unmatched) for a result toast; frontend reloads the deferred `scoresData` group.
4. **Guards.**
   - Offering completed → action hidden and endpoint rejects (ADR 0014: post-completion grades flow only through Recalculate — issue 11).
   - No `mapped` Canvas mapping → action hidden.
   - New permission `sync_course_grades` in the course-offering permission group gates both endpoints (distinct from `sync_canvas_courses`, which is Canvas-integration admin).

## Implementation notes

- Reuse `CanvasGradeSyncService` (`app/Services/Canvas/CanvasGradeSyncService.php`): add a student-ID filter and a dry-run mode that returns the diff instead of writing. The bulk all-campus sync path must keep working unchanged.
- Fix in passing: the existing-score lookup (`CanvasGradeSyncService.php:368-370`) filters only `assessment_component_detail_id` + `student_id`, missing `course_offering_id` — align it with the table's unique key.
- Follow the cockpit action pattern from the Recalculate endpoint (`routes/web/course-offerings.php`, `RecalculateCourseOfferingController`): two POST endpoints (e.g. `.../sync-grades/preview` and `.../sync-grades`), thin controllers, FormRequest validating `student_ids[]` against the offering roster, `ApiResponse` envelope.
- Preview UI: dialog/drawer inside `ScoresTab.vue`, diff table (changed rows only), unmatched section, summary header, apply button labeled with counts ("Sync 12 changes for 5 students"). This diff-table component will be reused by issue 11 — keep it extractable.
- The `canvas_unsynced` readiness blocker is driven by *assignment-structure* sync (`CourseOffering.is_canvas_synced`, set by `CanvasAssignmentSyncService`), not grade sync — this feature does not touch it.

## Acceptance criteria

- [x] Sync button appears only for non-completed offerings with a `mapped` Canvas mapping and the `sync_course_grades` permission; endpoint enforces all three server-side.
- [x] Preview writes nothing (no score rows, no `AcademicRecord` change, no events) and reports changed cells, total changes, disputed badges, unmatched students with reasons, and summary counts.
- [x] Apply re-fetches Canvas, writes only the selected students' scores + totals, respects the custom-grading-engine guard, and returns a per-student summary.
- [x] Selecting a subset leaves unselected students' scores untouched.
- [x] Completed offering → endpoint rejects with a clear error.
- [x] Existing bulk sync (`syncCourseGrades` without filter) behavior unchanged — regression-covered.
- [x] Feature tests cover: preview diff correctness, apply write path, roster-membership validation of `student_ids`, permission 403, completed-offering rejection.

## Blocked by

None.
