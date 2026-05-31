# Bulk Link Students to Decision

## Current Behavior

Staff can create and edit records in the Student Decisions registry.
Student action logs can be linked to a decision one action at a time through the
student action form, and the decision detail page shows the action logs already
linked to that decision.

## Target Behavior

From a decision detail page, staff can choose the matching student action type,
paste multiple student codes, preview the matched students and linkable action
logs of that type, then link all valid matched action logs to the decision in
one operation.

## Affected Users

- Academic staff managing student action decisions.

## Affected Product Docs

- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`

## Portal Impact

none

## Non-Goals

- No student or lecturer portal API changes.
- No database schema change.
- No automatic creation of new student action logs.
- No unlink or replace-all workflow.
