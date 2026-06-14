# Design

## Domain Model

The page reads aggregate Finance facts:

- Total billed.
- Collected.
- Outstanding.
- Collection rate.
- Overdue.
- Debtor count.
- Revenue/debt by charge type and semester.

All numbers must come from canonical ledger/cache rules accepted in earlier
stories.

If a DNG request and an invoice represent the same student obligation, the BOD
query must count it once. DNG is a provider/payment rail; invoice/ledger data is
the Finance source of truth unless a product decision says otherwise.

## Application Flow

- Add a read-only Finance query for BOD aggregate data.
- Add an explicit rail reconciliation decision to the query contract: invoice
  ledger only, DNG only, or reconciled projection. The default should not add
  both rails together.
- Add a permission such as `view_finance_bod_overview` and assign it to the BOD
  role only after role audit.
- Render an Inertia page with deferred heavy chart props if needed.
- Add drill-down links to existing read-only list surfaces without action
  buttons.

## Interface Contract

Admin web route proposal:

- `GET /finance/operations/bod-overview`
- Route name: `finance.operations.bod-overview`

Props remain snake_case. Chart data uses existing Chart.js/vue-chartjs
components already in the repo.

## Data Model

No source-of-truth tables in this story. Add indexes only if aggregate query
proof requires them and they are not covered by story 003. Do not depend on
stale snapshot cache before story 002 makes cache rebuildable.

## UI / Platform Impact

Use existing layout, shared chart components, `useDataTable` for drill-down
lists, and Vietnamese finance terminology.

## Observability

Query timing and aggregate SQL should be easy to inspect. No write audit needed
because the page is read-only.

## Alternatives Considered

1. Give BOD access to existing operator pages.
   - Rejected because those pages include destructive actions and worklists.
2. Build charts before fixing balance truth.
   - Rejected because leadership metrics amplify wrong data.
