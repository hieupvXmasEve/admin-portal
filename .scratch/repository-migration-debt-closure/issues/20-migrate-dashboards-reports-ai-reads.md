# Migrate dashboards, reports and AI read surfaces

Status: ready-for-human

Portal impact: none

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Cut operational dashboards, academic/finance reports, exports, and AI read tools over to owner readers or declared cross-context Read Projections. Preserve permissions and campus scope, and ensure stale-tolerant reporting data never becomes an irreversible business gate.

## Acceptance criteria

- [ ] Every dashboard/report/AI field has a named owner reader or projection with declared freshness and permission scope.
- [ ] No report or AI path imports another context's internal service/model or performs business-state mutations.
- [ ] Existing filters, pagination, exports, totals, labels, campus scope, and authorization remain compatible.
- [ ] Hard gates query fresh owner contracts and never rely solely on a reporting projection.
- [ ] Report parity, performance, permission, frontend, and AI query-only regression tests pass.

## Blocked by

- [Migrate Student Registry identity and Guardian relationships](06-migrate-student-registry-identity-guardians.md)
- [Migrate Academic Catalog & Calendar management](08-migrate-academic-catalog-calendar.md)
- [Migrate assessment, gradebook and Course Result](11-migrate-assessment-gradebook-course-result.md)
- [Migrate Academic Progression & Lifecycle](12-migrate-academic-progression-lifecycle.md)
- [Close Finance obligation, settlement and collection Migration Debt](13-close-finance-obligation-settlement-collection-debt.md)
- [Migrate Notification and email delivery operations](15-migrate-notification-email-delivery.md)

## Sync-back evidence — 2026-07-25

- Added reader contracts with freshness, permission-scope, and field-ownership metadata for staff dashboards, charts, academic reports, and student dashboards.
- Routed dashboard and report controllers through reader contracts; retained legacy services/actions only as compatibility implementations.
- Moved academic report logic into `GetAcademicReportQuery`, preserving filters, pagination, exports, totals, labels, and current-campus scope.
- Added fail-closed dashboard campus handling, including the legacy quick-stats path, and campus-filtered program-change alerts.
- Protected academic report API reads/exports with `view_academic_report`.
- Added owner-reader, freshness, permission, and campus-scope metadata to AI catalogs and source references.
- Verification: Pint passed; `git diff --check` passed; focused architecture/AI/report/export tests passed (53 tests, 932 assertions).
- Portal impact: none. No frontend or portal files changed.
- Remaining scope: keep acceptance checkboxes open until broader parity, performance, permission, and frontend coverage is independently confirmed. The full repository suite was not completed; concurrent runs encountered shared test-database deadlocks and schema collisions.
