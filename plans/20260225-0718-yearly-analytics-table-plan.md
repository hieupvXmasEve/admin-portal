# Plan: Yearly Analytics Table (Architecture + Delivery)

## Executive Summary
Use existing monolith patterns: query classes over canonical tables, plus centralized business-term normalization. Start query-on-read + cache; add materialized yearly snapshot only if performance data proves need. This keeps YAGNI/KISS/DRY and avoids premature event-pipeline complexity.

## Context Links
- [`FinanceCharge`](/Users/hunt2412/hieupvdev/project/swinx/app/Models/FinanceCharge.php)
- [`GetBillingDashboardStatsQuery`](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Queries/Operations/GetBillingDashboardStatsQuery.php)
- [`GetBillingDashboardStudentsQuery`](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Queries/Operations/GetBillingDashboardStudentsQuery.php)
- [`AcademicProgressionEvent`](/Users/hunt2412/hieupvdev/project/swinx/app/Models/AcademicProgressionEvent.php)
- [`GetAcademicProgressionAuditQuery`](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Queries/Placement/GetAcademicProgressionAuditQuery.php)
- [`EventReportService`](/Users/hunt2412/hieupvdev/project/swinx/app/Services/EventReportService.php)
- Evaluation report: [`20260225-0718-yearly-analytics-architecture-evaluation.md`](/Users/hunt2412/hieupvdev/project/swinx/plans/reports/20260225-0718-yearly-analytics-architecture-evaluation.md)

## Scope
In scope:
- Architecture and implementation plan for yearly analytics table
- Query architecture
- Business term normalization (`DO/DF/BB2/NE`)
- Event-vs-snapshot decision path

Out of scope:
- Full data warehouse/event streaming platform
- Cross-system BI replatform

## Architecture Decision
### Decision A: Query Layer
- Create one dedicated query class for yearly table in owning module.
- SQL-first aggregation (`YEAR(...)`, `SUM(CASE WHEN...)`, grouped by year + canonical term).
- Keep controller as adapter only.

### Decision B: Data Source Strategy
- Source of truth stays current tables:
  - finance metrics: `finance_charges` (+ allocations when needed)
  - progression metrics: `academic_progression_events`
- Use snapshot fields where already designed for historical accuracy (`invoice_lines.amount_snapshot`).

### Decision C: Term Normalization
- Add one normalization source (table or static config if tiny and stable):
  - `raw_code`, `canonical_code`, `label`, `domain`, optional validity window.
- Query always joins/maps via this source.
- Unknown raw codes bucketed as `UNKNOWN` and logged.

### Decision D: Performance Strategy
- Phase 1: query-on-read + cache TTL.
- Phase 2 trigger only if measured pain: add materialized yearly snapshot table refreshed by scheduled job.

## Implementation Phases
### Phase 1: Contract + Canonical Definitions
1. Freeze metric contract for yearly table columns.
2. Freeze year definition (calendar vs academic).
3. Freeze exact meaning for `DO/DF/BB2/NE`.
4. Create canonical mapping source and seed initial values.

Deliverables:
- term-mapping spec
- initial normalized mapping records

### Phase 2: Yearly Aggregation Query
1. Add `GetYearlyAnalyticsTableQuery` (or equivalent naming per module).
2. Implement SQL aggregation with canonical mapping.
3. Add optional filters: campus, program, year range.
4. Add cache key strategy per filter set.

Deliverables:
- query class
- endpoint/controller wiring

### Phase 3: Validation + Guardrails
1. Add tests:
- mapping tests (`DO/DF/BB2/NE` -> canonical)
- aggregation tests with mixed charges/events
- unknown-code fallback test
2. Add explain-plan check for largest query.
3. Add indexes if missing (year/filter columns + mapping lookup key).

Deliverables:
- feature/integration tests
- index migration(s) if needed

### Phase 4: Snapshot Escalation (Conditional)
Run only if Phase 2 fails SLA.
1. Create yearly materialized table.
2. Add scheduled refresh command (incremental by year/semester).
3. Switch endpoint to read snapshot; keep fallback query.

Deliverables:
- snapshot table + refresh command
- monitoring notes

## File-Level Work Plan (expected)
- Add query class under `app/Modules/*/Queries/`
- Add/adjust endpoint controller under `app/Modules/*/Http/`
- Add tests under `tests/`
- Add mapping migration/seed in `database/migrations` + `database/seeders`
- Update docs under `docs/` after implementation

## Risks and Mitigations
- Risk: ambiguous `DO/DF/BB2/NE` semantics.
  - Mitigation: block coding until business glossary signed.
- Risk: mixed year semantics.
  - Mitigation: explicit `year_type` in API contract.
- Risk: performance regression from broad scans.
  - Mitigation: index + cache first; snapshot later only if needed.

## Success Criteria
- Single endpoint/query returns yearly table with stable contract.
- Term normalization centralized; no duplicated mapping logic.
- Tests prove correctness on edge cases and unknown codes.
- Meets latency target with query+cache, or snapshot fallback enabled.

## TODO Checklist
- [ ] Confirm meanings for `DO/DF/BB2/NE`
- [ ] Confirm year semantics
- [ ] Confirm metric list for v1 yearly table
- [ ] Implement normalized mapping source
- [ ] Implement yearly query class
- [ ] Wire endpoint
- [ ] Add tests
- [ ] Measure performance and decide on snapshot escalation

## Open Questions
1. Where should yearly table live: Finance module, Academic module, or cross-module reporting module?
2. Should normalization mapping be business-editable (admin UI) or code-seeded only?
3. What is acceptable refresh staleness for the first release?
