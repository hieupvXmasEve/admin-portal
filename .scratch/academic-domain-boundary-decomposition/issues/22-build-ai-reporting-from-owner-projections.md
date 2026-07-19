# Build AI and reporting from owner readers and read projections

Status: completed

Portal impact: none

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Converge Staff Copilot, dashboards, exports, and multi-context reports on owner-provided readers or rebuildable Cross-context Read Projections. Preserve permission, campus scope, redaction, source evidence, and existing report results while preventing analytical joins from becoming command-side dependencies or hard-gate evidence.

## Acceptance criteria

- [x] AI and reporting obtain Registry, Catalog, Delivery, Progression, and Finance facts through owner readers or explicit projections.
- [x] Every projection has an owner, rebuild path, freshness semantics, and documented consumer scope.
- [x] Staff permissions, campus scope, AI allowlists, redaction, and audit are enforced before data is returned.
- [x] AI remains query-only and cannot execute domain commands or mutate authoritative state.
- [x] No hard business gate uses an AI result, dashboard total, report export, or stale projection as sole evidence.
- [x] Existing metrics, reports, exports, filters, and Staff Copilot behavior remain compatible.
- [x] Reader, projection, permission, architecture, and reporting tests pass.

## Blocked by

- [Issue 07: Introduce Student Registry through the Finance Student overview](07-introduce-student-registry-through-finance-overview.md)
- [Issue 10: Materialize Program Enrollment and separate its state machines](10-materialize-program-enrollment.md)
- [Issue 16: Commit Course Result and Transcript Entry atomically](16-commit-course-result-and-transcript-atomically.md)
- [Issue 20: Serve Finance settlement and operations through Registry and Progression readers](20-serve-finance-operations-through-owner-readers.md)
- [Issue 21: Serve Finance EGC and pricing worklists through Progression readers](21-serve-finance-egc-pricing-through-progression-readers.md)

## Verification

- Removed the remaining AI imports of Registry, Catalog, Delivery, and Academic Period source models. AI now resolves Academic Period filters through `AcademicPeriodReader`; owner adapters remain the only layer that reads source persistence.
- Added an architecture guard that rejects AI imports of other contexts' source models, Actions, Queries, or Services.
- Metrics are owner-reader reads computed at request time; no Cross-context Read Projection is introduced by this slice. Existing catalog freshness, source evidence, permission, campus-scope, redaction, audit, and query-only controls remain the runtime contract.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent`
- `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/AiReportingOwnerReadersArchTest.php tests/Feature/AI/AiQueryMetricsToolTest.php tests/Feature/AI/AiEntityCatalogSearchTest.php tests/Feature/AI/AiStudentProfileSectionsTest.php`
- `./scripts/dev.sh npm run type-check`
- `./scripts/dev.sh test`
- `./scripts/dev.sh npm run lint`
- `./scripts/dev.sh npm run format:check`
