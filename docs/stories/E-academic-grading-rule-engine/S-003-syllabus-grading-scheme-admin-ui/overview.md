# Overview

## Current Behavior

After S-001 and S-002, `grading_scheme` can be stored and applied through
backend fixtures or CLI, but academic admins cannot inspect, validate, or
preview schemes from the Swinx admin UI.

## Target Behavior

Syllabus template create/edit/show pages expose a guarded grading scheme panel.
Admins can choose default weighted grading or a configured scheme, paste/edit
JSON, validate it, and preview sample component scores before saving.

## Affected Users

- Academic admins who manage syllabus templates.
- Academic operations staff validating Metropolia mappings.

## Affected Product Docs

- `docs/features/academic/grading-rule-engine.md`
- `docs/features/academic/metropolia-component-mapping.md`

## Portal Impact

None. This story changes admin web pages and web-authenticated preview
endpoints only.

## Non-Goals

- No drag-and-drop visual rule builder.
- No portal display changes.
- No grade recalculation.
- No automatic scheme recommendation from syllabus title.
