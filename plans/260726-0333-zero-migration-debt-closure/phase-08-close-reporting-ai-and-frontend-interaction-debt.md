---
title: "Phase 8: Close reporting AI and frontend interaction debt"
status: todo
priority: P1
effort: XL
dependencies: [4, 5, 6, 7]
---

# Phase 8: Close reporting AI and frontend interaction debt

## Overview

Move dashboards, reports, AI tools, exports/imports, and all remaining frontend
interaction code to owner projections, canonical filters, named routes, and
the canonical API client.

## Requirements

- [ ] Remove AI/reporting exceptions for User, Campus, and Academic models.
- [ ] Verify every original filter/URL finding has exactly one domain owner; migrate
  only the enumerated residual manifest not already closed by phases 4–7.
- [ ] Delete legacy filter composables/exports after consumer count is zero.
- [ ] Resolve repository issue 20 parity, permission, and performance review.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/AI` | Replace concrete models with owner readers/projections |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Reporting` | Own Academic reporting projections |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/composables` | Remove legacy filters; align `useApi` types |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/utils/routes.ts` | Use Ziggy-backed named route seam |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages` | Convert remaining filters, URLs, fetches |
| `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Architecture` | Tighten AI/report owner-reader tests |

## Interface Checklist

- Reports use projections for display and fresh owner contracts for authorization decisions.
- `useDataTable` receives validated filters, sort whitelist, pagination, and preserved query.
- Application HTTP calls use `useApi` plus `route(name, params)`; static assets use asset URLs.
- AI tools receive immutable actor/campus references with explicit permission checks.

## Dependency Map

`all owner APIs/projections → reports/AI → frontend consumers → remove legacy composables/constants`

## Implementation Steps

1. Delete the dead Finance Major page only after route/build proof.
2. Migrate report/AI readers to stable owner projections and tighten architecture tests.
3. Generate the residual filter/URL/path manifest after phases 4–7; reject double ownership.
4. Convert only residual filtered views and application literals with matching backend contracts.
5. Correct scanner false positives without exempting real wrapper/constant literals.
6. Delete `useInertiaFilters`, `useServerTableQuery`, `useFilters`, stale constants, and exports.
7. Complete parity/performance/permission review and remove dashboard/report shells.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Filter defaults/invalid filter/sort | Sanitized, whitelisted, retained in pagination |
| Empty/large report | Correct payload and accepted performance |
| Unauthorized AI/report request | Denied using fresh owner authority |
| Static address JSON | Loads without being treated as app route |
| Named route parameter | Correct Ziggy URL; no literal fallback |
| Inventory | filters, URLs, AI/report shared imports all zero |

## Success Criteria

- [ ] `legacy_filter_stacks=0` and `literal_frontend_urls=0`.
- [ ] `legacy_page_directories=0`; every moved/deleted file matches its owner ledger.
- [ ] AI/reporting shared-model imports and compatibility shells are zero.
- [ ] Root lint, format, typecheck, build, parity, performance, and permission gates pass.

## Risks and Security

- Reporting projections may be stale and AI tools can overreach. Enforce current authority
  at execution time, minimize exposed fields, and retain campus/role/audit boundaries.
