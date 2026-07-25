---
title: Dashboard, report, and AI read-surface cutover
date: 2026-07-25
type: technical-journal
---

# Dashboard, report, and AI read-surface cutover

## Context

Issue 20 required operational dashboards, academic reports, exports, and AI read tools to identify their owner reader or declared cross-context read projection. The migration had to preserve filters, pagination, totals, campus scope, permissions, and query-only behavior while keeping stale reporting data out of irreversible business gates.

## Implementation decisions

- Added `ReadSurfaceMetadata` plus explicit dashboard, academic-report, and student-dashboard reader contracts declaring freshness, permission scope, and field ownership.
- Bound dashboard controllers and report controllers to reader contracts; retained legacy services/actions as compatibility implementations or adapters.
- Moved academic report query logic into `GetAcademicReportQuery`, preserving the existing report/export response shape and current-campus filtering.
- Added fail-closed campus handling for dashboard reads and campus filtering for pending program-change alerts.
- Aligned quick-stats fail-closed behavior with controller exception handling so missing campus scope consistently surfaces as an access-denied response.
- Protected academic report API reads and exports with `view_academic_report` route and request authorization.
- Added owner-reader, freshness, permission, and campus-scope metadata to AI metric, entity, and student-profile catalogs and tool source references.
- Added architecture coverage for reader bindings, metadata declarations, query-only controllers, report authorization, and dashboard campus fail-closed behavior.

## Files and areas changed

- `app/Shared/Contracts/` — read-surface metadata and dashboard/report reader contracts.
- `app/Modules/Academic/Queries/Reporting/GetAcademicReportQuery.php` — owner report query.
- `app/Actions/Academic/GetAcademicReportAction.php` — compatibility adapter.
- Dashboard services/controllers and Academic report controllers/requests/routes.
- `app/Modules/AI/Support/` — catalog and tool source metadata.
- `app/Modules/{Academic,Platform}/Providers/` — contract bindings.
- `tests/Feature/Architecture/` — migration architecture tests.

## Verification

- Pint completed successfully after formatting the changed PHP files.
- `git diff --check` passed.
- The combined focused architecture/AI/report/export suite passed: 53 tests, 932 assertions.

## Remaining risk

The full repository suite was not run to completion. Broader concurrent runs encountered shared test-database deadlocks, migration-table corruption, and schema collisions, so those failures are environmental evidence rather than confirmed behavior regressions. No frontend or portal files were changed; frontend/portal checks were therefore not run. Unrelated worktree changes remain untouched.
