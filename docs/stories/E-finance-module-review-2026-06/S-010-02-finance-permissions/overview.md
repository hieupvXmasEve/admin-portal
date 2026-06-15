# Overview

## Current Behavior

The Finance Office shell needs a permission-gated Student 360/search surface and
a separate all-campus privilege. Without dedicated permissions, the new shell
would either overuse broad Finance permissions or expose cross-campus lookup
behavior too widely.

## Target Behavior

Track Task 2 from the Milestone 1 plan as its own story:

- Declare `view_finance_student_overview`.
- Declare `view_finance_all_campus`.
- Sync the permissions to the database.
- Map `view_finance_student_overview` to finance-capable staff roles.
- Keep `view_finance_all_campus` restricted to super admin unless explicitly
  changed by a later story.

## Affected Users

- Finance staff who can use Student 360/search.
- Super admins who can cross campus boundaries.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-shell-and-360-foundation.md`

## Portal Impact

Portal impact: none.

This story affects admin/staff web permissions only.

## Non-Goals

- Do not add Student 360 UI or search behavior here.
- Do not change student/lecturer portal auth.
- Do not broaden all-campus access beyond the approved admin role.
