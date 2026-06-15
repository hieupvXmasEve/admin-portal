# Exec Plan

## Goal

Create a Finance Audit Workspace story that gives staff one safe place to find
and understand a student's money graph without changing money state.

## Scope

In scope:

- New `FIN-REV-009-finance-audit-workspace` story packet.
- Universal search and deterministic resolver design.
- Graph/ledger timeline design for student, invoice, charge, payment, DNG,
  installment, and discount relationships.
- Derived settlement truth through `SettlementService`.
- Cache-vs-derived drift warnings.
- Extraction of the existing `AuditFinanceInvariants` INV-1..INV-15 catalog into
  subject-scopeable shared code used by both the console command and workspace.
- Permission, campus scope, authenticated share links, and export audit
  requirements.
- New Finance permissions: `view_finance_audit_workspace` and
  `export_finance_audit_workspace`.
- Menu IA note: Audit Workspace becomes the primary Finance Office entry while
  source pages remain available.

Out of scope:

- Implementing the workspace in this story creation pass.
- Changing settlement math, allocation behavior, DNG pushes/webhooks, or DB
  constraints.
- Adding BOD oversight charts.
- Adding destructive or money-changing workspace actions.
- Export implementation if no existing audit-log mechanism can safely record
  PII egress.

## Risk Classification

Risk flags:

- Authorization.
- Audit/security.
- Public contracts.
- Existing behavior.
- Weak proof.

Hard gates:

- Permission and campus scoping for aggregated financial records.
- PII egress through export.
- DNG/payment data visibility.

Portal impact:

- None.

## Work Phases

1. Register the high-risk story and keep `FIN-REV-007` scoped to UI foundation
   and graph-safe navigation links.
2. During implementation planning, audit exact resolver formats and indexes from
   current schema/data before writing code.
3. Add and sync `view_finance_audit_workspace` and
   `export_finance_audit_workspace` through the existing permission config and
   seeding/sync pattern.
4. Extract `AuditFinanceInvariants` into a shared
   `Support\Integrity\FinanceInvariantRegistry` + `FinanceIntegrityAuditor`
   (student-scoped via `Support\Integrity\FinanceAuditScope`) supporting both
   global command execution and subject-scoped workspace warnings. Preserve
   byte-identical command output (counts plus `ERROR` rows) and surface — never
   swallow — a failing invariant SQL.
5. Add the route, controller, FormRequest, and read queries with permission and
   campus scoping enforced at resolver and graph-query boundaries.
6. Build graph/timeline/warning resources (`Support\Audit\FinanceLedgerTimelineBuilder`
   + `FinanceAuditWarningBuilder`) around existing models, `SettlementService`,
   and the shared invariant registry; do not duplicate settlement formulas.
7. Build the Inertia page with universal search, ambiguous-result state,
   timeline, derived-balance panel, warning panel, and permission-checked links.
8. Add optional lightweight actions only after safety gates:
   - copy/share authenticated link,
   - refresh,
   - source-page deep links,
   - export only with `export_finance_audit_workspace` plus audit logging.
9. Move Audit Workspace to the top of the Finance Office menu and keep source
   pages available for specialist drill-down.
10. Verify unit/integration/frontend/browser/access-control coverage and record
   evidence in Harness.

## Stop Conditions

Pause for human confirmation if:

- Resolver formats collide and cannot be disambiguated by prefix/precedence.
- A result can only be found by leaking whether hidden-campus data exists.
- Export cannot be audited with the existing logging/audit infrastructure.
- Building the workspace requires new schema, denormalized read tables, or a
  search index.
- A requested action would mutate money state from the workspace.
- `SettlementService` cannot supply a needed derived value without duplicating
  settlement math.
- Performance requires broad unbounded student-history scans.
