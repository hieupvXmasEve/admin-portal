# Overview

## Current Behavior

The Finance Office "🔎 Tra cứu & Audit" group lists three demoted object-list
surfaces and one audit destination, but they do not share a consistent lookup
experience:

- **Charge Ledger** (`/finance/charges`) — manual filter state, eye-icon to detail
  page, no column sort, no row-click to Student 360, no multi-select.
- **Invoices** (`/finance/invoices`) — older `useTableFilters` wrapper, eye-icon
  to detail page, no sort, no row-click, no selection; Excel export works.
- **Payments** (`/finance/payments`) — `useInertiaFilters` (older), sortable
  heads, row click goes to payment detail (not 360), date-range filter supported
  in `ListPaymentsQuery` but not exposed in the UI.
- **Audit Workspace** (`/finance/audit`) — graph renders as a count-only summary
  (node-group badges + edge count); signed timeline and derived-balance table
  exist but the money-flow graph panel is incomplete.

Backend charge and invoice list queries are inline in controllers; payments
already delegate to `ListPaymentsQuery` with whitelisted sort and `date_range`.

## Target Behavior

Finish Milestone 5: one consistent, dense, server-driven **lookup standard**
across Charge Ledger, Invoices, and Payments, and make Audit Workspace a proper
drilldown **destination**.

Lookup standard (design §7.3):

- Server sort + filter + paginate via `useDataTable` + extracted read-only Query
  classes and `Filter*Request` contracts.
- Sticky table header, right-aligned tabular money, status chips from existing
  badge maps.
- **Row → Student 360** with `focus=<type>:<id>` deep-link param.
- **Multi-select → Batch Studio** (M4) carrying `student_ids` when the operator
  has the relevant action permissions.
- Detail/show pages remain reachable for audit deep-links.

Audit Workspace (design §4.4 / §10):

- Replace count-only graph summary with a full **money-flow panel** (nodes grouped
  by type + edges grouped by kind) from existing deferred `graph` props.
- Polish signed-ledger timeline coloring (+ green / − red).
- Accept `finding_code` deep-link banner when M3 invariant-drilldown params are
  present (consume only — do not implement M3 resolver here).

**Read-only milestone:** no money writes, no money math. Reuse existing queries,
`SettlementService`, and audit graph/timeline builders.

This packet is the canonical Milestone 5 story for
`docs/superpowers/plans/2026-06-15-finance-office-lookup-and-audit.md`.

## Plan Task Mapping

| Plan task | Included in this story |
| --- | --- |
| Task 0: `grantFinance()` test helper | Shared Lookup suite auth bootstrap |
| Task 1: `ListFinanceChargesQuery` + `FilterFinanceChargesRequest` | Extract charge list + whitelisted sort |
| Task 2: Thin `FinanceChargeController::index` | Delegate to query + request |
| Task 3: `ListStudentInvoicesQuery` + invoice controller | Extract invoice list + whitelisted sort |
| Task 4: `FilterPaymentsRequest` | Validate payment lookup filters (query already supports sort/date-range) |
| Task 5: Shared lookup frontend | `useLookupSelection`, `SendToBatchBar`, `LookupRowActions` |
| Task 6: Charge Ledger page | Reference `useDataTable` migration |
| Task 7: Invoices page | Mirror charge lookup standard; keep Excel export |
| Task 8: Payments page | `useDataTable` + date-range UI + row→360 |
| Task 9: Audit Workspace polish | `MoneyFlowGraph` + timeline colors + finding banner |
| Task 10: Nav + milestone evidence | Sidebar verify + Lookup test suite + browser smoke + invariant sanity |

## Affected Users

- Finance staff who search charges, invoices, and payments and need fast paths to
  Student 360 or Batch Studio bulk jobs.
- Finance auditors who drill from Cockpit/invariant findings or universal search
  into the Audit Workspace money-flow graph.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md` (§3.2 sidebar IA,
  §4.4 drilldown destination, §5.4/§7.1 signed ledger, §7.3 dense table, §7.4
  colors, §10 milestone 5)
- `docs/superpowers/plans/2026-06-15-finance-office-lookup-and-audit.md`
- `docs/stories/E-finance-module-review-2026-06/README.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/`
- `docs/stories/E-finance-module-review-2026-06/S-010-student-360-full/`
- `docs/stories/E-finance-module-review-2026-06/S-010-batch-studio/`

## Portal Impact

Portal impact: none.

This story targets admin/staff Inertia UI and Finance web routes. It does not
change `/api/v1/student/*` or `/api/v1/lecturer/*`.

## Non-Goals

- No money writes, no new money math, no new ledger tables.
- Do not remove legacy detail pages (`finance.charges.show`,
  `finance.invoices.show`, `finance.payments.show`) — Audit `SOURCE_ROUTES` still
  deep-link into them.
- Do not implement M3 invariant-drilldown params (`finding_code` / `scope` /
  `sample_id` + `FinanceInvariantSampleResolver`) — consume banner only if M3
  merged.
- No force-directed/SVG graph library for Audit — columnar node/edge panel only.
- Do not add server sort for derived `outstanding_balance` on invoices unless
  explicitly approved (see unresolved questions in plan).

## Dependencies

| Dependency | Required for | Blocks lookup work? |
| --- | --- | --- |
| M1 `FIN-REV-010-finance-staff-workspace` | `financeRoutes`, semester prop, sidebar IA, `useDataTable` foundation | Yes — must be merged |
| M2 `FIN-REV-010-student-360-full` | Student 360 `focus` deep-link param | Yes — row→360 needs focus |
| M4 `FIN-REV-010-batch-studio` | Batch Studio hand-off + wizard prefill | No — lookup tables ship without prefill; bar navigates with `student_ids` |
| M3 Cockpit invariant drilldown | `finding_code` banner on Audit | No — banner is optional read-only |

## Unresolved Questions (confirm at implementation)

- **Invoice `outstanding_balance` sort:** display-only vs derived SQL sort — see
  plan self-review.
- **`chargeTypes` options source:** reuse exact accessor from current
  `FinanceChargeController::index` (do not invent helpers).
- **M4 `student_ids` prefill passthrough:** 2-line forward in Batch Studio
  controller + wizard pages if not already merged.
- **`TableEmpty` colspan + loading overlay:** confirm repo primitives and whether
  to wire `useDataTable.isLoading` spinner on all three tables.