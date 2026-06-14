# Overview

## Current Behavior

Course finalization can calculate current records, but there is no controlled
operator workflow for recalculating existing academic records after a syllabus
receives a new grading scheme.

## Target Behavior

Academic operators have a dry-run-first recalculation command that targets a
course offering, semester, or syllabus template. The command previews before
and after values, writes an audit snapshot, and requires explicit confirmation
before mutating records.

## Affected Users

- Academic operators running recalculation.
- Academic admins reviewing audit output.
- Students and lecturers whose displayed grades may change after approved
  recalculation.

## Affected Product Docs

- `docs/runbooks/academic-grading-recalculation.md`
- `docs/features/academic/grading-rule-engine.md`

## Portal Impact

None directly. Recalculated data may later be visible through APIs, but this
story does not change API shape or portal code.

## Non-Goals

- No automatic background recalculation after scheme save.
- No broad all-database recalculation without filters.
- No rollback command that mutates data without human review.
