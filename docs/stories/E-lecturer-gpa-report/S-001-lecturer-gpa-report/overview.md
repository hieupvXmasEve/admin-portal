# S-001 Lecturer GPA Report

## Current Behavior

Staff can view survey runs and per-class aggregate survey results under
`/forms/admin/results`. The aggregate page computes rating averages for one
course offering survey target, but there is no lecturer-level semester summary
that averages lecturer evaluation scores across all classes taught by the same
lecturer.

The lecturer directory at `/lectures` can filter lecturers by semester and
program/unit type, and defaults to the active semester when one is configured.

## Target Behavior

Staff can open `/lectures/lecturers-GPA` to review Lecturer GPA for the active
semester by default. The page summarizes one row per lecturer with:

- No.
- Lecturer name.
- Email account, using the local part before `@` such as `trangnk16`.
- Employee ID.
- Type.
- Courses taught in the selected semester.
- GPA, calculated as the average Lecturer Evaluation score across all course
  offering survey targets taught by that lecturer in the selected semester.

Staff can switch semester and export the same report to Excel. The existing
Survey Results aggregate page links to the Lecturer GPA page for the matching
semester.

## Affected Users

- Academic operations staff who can view lecturer records and aggregate survey
  results.
- Managers reviewing lecturer evaluation outcomes for a semester.

## Affected Product Docs

- `docs/product/lecturers.md`
- `docs/project-overview-pdr.md`
- `docs/TEST_MATRIX.md`

## Non-Goals

- No schema changes.
- No raw student identifiers or raw individual responses in the Lecturer GPA
  report or export.
- No lecturer mobile/API changes.
- No changes to the course survey submission flow.
