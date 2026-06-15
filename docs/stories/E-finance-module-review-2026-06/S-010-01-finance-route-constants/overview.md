# Overview

## Current Behavior

Finance navigation historically used many literal Finance URLs directly in
frontend menu/config code. The Milestone 1 plan needs a stable route-helper
surface before later UI tasks can safely link Student 360, search, semester
context, and the reorganized Finance Office sidebar.

## Target Behavior

Create the route-name foundation from Task 1 of
`docs/superpowers/plans/2026-06-15-finance-office-shell-and-360-foundation.md`:

- `FINANCE_ROUTE_NAMES` in `resources/js/constants/finance-routes.ts`.
- `financeRoutes` in `resources/js/utils/routes.ts`.
- Existing Finance pages use named routes through Ziggy helpers where this
  foundation is consumed.

This story is the dependency root for the later Finance shell stories. It does
not create backend routes by itself.

## Affected Users

- Finance staff and admins indirectly, through safer navigation.
- Developers and agents implementing Finance Office surfaces.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-shell-and-360-foundation.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/`

## Portal Impact

Portal impact: none.

This story is admin/staff web UI route-helper work only.

## Non-Goals

- Do not register new Laravel routes in this story.
- Do not mount UI affordances.
- Do not change Finance permissions, money logic, student APIs, or lecturer APIs.
