# Serve Finance settlement and operations through Registry and Progression readers

Status: ready-for-agent

Portal impact: student

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Cut Finance settlement worklists, billing operations, audit search, dashboards, invoices, payments, and clearance decisions over to Student Registry and Academic Progression & Lifecycle readers. Finance retains local money aggregates and projections while identity and academic eligibility facts come from their owners.

## Acceptance criteria

- [ ] Finance operations resolve Student display, contact, campus, and search facts through Student Registry readers.
- [ ] Academic lifecycle, Program Enrollment Status, Study Stage, and eligibility facts come through Progression readers rather than Student status fields.
- [ ] Hard financial gates query authoritative Finance settlement state synchronously and do not use stale cross-context projections.
- [ ] Dashboards and worklists may use declared stale-tolerant projections but expose consistent freshness semantics.
- [ ] Existing totals, filters, campus scoping, permissions, exports, invoices, payments, and audit results remain unchanged.
- [ ] Architecture tests reject new Finance operations/reporting reads of Student or Progression persistence.
- [ ] Finance behavior, invariant, architecture, and student portal checks pass.

## Blocked by

- [Issue 07: Introduce Student Registry through the Finance Student overview](07-introduce-student-registry-through-finance-overview.md)
- [Issue 10: Materialize Program Enrollment and separate its state machines](10-materialize-program-enrollment.md)
- [Issue 17: Run defer, resume, and dropout through Program Enrollment and Finance contracts](17-run-student-lifecycle-through-progression-and-finance.md)

## Comments

- 2026-07-19: Dashboard cutover in progress. Candidate and retake facts now come through Registry/Progression owner readers and the Academic gateway; Finance-owned defer and settlement facts remain synchronous local reads. Added an architecture guard for this seam and exposed `freshness.mode=live_owner_reads` with `freshness.as_of` in the dashboard payload. The issue remains `ready-for-agent`: settlement worklists, reporting, invoice/payment, audit, and clearance surfaces still need their named cutovers and broader architecture coverage.
- 2026-07-19: Extended the partial cutover to manual payments, charge lookup/create/show, and non-academic batch generation. These use Student Registry references for identity, code lookup, display, and campus checks; charge search resolves student matches through Registry. Dashboard defer policy remains Finance-owned and preserves its original case-level totals and per-student EXISTS semantics, while Academic supplies only registration/retake facts. The issue remains `ready-for-agent`: settlement worklists, audit, invoice/payment list/detail, reporting, clearance, and their broad architecture guard still require their own cutovers.
- Verification: Pint, the owner-reader architecture test, Registry reader test, manual-payment test, charge controller/lookup tests, non-academic generation test, and dashboard retake/defer-count regression tests passed. `npm run type-check` passed earlier in this work window; no portal API contract or portal files changed, so portal checks were not needed.
- 2026-07-19: Invoice-list filtering/display and payment-detail display now resolve identity through Student Registry. Invoice lookup and the owner-reader architecture test pass. `PaymentPagesTest` has pre-existing failures: its index assertion distinguishes an integer from an equivalent float, and its detail/create assertions lack the route permissions/routes now required by the suite; this is recorded as a verification gap rather than changed here.
- 2026-07-19: Settlement worklist campus scope, search, student display, and lifecycle-status filter now use Student Registry and Program Enrollment readers. `SettlementWorklistTest` (5 tests) and the owner-reader architecture test pass. Remaining named surfaces: audit internals, payment list, invoice detail/export, reporting, and clearance.
- 2026-07-19: Invoice detail campus authorization and student/program display now use Student Registry and Program Enrollment readers. `InvoiceSettlementBreakdownTest` (3 tests, 67 assertions) and the owner-reader architecture test pass. Remaining named surfaces: invoice export, payment list, audit internals, reporting, and clearance.
- 2026-07-19: Invoice export now resolves campus scope, search matches, and exported student name/code through Student Registry for each export chunk. Current and as-of settlement export regression tests pass (2 tests, 14 assertions). Remaining named surfaces: payment list, audit internals, reporting, and clearance.
- 2026-07-19: Payment-list campus scope, search, display, and student-code/name sorting now use Student Registry; Finance still owns payment and allocation sums. Audit workspace/controller and graph/search query paths were added to the owner-reader architecture guard because they already use Registry/Lifecycle readers. `PaymentLookupTest` (3 tests, 14 assertions) and the architecture test pass. Remaining named surfaces: reporting and clearance, plus fuller regression coverage for payment-page routes.
