# Design

## Domain Model

Define two separate operational concepts:

- Active due item: an open DNG payment request that belongs in normal reminder
  operations. The linked student must be in `Student::FINANCIAL_STATUSES`
  (`intake_pre_uni_gc`, `intake_course`, `intake_major`).
- Lifecycle due exception: an open DNG payment request that has a due date but
  whose linked student is outside normal finance collection status.

Minimum exception statuses:

- `deferred`: requires defer policy review before reminders or cancellation.
- `dropout`: requires final settlement/offboarding debt review.
- `dropout_transfer`: requires transfer settlement review before any write-off
  or recovery decision.
- `inactive_or_non_financial`: catches `inactive`, `graduated`,
  `admission_deferred`, `pending`, `pending_course_opening`, and other statuses
  outside `Student::FINANCIAL_STATUSES`.
- `missing_student`: DNG request has no valid linked student and needs data
  repair.

Recommended resolution states:

- `open`: detected and waiting for staff review.
- `acknowledged`: reviewed but intentionally left open, with reason.
- `kept_as_debt`: staff confirmed the amount should remain collectible outside
  normal reminder flow.
- `routed_to_settlement`: staff decided the item must be handled through
  settlement/charge workflow.
- `cancel_requested`: cancellation was attempted or queued but not yet proven.
- `resolved`: the underlying DNG/charge state no longer belongs in the
  exception queue.
- `ignored`: staff intentionally suppresses this derived exception, with reason.

## Application Flow

Queries:

- Add `ListLifecycleDueExceptionsQuery` under
  `app/Modules/Finance/Queries/Operations/`.
- Add `GetLifecycleDueExceptionSummaryQuery` for summary cards that are scoped to
  the same filters as the list, except for the active tab filter.
- Extract or share the active-due predicate used by Due Calendar and reminder
  actions so list, summary, export, and sends agree on the same lifecycle rule.

Controllers and actions:

- Prefer a dedicated `LifecycleDueExceptionController` under
  `app/Modules/Finance/Http/Web/Admin/` instead of expanding
  `BillingOperationsController` further.
- Add read-only page action for the list.
- Add an `AcknowledgeLifecycleDueExceptionAction` for non-destructive decisions.
- Add a `ResolveLifecycleDueExceptionAction` for destructive or state-changing
  decisions. It must validate permission, current DNG status, linked payment
  state, linked charges, and staff reason before mutation.
- Avoid duplicating acknowledge/review-state transition logic across
  `AcknowledgeLifecycleDueExceptionAction` and
  `ResolveLifecycleDueExceptionAction`; shared metadata and audit behavior must
  stay consistent (`FIN-24`).
- Reuse `CancelDngPaymentRequestAction` for DNG cancellation. Do not duplicate
  provider cancellation logic.
- Reuse `VoidFinanceChargeAction` when a linked charge must be voided. Do not
  update `finance_charges.status` or `invoice_lines.status` directly.
- Update student and parent reminder actions to skip lifecycle due exceptions
  even if a crafted request posts their item ids.

Resolution choices:

- `acknowledge`: store staff reason; no finance mutation.
- `keep_as_debt`: mark reviewed and keep DNG/charge open, but keep it out of
  generic reminder queue.
- `route_to_settlement`: mark reviewed and link staff to the student settlement
  or charge detail page; no automatic mutation.
- `cancel_dng`: cancel only the DNG request where status allows it and no paid
  bridge exists.
- `cancel_dng_and_void_linked_charge`: only after an impact preview lists every
  affected charge, invoice line, installment, and registration side effect.

## Interface Contract

Admin web routes:

- `GET /finance/operations/lifecycle-exceptions`
  - Route name: `finance.operations.lifecycle-exceptions`.
  - Middleware: `can:view_finance_operations_lifecycle_exceptions` or a
    documented reuse of `view_finance_operations_due_calendar` plus
    `view_finance_dng_payment_requests`.
