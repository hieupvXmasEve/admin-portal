# Overview

## Current Behavior

`/forms/admin/inbox` scopes query responses by the logged-in user's department memberships and assignments unless the user is admin, super admin, or has `view_all_queries`.

## Target Behavior

The inbox shows all query responses in the current campus for users with `view_queries`. Department selection is a convenience filter, not a visibility boundary, and the UI offers only `Student HQ` and `Academic Service` department filters.

Portal impact: none.

## Affected Users

- Admin and staff users who manage student query inbox records.

## Affected Product Docs

- `docs/project-overview-pdr.md`
- `docs/rules/backend.md`
- `docs/rules/frontend.md`

## Non-Goals

- No student or lecturer portal API contract change.
- No schema or migration change.
- No change to assignment ownership rules beyond keeping existing assignment controls restricted.
