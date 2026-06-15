# Design

## Domain Model

The audit workspace models Finance as a graph plus ledger events.

Core nodes:

- `student`
- `invoice`
- `invoice_line`
- `charge`
- `payment`
- `payment_application`
- `invoice_discount`
- `discount_allocation`
- `dng_request`
- `dng_request_charge`
- `finance_charge_installment`

Core edges/events:

- `invoice_line` links an invoice to a charge.
- `payment_application` applies or reverses a student-level payment against an
  invoice line.
- `discount_allocation` allocates or releases a non-cash discount against an
  invoice line.
- `dng_request_charge` links one DNG request to one or many charge/installment
  rows.
- `dng_request.payment_id` links a paid DNG request to the bridged payment when
  available.

`Settlement` is not a node. The workspace reads derived totals through
`SettlementService::deriveInvoiceSnapshot()`, `getPaymentUnappliedAmount()`,
`getChargePaidAmount()`, and line-level settlement helpers. Cached invoice
columns are compared to derived values and surfaced as warnings when they drift.

There are two distinct truth layers:

- Current balances and user-facing money totals come from `SettlementService`.
- Integrity warnings come from a shared invariant registry extracted from the
  existing `AuditFinanceInvariants` console command. Those invariants may use
  SQL aggregates because they are DB integrity guards, not the UI balance read
  model.

## Application Flow

Create a read query layer under `app/Modules/Finance/Queries/Audit/`:

1. `ResolveFinanceAuditSearchQuery`
   - Parses the submitted search string with deterministic format and
     precedence rules.
   - Enforces permission and campus visibility before returning any match.
   - Returns a single resolved target or a short ambiguous-result list.
2. `GetFinanceAuditGraphQuery`
   - Builds the visible graph around the resolved target.
   - Reuses/adapts existing query/service outputs where they already represent
     source-page truth, especially payment details, DNG details, student balance,
     student charges, settlement helpers, exception collectors, and the finance
     invariant catalog.
   - Avoids reimplementing money formulas outside `SettlementService`.
3. `Support/Audit/FinanceLedgerTimelineBuilder`
   - Flattens graph edges into chronological ledger events for the UI.
   - Preserves event type and signed amounts instead of hiding reversals inside
     current balances.
4. `Support/Integrity/FinanceInvariantRegistry` + `Support/Integrity/FinanceIntegrityAuditor`
   - The registry extracts the existing INV-1..INV-15 catalog from
     `AuditFinanceInvariants` into shared definitions (one `{scope}` SQL token
     plus a student-scope predicate per invariant). The auditor runs them
     globally for the command or student-scoped for the workspace.
   - Subject scope is **student-resolved**: any searched subject
     (invoice/payment/DNG/charge) is reduced to its owning student id set
     (`Support/Integrity/FinanceAuditScope`) before invariants run.
   - The auditor never swallows a failing invariant: a SQL error surfaces as an
     `ERROR`/`invariant_error` result, so the command still prints `ERROR` and
     the workspace shows "audit unavailable" — never a false clean check.
   - Keeps invariant SQL and labels in one place so command output and
     workspace warnings cannot drift.
5. `Support/Audit/FinanceAuditWarningBuilder`
   - Maps scoped invariant failures (passing through `invariant` vs
     `invariant_error` kind) plus derived-balance checks into UI warnings.
   - Flags cache drift, unapplied payment, outstanding invoice lines, missing
     graph links, DNG/request mismatch, and visible lifecycle exceptions.

