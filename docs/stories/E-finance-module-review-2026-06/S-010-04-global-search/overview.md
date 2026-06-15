# Overview

## Current Behavior

Finance staff can search inside specific pages, but Milestone 1 needs a global
Finance entry point that jumps directly to Student 360 from student code/name,
invoice number, or DNG identifiers.

## Target Behavior

Track Task 4 from the Milestone 1 plan as its own story:

- Add `finance.search` JSON endpoint.
- Reuse the existing Finance audit search resolver.
- Map results to the owning Student 360 URL.
- Add the command palette component that consumes the endpoint and navigates on
  selection.

## Affected Users

- Finance staff using the topbar command search.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-shell-and-360-foundation.md`

## Portal Impact

Portal impact: none.

This story exposes an admin/staff web JSON endpoint, not portal APIs.

## Non-Goals

- Do not invent a second search resolver.
- Do not search across campuses unless the backend resolver and permissions
  allow it.
- Do not add full Student 360 focus scroll behavior.
