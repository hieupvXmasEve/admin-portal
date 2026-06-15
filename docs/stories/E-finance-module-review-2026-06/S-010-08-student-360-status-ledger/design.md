# Design

## Domain Model

This story introduces read-only Student 360 view models:

- `status_cards.balance`: current balance, unapplied credit,
  `has_unapplied`, and `unapplied_payment_id` when a completed payment still has
  unapplied amount.
- `status_cards.dng`: the latest/active DNG request summary.
- `status_cards.installments`: installment counts and next payable/pushable
  installment.
- `status_cards.exception`: DNG/lifecycle blocking reasons for operator review.
- `ledger_groups`: invoices grouped by semester, with line-level outstanding
  amounts.
- `actions`: permission-derived booleans for later UI rendering.

Money truth must stay in existing services:

- `GetStudentBalanceQuery`
- `SettlementService`
- DNG request models/actions
- existing installment and lifecycle read helpers

## Application Flow

1. User opens `GET /finance/students/{student}`.
2. `Student360ShowRequest` keeps the Milestone 1 permission/campus boundary.
3. `FinanceStudentOverviewController` resolves the existing M1 props.
4. The controller calls `GetStudent360StatusCardsQuery` for cheap card data.
5. The controller exposes `ledger_groups` with `Inertia::defer(fn () => ...)`.
6. The controller exposes `actions` from current user permissions.

The grouped ledger is a second lens beside the existing M1 timeline. It must not
replace the audit graph/timeline builder.

## Interface Contract

Route:

- `GET /finance/students/{student}` named `finance.students.overview`

Additive Inertia props:

- `status_cards.balance.balance`
- `status_cards.balance.unapplied_credit`
- `status_cards.balance.has_unapplied`
- `status_cards.balance.unapplied_payment_id`
- `status_cards.dng.has_active`
- `status_cards.dng.request`
- `status_cards.installments.total`
- `status_cards.installments.paid`
- `status_cards.installments.next`
- `status_cards.exception.needs_review`
- `status_cards.exception.dng_request_id`
- `status_cards.exception.blocking_reasons`
- `actions.can_record_payment`
- `actions.can_allocate`
- `actions.can_cancel_dng`
- `actions.can_void_charges`
- `ledger_groups`

Inertia v3 requirements:

- Use `Inertia::defer(fn () => ...)` for `ledger_groups`.
- Preserve snake_case prop names in PHP and TypeScript.
- Do not use removed v2 APIs such as `Inertia::lazy()`.

## Data Model

No schema changes.

Read paths may use existing tables only:

- `student_invoices`
- `invoice_lines`
- `payment_applications`
- `payments`
- `dng_payment_requests`
- `finance_charge_installments`
- `finance_charges`

## UI / Platform Impact

This story only supplies props for the admin/staff Inertia page. Rendering the
cards, ledger tabs, state stepper, menu, and drawers belongs to later child
stories.

## Observability

No money state changes occur, so no `finance:audit-invariants` evidence is
required for this story. Proof is the feature test asserting props, permission
flags, campus behavior inherited from M1, and absence of new money-write paths.

## Alternatives Considered

1. Build the card data directly in Vue.
   - Rejected because Vue must not compute money truth.
2. Reuse the audit graph for every card.
   - Rejected because the graph is heavier and the status cards can delegate to
     narrower existing read services.
3. Return only an unapplied total.
   - Rejected because the later allocation drawer needs a real payment id and
     must not infer it from a DNG request.
