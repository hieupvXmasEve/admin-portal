# Overview

## Current Behavior

Finance pages often keep their own semester filters. The Finance Office shell
needs one app-level semester context so future semester-bound surfaces can share
the same selected semester.

## Target Behavior

Track Task 5 from the Milestone 1 plan as its own story:

- Share a `semester` Inertia prop for Finance users.
- Add a session-backed semester context update route.
- Add a topbar semester switcher component.

Milestone 1 ships the plumbing only. Student 360 remains all-semester truth and
does not become semester-filtered in this story.

## Affected Users

- Finance staff using the app shell.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-shell-and-360-foundation.md`

## Portal Impact

Portal impact: none.

This story affects the admin/staff Inertia app shell only.

## Non-Goals

- Do not retrofit every Finance page to obey the selected semester.
- Do not filter Student 360 balances by semester.
- Do not store selected semester in a new table.
