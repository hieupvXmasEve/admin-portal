# Lecturer Gradebook Workspace

Status: done
Portal impact: lecturer

## Problem

Lecturer grading is split across top-level `Assessments` and `Grades` tabs. In practice, `Assessments` is the path to enter grades while `Grades` is a read-only matrix/report. This naming and structure makes lecturers look in the wrong place when they want to grade.

## Decision

Replace the two top-level tabs with one `Gradebook` workspace, following common LMS patterns:

- `Gradebook > To Grade`: compact Gradable Items list for the work queue.
- `Gradebook > Matrix`: spreadsheet-style class matrix with inline point editing.
- Focused grading remains available for one assessment detail.

## Scope

- Lecturer-only backend API and portal UI.
- New lecturer Gradebook API contract.
- Inline Matrix editing with dirty state and explicit `Save changes`.
- Points-based entry using each detail's `max_points`.
- All-or-nothing save, no score clearing in this phase.
- Auto-refresh Gradebook after save.
- Route focused grading through `/course/{courseId}/gradebook/items/{detailId}`.

## Out of Scope

- Admin/staff Course Offering Scores UI.
- Export.
- Student notifications.
- Final grade override.
- Mobile-first matrix editing beyond non-broken horizontal scroll and focused-view fallback.
- Backward compatibility for old `?tab=assessments`, `?tab=grades`, or old assessment detail routes.

## Success Criteria

- Lecturer sees one top-level `Gradebook` tab.
- `To Grade` opens by default and shows a compact item list.
- `Matrix` allows editing assessment detail score cells, then saving all changes.
- Save updates raw scores and final/pass-fail display after refresh.
- Focused grading route still supports detailed grade entry.
- Backend tests cover the new Gradebook read/save contract.
- Lecturer portal typecheck/lint/build are attempted and results recorded.
