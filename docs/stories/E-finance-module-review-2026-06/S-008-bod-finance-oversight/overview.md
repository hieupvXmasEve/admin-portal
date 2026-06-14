# Overview

## Current Behavior

BOD has no dedicated read-only Finance oversight surface. Existing Finance pages
are operator-first, permission-gated mainly for admin/staff work, and include
action buttons that are inappropriate for leadership review.

## Target Behavior

Add a read-only BOD Finance overview after the core money source is reliable.
The page shows multi-semester KPIs and charts for billed amount, collected
amount, outstanding debt, collection rate, overdue debt, and debtor count.
It must not double-count the same obligation through both invoice and DNG rails.

## Affected Users

- BOD / leadership role.
- Finance managers who need read-only aggregate review.

## Affected Product Docs

- `docs/features/finance/finance-module-review-2026-06-13.md`
- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/design-guidelines.md`

## Portal Impact

None. This is an internal admin/staff web surface.

## Non-Goals

- Do not add write actions.
- Do not expose the page to student or lecturer portals.
- Do not build on stale/cache-only math before story 002/003 proof exists.
- Do not mix DNG due items and invoice due items into one KPI until the product
  owner has decided how those rails reconcile.
