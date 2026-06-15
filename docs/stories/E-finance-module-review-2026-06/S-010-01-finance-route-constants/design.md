# Design

## Domain Model

No Finance domain model changes. This story introduces frontend route constants
only.

## Application Flow

Frontend code imports `financeRoutes` and calls Ziggy `route()` through named
route constants rather than hard-coded Finance URLs.

## Interface Contract

Created frontend contract:

- `FINANCE_ROUTE_NAMES`: typed object of Finance route names.
- `financeRoutes`: helper object grouped by operator work area:
  - `today`
  - `feeGeneration`
  - `collect`
  - `exceptions`
  - `lookup`
  - `students`
  - `search`
  - `semesterContext`

The helper may contain route names whose backend routes are introduced by later
stories, but code must not call those helpers until their corresponding routes
exist.

## Data Model

No database changes.

## UI / Platform Impact

No rendered UI change by itself. This story enables later sidebar and topbar
changes.

## Observability

No logs or audit records.

## Alternatives Considered

1. Keep literal URLs in each component.
   - Rejected because the Finance Office IA rewrite would keep duplicating route
     knowledge and make future route renames brittle.
2. Add helpers only when each page is built.
   - Rejected because Task 6 needs the shared helper for the whole sidebar
     migration.