- `POST /finance/operations/lifecycle-exceptions/{dngPaymentRequest}/acknowledge`
  - Requires view permission and staff reason.
- `POST /finance/operations/lifecycle-exceptions/{dngPaymentRequest}/resolve`
  - Requires the action-specific permission:
    - DNG cancellation: `create_finance_payments`.
    - Charge voiding: `void_finance_charges`.
    - Settlement routing/acknowledge only: view permission.

List filters:

- `semester_id`
- `lifecycle_status`
- `review_status`
- `due_status` (`upcoming`, `due_today`, `overdue`, `all`)
- `fee_type`
- `search` by student code, student name, DNG id, item id
- `per_page`, `page`, `sort`, `direction`

Row contract:

- DNG identity: id, item id, fee type, amount, status, due date, days overdue,
  last reminder time.
- Student identity: id, student code, full name, campus, lifecycle status,
  lifecycle status label/color.
- Finance context: linked `finance_charge_id`, charge type/status, invoice line
  status, paid/bridged payment status, balance summary when available.
- Academic context: latest defer/dropout action metadata when available,
  `defer_case` summary when available.
- Derived fields: `exception_reason`, `recommended_action`, `review_status`,
  `available_actions`, `blocking_reasons`.

## Data Model

Add a small review/audit table if the implementation needs persistent staff
state:

`finance_lifecycle_due_exception_reviews`

- `id`
- `dng_payment_request_id`
- `student_id`
- `exception_reason`
- `status`
- `resolution_action`
- `resolution_reason`
- `resolved_at`
- `resolved_by_user_id`
- `last_seen_at`
- `metadata` JSON
- timestamps

Indexes:

- Unique active review per `dng_payment_request_id` where practical, or
  application-level idempotency if the database cannot express the partial
  uniqueness.
- `status, exception_reason, last_seen_at` for queue filters.
- `student_id` and `dng_payment_request_id` for detail drill-down.

Do not store computed balances as source of truth. Payment and outstanding
amounts remain derived from `payment_applications`, `discount_allocations`,
`invoice_lines`, and DNG request/payment state.

## UI / Platform Impact

Add a Finance Operations menu entry near Due Calendar:

- Title: `Lifecycle Exceptions`
- Route: `/finance/operations/lifecycle-exceptions`
- Permission-gated like other Finance operations pages.

Page shape:

- Summary cards: Deferred, Dropout, Transfer, Other, Total amount under review.
- Filter panel using existing Finance/list page patterns and route helpers.
- Server-paginated table with stable columns:
  - DNG request
  - Student
  - Lifecycle status
  - Amount/due date
  - Reason
  - Review status
  - Recommended action
  - Actions
- Detail drawer with timeline and linked navigation to DNG request, student
  finance, settlement, charges, and student action history.
- Resolution modal requiring reason and showing impact preview for every
  destructive option.

## Observability

- Log summary counts for exception review actions: opened, acknowledged, kept as
  debt, routed, cancel attempted, cancel failed, resolved.
- Persist staff identity, reason, before/after review status, and target DNG
  request id for every resolution.
- Store DNG cancel payload/response through existing DNG request audit fields.
- Do not use application logs as the only audit for destructive resolution.

## Alternatives Considered

1. Add cancel buttons directly to Due Calendar.
   - Rejected because Due Calendar is a reminder queue. Cancellation and voiding
     require lifecycle-specific review and audit.
2. Hide deferred/dropout rows without any replacement page.
   - Rejected because finance still needs visibility into real outstanding
     records and DNG requests.
3. Auto-cancel all deferred/dropout DNG requests.
   - Rejected because some cases may remain legally collectible or require
     settlement, defer credit, or recovery handling.
4. Use only the DNG audit page.
   - Rejected because the DNG audit page is provider-centric and does not group
     or explain lifecycle exceptions as a finance operations workflow.
