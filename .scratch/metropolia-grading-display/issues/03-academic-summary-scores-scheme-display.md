# 03 — Academic summary Scores page scheme display

Status: ready-for-human (implemented 2026-07-04; all acceptance criteria met, see StudentAcademicSummaryScoresSchemeDisplayTest)

## Parent

`.scratch/metropolia-grading-display/PRD.md`

## What to build

Apply the same stored-breakdown scheme display to the student academic
summary Scores page (`/students/{id}/academic-summary/scores`), so academic
operations staff can answer "why did this student fail?" from one screen.

End-to-end behavior:

- For each completed course graded by a custom scheme, the page shows the
  final scheme grade (0–5 or Pass/Fail), pass status, and the per-component
  breakdown (raw percentage, converted grade, requirement status) — all read
  through the existing grade display presenter from the stored grade
  breakdown.
- Requirement failures are visible per component, mirroring the cockpit
  Scores tab treatment (issue 02) — reuse its FE pattern/components where
  sensible.
- Courses graded by the default weighted path render unchanged.
- Courses without a finalized academic record show no scheme grades (same
  empty-state philosophy as issue 02).

No new calculation, storage, or presenter contract changes. Read-only.

## Acceptance criteria

- [x] Scores route props include presenter-shaped scheme fields for completed scheme-graded courses
- [x] Per-component requirement status visible; gate-fail case explainable from this page alone
- [x] Default-weighted courses render unchanged (regression assertion)
- [x] Unfinalized/no-record courses show no scheme grades
- [x] Feature tests at the HTTP/Inertia seam (prior art: existing student academic summary scores tests)

## Blocked by

None strictly — best done after issue 02 to reuse its FE display pattern.
