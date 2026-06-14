# Overview

## Current Behavior

Swinx currently calculates manually entered grades as weighted percentages. The
runtime aggregates assessment detail `percentage_score` values into
`academic_records.final_percentage`, then finalizes pass/fail using
`syllabus_templates.min_grade_threshold` and fixed percentage-to-letter/GPA
helpers.

The Metropolia grading reference in
`docs/features/academic/Grading_Schemes_Metropolia.md` requires course-specific
rules that cannot be represented by the existing single weighted-percentage
model.

## Target Behavior

Swinx supports a small, syllabus-scoped grading rule engine. Existing schools
continue using the default weighted-percentage behavior unless a syllabus has a
custom `grading_scheme`.

The first custom engine is `metropolia_v1`, covering Metropolia-style gates,
component conversions, 0-5 grades, pass/fail schemes, and final rounding after
component conversion.

This story is the first implementation slice under the umbrella story set in
`docs/stories/E-academic-grading-rule-engine/README.md`.

## Affected Users

- Academic admins who configure syllabus templates.
- Lecturers who enter assessment scores.
- Students who view course outcomes through the student API or portal.
- Academic staff who finalize courses and semester GPA.

## Affected Product Docs

- `docs/features/academic/Grading_Schemes_Metropolia.md`
- `docs/features/academic/grading-rule-engine.md`
- `docs/portal-repos.md`

## Non-Goals

- No shared-database tenant isolation. Schools share the repo but run separate
  databases, so scheme selection is per deployment database, syllabus template,
  and optional campus.
- No visual rule-builder UI in the first slice.
- No historical recalculation job in the first slice.
- No change to the default weighted-percentage behavior when
  `syllabus_templates.grading_scheme` is null.
