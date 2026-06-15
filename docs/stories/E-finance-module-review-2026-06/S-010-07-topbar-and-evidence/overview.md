# Overview

## Current Behavior

The command palette and semester switcher are useful only after they are mounted
into the app shell. The milestone also needs one validation/evidence step that
proves the independent pieces work together.

## Target Behavior

Track Task 7 from the Milestone 1 plan as its own story:

- Mount `FinanceCommandPalette` and `SemesterSwitcher` in
  `AppSidebarHeader.vue`.
- Gate shell affordances by Finance permissions.
- Run/record the full Milestone 1 validation set.
- Record Harness evidence and manual browser smoke status.

## Affected Users

- Finance staff using the always-visible topbar.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-shell-and-360-foundation.md`
- S-010 child story packets.

## Portal Impact

Portal impact: none.

This story changes admin/staff topbar rendering only.

## Non-Goals

- Do not implement search endpoint or semester context here; those are separate
  child stories.
- Do not add full browser automation.
- Do not change money state.
