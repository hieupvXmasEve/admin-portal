# Research Report: Yearly Analytics Table Architecture (Laravel Monolith)

## Scope
Evaluate architecture/pattern choices for yearly analytics table with focus on:
- query architecture
- event vs snapshot strategy
- normalization of business terms (`DO/DF/BB2/NE`)
- minimal viable path under YAGNI/KISS/DRY

Research date: 2026-02-25

## Current System Facts (from codebase)
- Finance already uses ledger pattern: [`FinanceCharge`](/Users/hunt2412/hieupvdev/project/swinx/app/Models/FinanceCharge.php) with `source_type/source_id`, positive/negative amounts, active/void.
- Finance already uses snapshot at invoice-line level: `amount_snapshot`, `description_snapshot` (see `invoice_lines`).
- Academic already uses event log + snapshot pairing: [`AcademicProgressionEvent`](/Users/hunt2412/hieupvdev/project/swinx/app/Models/AcademicProgressionEvent.php) + snapshot state on `students`.
- Existing analytics queries are mostly query-class based (good), but some service analytics still in-memory collection loops (risk for yearly scale), e.g. [`EventReportService`](/Users/hunt2412/hieupvdev/project/swinx/app/Services/EventReportService.php).

## Findings
### 1. Query Architecture Choice
Recommended: **Query-object + SQL aggregation first**, no new pipeline yet.
- Implement one dedicated query class for yearly table (same style as `GetBillingDashboardStatsQuery`).
- Aggregate directly in SQL (`group by year`, `sum(case when...)`) from canonical sources.
- Keep controller thin; no business rules in controller.
- Add DB indexes before adding infra.

Why:
- Fits monolith style already used.
- Lowest delivery risk.
- Avoids early over-design (no event bus, no CQRS split yet).

### 2. Event vs Snapshot Strategy
Recommended: **Hybrid, phased**.
- Source of truth: existing ledger/events (`finance_charges`, `academic_progression_events`).
- Phase 1 MVP: query-on-read + short cache (5-15 min) for yearly table.
- Phase 2 only if needed (slow query or high traffic): materialized yearly snapshot table refreshed by scheduled job.

Why:
- You already have immutable-ish history structures; reuse them.
- Snapshot table only when read latency/cost justifies it.
- Keeps YAGNI.

### 3. Business Term Normalization (`DO/DF/BB2/NE`)
Current state: no explicit definition found in repo docs/code.

Recommended normalization model:
- Define canonical dimension for term codes (single source): e.g. `analytics_term_definitions`.
- Columns: `raw_code`, `canonical_code`, `label`, `domain`, `is_active`, `effective_from`, `effective_to`.
- Query layer always maps raw -> canonical before aggregation.
- Unknown code handling: map to `UNKNOWN` bucket + log warning.

Why:
- Stops hardcoded branch logic spread across queries.
- Future code additions do not break analytics.
- DRY: one mapping path reused by all reports.

### 4. Minimal Viable Approach (recommended)
1. Build one yearly analytics query class over existing tables.
2. Add lightweight canonical mapping source for `DO/DF/BB2/NE`.
3. Add cache.
4. Add tests for mapping + aggregation correctness.
5. Defer snapshot table until measured need.

## Technical Recommendation (brutal/concise)
- **Do not build event-driven analytics pipeline now.** Too much infra for current ask.
- **Do not duplicate business logic in multiple reports.** Centralize aggregation + mapping.
- **Do not trust raw term code directly in report SQL.** Normalize first.

## Risks
- Unknown semantics of `DO/DF/BB2/NE` can produce wrong numbers.
- Year boundary semantics unclear (calendar year vs academic year).
- Existing in-memory analytics patterns can become slow if copied.

## Success Criteria
- Single query endpoint returns yearly table under target latency.
- Same canonical term mapping used across all yearly rows.
- No duplicated mapping logic in controller/service/view.
- Numeric parity validated against sample manual SQL checks.

## Unresolved Questions
1. Exact semantics for `DO`, `DF`, `BB2`, `NE`? (domain meaning + allowed transitions)
2. Year dimension = calendar year or academic year?
3. Should yearly table span Finance only, Academic only, or mixed metrics?
4. Required freshness SLA: real-time, hourly, or daily?
