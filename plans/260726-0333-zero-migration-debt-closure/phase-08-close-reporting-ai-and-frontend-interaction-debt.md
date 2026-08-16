---
phase: 8
title: "Phase 8: Close reporting AI and frontend interaction debt"
status: pending
priority: P1
effort: L
dependencies: [4, 5, 6, 7]
---

# Phase 8: Close reporting AI and frontend interaction debt

<!-- Rescoped 2026-08-16 from measured inventory; supersedes the 2026-07-26 draft. -->

## Overview

Move AI tools and the remaining frontend interaction code onto owner projections,
canonical filters, named routes, and the canonical API client.

Two corrections to the 2026-07-26 draft. Its File Inventory cited
`app/Modules/Academic/Reporting`, which has never existed; Academic reporting
projections live at `app/Modules/Academic/Progression/Queries/Reporting` and are
phase 5's scope now, so they leave this phase entirely. And the draft's first
step was to delete a dead Finance Major page, which is still present and now
carries a filter finding, so its deadness is unproven and it is treated as
ordinary scope until a route and build check says otherwise.

## Measured scope (2026-08-16)

97 findings: 40 `literal_frontend_urls`, 33 `shared_model_imports`, 24 `legacy_filter_stacks`.

| Cluster | Findings |
|---|---:|
| `resources/js/pages` | 59 |
| `app/Modules/AI` (Support 16, Models 7, Actions 5, Http 3, Queries 2) | 33 |
| `resources/js/components` | 3 |
| `resources/js/composables` | 2 |

The 24 filter-stack pages, in full: `Academic/CourseRanking/Index.vue`,
`Academic/Report/Index.vue`, `Admin/Academic/Gpa/History.vue`,
`Admin/Modules/Index.vue`, `Admin/Notifications/Ops/{Deliveries,Messages,Outbox}.vue`,
`Admin/Reports/AcademicProgressionAudit/{DeferReturns,Index,MissingDecisions,MissingDocuments}.vue`,
`Admin/Reports/ScholarshipRestorationWatchlist/Index.vue`,
`Admin/Reports/StudentActionsAudit.vue`,
`Admin/Reports/StudentDecisions/{Index,Show}.vue`,
`Admin/Reports/StudentLifecycleYearlyAnalysis/Index.vue`,
`CourseStatistics/Index.vue`, `FailedStudents/Index.vue`,
`Finance/Major/GenerateCharges.vue`, `Finance/Operations/Dashboard.vue`,
`Finance/Payments/DngWebhookEvents/Index.vue`, `RoomBookings/Index.vue`,
`Rooms/Index.vue`, `Students/AcademicSummary/RegistrationsTab.vue`.

Three of those pages postdate the original draft (the scholarship restoration
watchlist, the defer-return watchlist, and the Finance operations dashboard),
which is why the count rose from 22 to 24 rather than falling.

## Requirements

- [ ] Remove AI exceptions for User, Campus, and Academic models; `AI/Support` is the bulk.
- [ ] Convert the 24 enumerated filter pages to `useDataTable`.
- [ ] Convert the 40 literal frontend URLs to named routes.
- [ ] Delete the legacy filter composables once their consumer count is zero.
- [ ] Prove the Finance Major page live or dead by route and build check before touching it.
- [ ] Resolve repository issue 20 parity, permission, and performance review.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/AI/Support` | Replace concrete models with owner readers and projections |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/AI/Models` | Resolve cross-context reads |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages` | Convert the enumerated filters, URLs, and fetches |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/composables/useInertiaFilters.ts` | Delete once consumers are zero |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/composables/useServerTableQuery.ts` | Delete once consumers are zero |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/composables/useFilters.ts` | Delete once consumers are zero |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/utils/routes.ts` | Use the Ziggy-backed named route seam |
| `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Architecture` | Tighten AI owner-reader tests |

## Interface Checklist

- Reports use projections for display and fresh owner contracts for authorization decisions.
- `useDataTable` receives validated filters, a sort whitelist, pagination, and preserved query.
- Application HTTP calls use `useApi` plus `route(name, params)`; static assets use asset URLs.
- AI tools receive immutable actor and campus references with explicit permission checks.
- The AI metric catalogue pins source-query FQNs as escaped string literals, which the
  namespace sweep does not rewrite; check it by test, not by grep.

## Dependency Map

`all owner APIs/projections → AI → frontend consumers → remove legacy composables/constants`

## Implementation Steps

1. Prove the Finance Major page live or dead by route and build check; scope it accordingly.
2. Migrate AI readers to stable owner projections and tighten architecture tests.
3. Regenerate the residual filter, URL, and path manifest after phases 4 to 7 land, since
   those phases close some of these pages as a side effect; reject double ownership.
4. Convert the residual filtered views and application literals with matching backend contracts.
5. Correct scanner false positives without exempting real wrapper or constant literals.
6. Delete `useInertiaFilters`, `useServerTableQuery`, `useFilters`, stale constants, and exports.
7. Complete the parity, performance, and permission review and remove dashboard and report shells.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Filter defaults, invalid filter, invalid sort | Sanitized, whitelisted, retained in pagination |
| Empty and large report | Correct payload and accepted performance |
| Unauthorized AI or report request | Denied using fresh owner authority |
| Static address JSON | Loads without being treated as an app route |
| Named route parameter | Correct Ziggy URL; no literal fallback |
| Inventory | Filters, URLs, and AI shared imports all zero |

## Success Criteria

- [ ] `legacy_filter_stacks` and `literal_frontend_urls` are zero.
- [ ] `legacy_page_directories` stays zero and every moved or deleted file matches its owner ledger.
- [ ] AI shared-model imports and compatibility shells are zero.
- [ ] Root lint, format, typecheck, build, parity, performance, and permission gates pass.

## Risks and Security

- Reporting projections may be stale and AI tools can overreach. Enforce current authority
  at execution time, minimize exposed fields, and retain campus, role, and audit boundaries.
- Whole-project frontend type-check is killed by memory limits in the dev container. Use
  per-file checks locally and rely on the host or CI for the full pass.
