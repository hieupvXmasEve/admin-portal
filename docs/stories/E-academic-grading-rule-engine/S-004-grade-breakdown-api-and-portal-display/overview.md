# Overview

## Current Behavior

Student and lecturer APIs mainly expose percentage and letter grade fields.
After S-001, custom schemes store richer calculation details in
`academic_records.grade_breakdown`, but portals do not have a safe, stable
display contract for those details.

## Target Behavior

Backend APIs expose a sanitized grade display object and safe breakdown summary.
Student and lecturer portals update their shared types and grade views to show
custom-scheme results without deriving AU percentage letter grades locally.

## Affected Users

- Students viewing course grades.
- Lecturers reviewing gradebook and course assessment results.
- Academic staff comparing portal output to admin records.

## Affected Product Docs

- `docs/api/student/GRADES_API.md`
- `docs/api/student/course.md`
- `docs/api/lecturer/grade-management-api.md`
- `docs/api/lecturer/assessments.md`
- `docs/features/academic/grading-rule-engine.md`

## Portal Impact

Both. Run `./scripts/portal-status.sh` before touching `FE/student-nuxt` or
`FE/lecturer-nuxt`.

## Non-Goals

- No changes to lecturer grade entry workflow.
- No grade recalculation.
- No exposure of internal rule formulas that are not needed for display.
