# Exec Plan

## Goal

Track Task 6 from the Milestone 1 plan as its own story: migrate Finance Office
sidebar IA into five work groups.

## Scope

In scope:

- Update `resources/js/constants/menu-sidebar.ts`.
- Import and use `financeRoutes`.
- Preserve item-level permissions.
- Remove duplicate DNG Due Reminders entry.
- Keep Discounts & Funding separate.

Out of scope:

- Backend routes or permission definitions.
- Topbar command/search mounting.
- Student 360 sidebar entry.

## Risk Classification

Risk flags:

- Existing behavior: navigation changes.
- Cross-platform/browser shell: sidebar behavior.
- Weak proof: visual navigation needs manual smoke.

Hard gates:

- Do not strand existing Finance pages.
- Do not weaken menu item permission gates.
- Do not convert non-Finance routes into `financeRoutes`.

## Work Phases

1. Update sidebar imports.
2. Replace Finance Office group.
3. Preserve Discounts & Funding.
4. Lint and build.
5. Record menu migration evidence.

## Stop Conditions

Pause if:

- A current sidebar item has no route helper and no explicit keep/remove
  decision.
- A route helper fails to resolve in production build.
