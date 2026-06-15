# Overview

## Current Behavior

Each Milestone 2 implementation story has focused validation, but the full
Student 360 surface combines read models, JSON previews, write adapters, DNG
cancel/void behavior, frontend drawers, permissions, and focus deep links. The
combined slice needs one final evidence pass before it is considered done.

## Target Behavior

Track Task 7 from
`docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md` as its
own story: run the full M2 integration validation, smoke the permission matrix,
record finance invariant evidence, and update the umbrella validation packet.

## Affected Users

- Finance staff using the completed Student 360 surface.
- Finance leads approving write-adjacent UX.
- Reviewers relying on Harness evidence before merge.

## Affected Product Docs

- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/validation.md`
- `docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md`
- `docs/features/finance/finance-office-ux-redesign-design.md`

## Portal Impact

Portal impact: none.

This story proves admin/staff web behavior only.

## Non-Goals

- Do not implement new product behavior in this story.
- Do not weaken validation if a command fails.
- Do not mark M2 done without invariant evidence for write paths.
- Do not update student or lecturer portal code.
