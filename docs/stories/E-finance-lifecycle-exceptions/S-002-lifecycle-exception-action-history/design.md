# Design

## Domain Model

Keep two separate concepts:

- Current lifecycle exception review: the latest operational state used by the
  lifecycle exception list for filtering, badges, and available actions.
- Lifecycle exception history event: an immutable record of one staff or system
  action taken against the DNG payment request in the lifecycle exception
  workflow.

Minimum event types:

- `detected`: exception first became visible or was refreshed by the read model.
- `acknowledged`: staff reviewed the item without mutating finance records.
- `kept_as_debt`: staff confirmed the DNG request should remain collectible.
- `routed_to_settlement`: staff chose settlement or charge review as next step.
- `cancel_requested`: destructive DNG cancellation was attempted or queued.
- `cancel_succeeded`: DNG cancellation completed successfully.
- `cancel_failed`: DNG cancellation failed and the reason is captured.
- `void_requested`: linked charge voiding was attempted or queued.
- `void_succeeded`: linked charge voiding completed successfully.
- `void_failed`: linked charge voiding failed and the reason is captured.

History events must be append-only. If an action is retried, append another
event instead of updating or deleting the previous event.

## Application Flow

Queries:

- Add `ListLifecycleDueExceptionHistoryQuery` under
  `app/Modules/Finance/Queries/Operations/`.
- The query returns a paginated shared history list across all lifecycle
  exception actions, with each row including DNG request summary, student
  summary, current review state, and the history event.
- Add a focused detail/timeline query only if the shared page needs to expand a
  DNG request into its full event sequence.
- History lookup must work even when the DNG payment request no longer matches
  the active lifecycle exception list because it was cancelled, resolved, or
  otherwise changed after staff action.
- The shared query must support filtering by DNG payment request id, DNG item
  id, student, actor, event type, review status, exception reason, and performed
  date range.

Controllers and actions:

- Add a focused `LifecycleDueExceptionHistoryController` for the shared history
  index page. Keep `LifecycleDueExceptionController` focused on the active
  review queue.
- Update `AcknowledgeLifecycleDueExceptionAction` and
  `ResolveLifecycleDueExceptionAction` to append history events whenever they
  change review state, attempt DNG cancellation, or attempt charge voiding.
- For destructive operations, append the attempted event before calling the DNG
  or Finance mutation action, then append a success or failure event with the
  final outcome.
- Do not duplicate DNG cancellation or finance charge voiding logic. Keep using
  the existing dedicated actions from the owning Finance areas.

## Interface Contract

Admin web route:

- `GET /finance/operations/lifecycle-exception-history`
  - Route name: `finance.operations.lifecycle-exception-history`.
  - Middleware: same view permission as the lifecycle exception page, or a
    documented stricter permission if Finance audit history needs one.

Supported filters:

- `dng_payment_request_id`
- `dng_item_id`
- `student_id`
- `search` by student code, student name, DNG request id, or DNG item id
- `event_type`
- `review_status`
- `exception_reason`
- `performed_by_user_id`
- `performed_from`
- `performed_to`
- `page`
- `per_page`
- `sort`
- `direction`

Page props must use snake_case:

- `filters`: current filter values.
- `filter_options`: event type, review status, exception reason, actor, and
  date range options where practical.
- `history_events`: paginated entries with event id, event type, actor,
  performed_at, dng request summary, student summary, current review summary,
  from_status, to_status, resolution_action, resolution_reason,
  dng_status_before, dng_status_after, metadata, and failure summary when
  available.
- `selected_timeline`: optional full timeline for the DNG request selected by
  query param or row expansion.
- `links`: back to lifecycle exceptions, DNG payment request detail, student
  finance view, and settlement/charge view where available.

Lifecycle exceptions page:

- Add a `View history` row action in the table and equivalent action in the
  detail drawer.
- The action links to the shared history page with
  `dng_payment_request_id=<id>` or `search=<item_id>` applied, not to a route
  that only exists for active lifecycle exception rows.
- Use the named route helper instead of literal URL strings.

## Data Model

Add an append-only table, proposed as
`finance_lifecycle_due_exception_review_events`:

- `id`
- `finance_lifecycle_due_exception_review_id` nullable until the current review
  row is guaranteed to exist
- `dng_payment_request_id`
- `student_id`
- `event_type`
- `from_status`
- `to_status`
- `resolution_action`
- `resolution_reason`
- `performed_by_user_id`
- `performed_at`
- `dng_status_before`
- `dng_status_after`
- `metadata` JSON
- timestamps

Indexes:

- `dng_payment_request_id, performed_at`
- `finance_lifecycle_due_exception_review_id, performed_at`
- `student_id, performed_at`
- `event_type, performed_at`
- `performed_by_user_id, performed_at`

Data rules:

- Do not store computed balances as source of truth.
- Do not use application logs as the only history source.
- Do not overwrite history rows to correct an action. Append a correction or
  follow-up event.
- Keep DNG provider payload/response audit in the existing DNG surfaces; copy
  only workflow-relevant summaries into lifecycle history metadata.

## UI / Platform Impact

Add a new Inertia page:

- `resources/js/pages/Finance/Operations/LifecycleExceptionHistory.vue`.

Page shape:

- Finance Operations menu entry near Lifecycle Exceptions:
  `Lifecycle History`.
- Header with filters and summary counts for total events, failed actions,
  destructive attempts, and resolved/hidden exceptions when practical.
- Server-paginated history table ordered newest-first by default.
- Stable columns for performed time, action type, actor, student, DNG request,
  lifecycle reason, review status transition, DNG status transition, and reason.
- Row expansion or side panel for full event metadata and the full timeline for
  that DNG request.
- Links to DNG request detail, student finance, settlement, charge pages, and
  the active lifecycle exception page when the row still belongs there.
- Empty state for no matching history rows and a distinct legacy state for
  current reviews that predate event capture.

No student or lecturer portal changes are expected.

## Observability

- Persist event rows for every lifecycle exception action.
- Include actor, target DNG payment request id, from/to review state, action
  type, staff reason, and failure details when applicable.
- Keep application logs as secondary diagnostics only.
- Consider a trace/log entry for destructive action attempts, but the event
  table remains the user-facing audit source.

## Alternatives Considered

1. Render the current review record as "history".
   - Rejected because current review state can be overwritten and cannot show
     multiple actions, retries, or failures.
2. Use only DNG payment request audit.
   - Rejected because DNG audit is provider-centric and does not explain
     lifecycle review decisions.
3. Put the full history only inside the lifecycle exception drawer.
   - Rejected because processed rows leave the active exception page, so drawer
     access disappears exactly when staff later need audit history.
4. Use application logs as the source of truth.
   - Rejected because logs are not permissioned or shaped as a staff-facing
     operational audit trail.
5. Build only a per-DNG request history route.
   - Rejected as the primary surface because staff need a shared history index
     to find actions after the item is no longer visible in the active queue.
