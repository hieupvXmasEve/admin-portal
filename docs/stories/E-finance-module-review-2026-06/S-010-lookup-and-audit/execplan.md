# Exec Plan

## Goal

Implement and accept Milestone 5 from
`docs/superpowers/plans/2026-06-15-finance-office-lookup-and-audit.md` as one
consolidated Harness story.

Deliver a unified Finance lookup standard across Charge Ledger, Invoices, and
Payments (server-driven `useDataTable` tables with row→360 and Batch Studio
hand-off), plus Audit Workspace money-flow graph and timeline polish. Read-only —
no money writes or new money math.

## Scope

In scope:

- Shared `grantFinance()` test helper for Lookup suite (Task 0).
- Extracted `ListFinanceChargesQuery`, `ListStudentInvoicesQuery`, and three
  `Filter*Request` classes with whitelisted server sort (Tasks 1–4).
- Thin charge and invoice controllers; payments controller uses new request
  validation (Tasks 2–4).
- Shared frontend: `useLookupSelection`, `SendToBatchBar`, `LookupRowActions`
  (Task 5).
- Migrate Charges/Invoices/Payments index pages to lookup standard (Tasks 6–8).
- `MoneyFlowGraph` + Audit Workspace wiring + signed-timeline colors + optional
  `finding_code` banner (Task 9).
- Nav verification, full Lookup backend suite, targeted lint, browser smoke,
  invariant sanity check, evidence in this story's `validation.md` (Task 10).

Out of scope:

- M3 Cockpit invariant resolver implementation (`finding_code`/`scope`/`sample_id`).
- New money calculations, write Actions, or ledger schema.
- Removing or rewriting detail/show pages.
- Force-directed graph visualization library.
- Student/lecturer portal API changes.

## Risk Classification

Risk flags:

- Read-model extraction (query parity with inline controller logic).
- Authorization — per-surface permissions and Batch Studio hand-off gates.
- Campus scope correctness on extracted queries.
- Frontend migration from legacy filter composables to `useDataTable`.
- Weak browser automation coverage for table interactions.

Hard gates:

- Per-surface permissions unchanged; row/batch actions gated correctly.
- Campus filter behavior matches pre-extraction controllers.
- No money writes introduced — invariant counts unchanged after M5.
- Detail/show pages remain reachable; Audit `SOURCE_ROUTES` unaffected.

## Work Phases

1. **Test foundation** — `grantFinance()` helper (Task 0).
2. **Backend read models** — charge + invoice queries/requests, payment request,
   thin controllers (Tasks 1–4).
3. **Frontend shared** — selection composable, batch bar, row actions (Task 5).
4. **Lookup pages** — Charges reference migration, Invoices mirror, Payments +
   date-range UI (Tasks 6–8).
5. **Audit polish** — `MoneyFlowGraph`, timeline colors, finding banner (Task 9).
6. **Acceptance** — nav verify, Lookup test suite, lint, browser smoke, invariant
   sanity, evidence recorded (Task 10).

## Stop Conditions

Pause for human confirmation if:

- Extracted query behavior diverges from current production list (filter/sort/
  campus scope mismatch).
- A step requires new money math not present in existing queries.
- M4 Batch Studio prefill passthrough is missing and product expects wizard
  seeding on day one.
- `finance:audit-invariants` counts change after read-only work (indicates
  accidental write path).

## Implementation Source

Execute task-by-task from:

`docs/superpowers/plans/2026-06-15-finance-office-lookup-and-audit.md`

Recommended execution mode: subagent-driven development (one task per subagent
with review between tasks) or inline execution with checkpoints.