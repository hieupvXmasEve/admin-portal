# Overview

## Current Behavior

Finance Operations has live routes/UI that are still stubbed or inefficient:
exception fixing returns success without doing work, exception listing returns
empty, due calendar counts can double count, several queries paginate in PHP or
use N+1 accessors/correlated subqueries, and allocation priority input is
ignored. The "Due Calendar" label is also misleading because the surface is a
DNG reminder list, not a calendar/timeline.

## Target Behavior

Operations pages either perform real, audited work or explicitly refuse the
action. Summary counts, list rows, dashboard metrics, and query performance must
agree with the canonical Finance model. Due reminder navigation should be named
so staff understand whether they are seeing a reminder queue, calendar, or
exception workflow.

## Affected Users

- Finance operations staff.
- Finance managers using dashboard counts.
- Developers maintaining Finance query/read models.

## Affected Product Docs

- `docs/features/finance/finance-module-review-2026-06-13.md`
- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/codebase-summary.md`

## Portal Impact

None.

## Non-Goals

- Do not implement lifecycle exception page work already covered by
  `E-finance-lifecycle-exceptions`.
- Do not rewrite all Finance UI.
- Do not change DNG provider security.