Namespace convention: the shared engine reused by the command + workspace lives
under `App\Modules\Finance\Support\Integrity\` (`FinanceAuditScope`,
`FinanceInvariant`, `FinanceInvariantRegistry`, `FinanceIntegrityAuditor`);
workspace-only pure transforms live under `App\Modules\Finance\Support\Audit\`
(`FinanceLedgerTimelineBuilder`, `FinanceAuditWarningBuilder`); read queries live
under `App\Modules\Finance\Queries\Audit\`.

Resolver precedence:

1. Explicit debug ids with prefixes such as `payment:<id>`, `invoice:<id>`,
   `dng:<id>`, or `charge:<id>`.
2. Exact `student_invoices.invoice_number`.
3. DNG formatted identifiers: `item_id`, then `dng_payment_id`, then
   `dng_transaction_id`.
4. Exact `students.student_id`.
5. Payment `external_ref`, only after invoice, DNG, and exact student-code
   matches fail. Free-form external refs must not win over a valid MSSV or other
   structured finance identifier.
6. Student name/email fuzzy search.

Bare integers must not guess across invoice/payment/DNG/charge ids. If more than
one visible match remains, the UI shows a choose-result state.

## Interface Contract

Add an authenticated Inertia route:

- `GET /finance/audit`
- route name: `finance.audit.index`
- permission: `view_finance_audit_workspace`

Add two permissions to `config/permission.php` under the Finance module and sync
them through the existing permission seeding/sync flow:

- `view_finance_audit_workspace`
- `export_finance_audit_workspace`

Request query parameters:

- `q`: search string.
- `target_type`: optional resolved target type for deep links.
- `target_id`: optional resolved target id for deep links.
- `semester_id`: optional scope for student-context searches.
- `billing_cycle_id`: optional scope for student-context searches.

Initial response:

- `filters`: current query/scope.
- `resolution`: empty, one target, or ambiguous result list.
- `graph`: deferred when a target is present.
- `timeline`: deferred when a target is present.
- `warnings`: deferred when a target is present.
- `links`: permission-checked deep links to source pages.
- `allowed_actions`: copy link, open source page, refresh, and export only when
  the current user has `export_finance_audit_workspace`.

Share links are normal authenticated routes, not bearer links. Opening a shared
audit URL must rerun permission and campus checks.

Export is PII egress. **For this MVP the export endpoint is deferred**: the
`export_finance_audit_workspace` permission is defined and seeded and the export
affordance renders disabled, but no egress route ships until an audit-log surface
is chosen. When export is built later it must require
`export_finance_audit_workspace`, write an audit record (actor, target, scope,
timestamp, outcome), and export only the visible scoped graph/timeline — never
shipped silently without that audit trail.

## Data Model

No finance ledger schema change is expected for the read-only MVP. Permission
configuration changes are expected for the two new workspace permissions.

Implementation must verify the current schema before coding resolver rules:

- `dng_payment_requests.id` is an integer primary key in current migrations.
- `dng_payment_requests.finance_charge_id` is nullable and not the only charge
  linkage.
- `dng_payment_request_charges` is the multi-charge allocation/link table.
- `finance_charge_installments.dng_payment_request_id` and
  `dng_payment_request_charges.finance_charge_installment_id` can both matter for
  installment audit.

If export audit logging or per-link audit records require new storage, pause for
a separate data-model decision instead of widening this story.

## UI / Platform Impact

Menu:

- Add `Audit Workspace` as the primary Finance Office entry.
- Keep Charges, Invoices, Payments, and DNG pages as specialist deep-dive pages.

Workspace UI:

- Universal search at the top.
- Result resolver state for no match, one match, and ambiguous matches.
- Subject header with student/campus/semester context.
- Graph summary showing current invoice/payment/DNG relationships.
- Ledger timeline showing signed ledger events, applications, reversals,
  discount allocations/releases, DNG request links, and payment receipts.
- Derived balance panel with cache-vs-derived comparison.
- Warning panel for drift and broken lifecycle links.
- Lightweight actions: copy authenticated link, refresh, export if permitted,
  and open source page.

The UI must follow Swinx frontend rules: Vue 3 `<script setup lang="ts">`,
Inertia v3 snake_case props, route helpers, existing `@/components/ui`
primitives, lucide icons, and no raw URL construction.

## Observability

- Export attempts must be auditable with user id, target, scope, timestamp, and
  result.
- Permission-denied and not-found results should not leak whether a hidden
  finance record exists outside the user's campus.
- Browser smoke should check that shared links rerun access control.
- Runtime logs should not include full DNG payloads, CCCD, or unnecessary PII.

## Alternatives Considered

1. Entity-stage timeline.
   - Rejected because payments, DNG requests, discounts, and invoice lines are
     graph-shaped. A linear chain would hide fan-out and reversals.
2. Add more links to the existing detail pages only.
   - Kept in `FIN-REV-007`, but not enough to solve the "too many pages" audit
     problem.
3. Full action workspace.
   - Rejected for MVP because money-changing actions already have source-page
     permissions, confirmations, and domain rules. The audit workspace should not
     become a parallel command surface.
