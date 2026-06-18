# FIN-REV-012 — Finance Fee Tracking and Reporting Requirements

## Status

implemented

## Lane

normal

## Product Contract

This story is a requirements holder for the new Finance UI surfaces that answer
the operator questions around fee tracking, statistics, and drilldown-ready data.

The goal is to clarify the problem before choosing pages or implementation
shape. This story must capture what Finance staff, leads, and reporting users
need to see, compare, filter, and reconcile inside the new Finance Office UI.

This story does not implement the final UI. It should produce a signed-off
requirement matrix and a recommended page list that can be split into later
implementation stories.

## Relevant Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/stories/E-finance-module-review-2026-06/README.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/`
- `docs/stories/E-finance-module-review-2026-06/S-010-cockpit/`
- `docs/stories/E-finance-module-review-2026-06/S-010-lookup-and-audit/`
- `docs/stories/E-finance-module-review-2026-06/S-008-bod-finance-oversight/`
- `docs/stories/E-finance-module-review-2026-06/S-009-finance-audit-workspace/`

## Portal Impact

none

This is an admin/staff Finance Office requirement story. It must not change
student or lecturer portal API contracts unless a later implementation story
explicitly expands scope.

## Current Starting Point

The new Finance UI now has task-first surfaces:

- Cockpit "Hôm nay" for daily triage.
- Student 360 for one-student financial context.
- Batch Studio for bulk work.
- Lookup & Audit for charge, invoice, payment, and audit investigation.
- A separate legacy menu group that keeps old object-first flows reachable
  during transition.

The remaining product gap is reporting clarity:

- Staff need to track which fees exist, which fees are missing, which are due,
  and which have already been collected.
- Leads need statistics by semester, account campus, program, intake, fee type,
  and status.
- Operators need trusted drilldowns from summary counts to the exact affected
  students, fees, DNG requests, payments, and lifecycle evidence.

## Confirmed Role Scope

The reporting surface must be designed for all four role groups from the start,
not as a single-role staff screen that later grows awkwardly:

| Role                        | Primary need                                                                           | Product implication                                                                                     |
| --------------------------- | -------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Finance staff               | Daily operational tracking: missing fees, due fees, unpaid students, follow-up queues. | Needs actionable worklists, drilldowns to Student 360, and visible filter/state context.                |
| Finance lead / head         | Progress and quality control by semester/account campus/program/intake/fee type.       | Needs aggregate statistics, comparisons, exception counts, and drilldown from summary to affected rows. |
| Accountant / reconciliation | Trusted line-level data for reconciliation with DNG, bank, invoices, and allocations.  | Needs stable line-level evidence, source lineage, status fields, and audit drilldowns.                  |
| BOD / management            | High-level financial health and collection progress.                                   | Needs concise summary dashboards and trend signals, separated from detailed operator/audit surfaces.    |

The initial architecture should define the full reporting map, even if
implementation is split into later stories. Later slices can build one page or
one role lens at a time without redesigning the reporting model.

## Confirmed Reporting Lens Scope

The new Finance reporting UI must cover all three fee-tracking lenses. They must
be visually and conceptually separated so operators can answer one question at a
time without losing the connection to the same student, semester, charge,
invoice, DNG request, payment, and allocation data.

| Lens                  | Core question                                                          | Primary users                            | Typical action                                                                        |
| --------------------- | ---------------------------------------------------------------------- | ---------------------------------------- | ------------------------------------------------------------------------------------- |
| Fee completeness      | Have the expected fees been generated correctly?                       | Finance staff, Finance lead              | Identify missing fees, inspect skipped/voided/blocked rows, hand off to Batch Studio. |
| Collection progress   | How much has been collected, and what is still outstanding?            | Finance staff, Finance lead, BOD         | Follow up unpaid/overdue balances, inspect aging, drill into debt lists.              |
| DNG/payment lifecycle | Where are payment requests, payments, webhooks, and allocations stuck? | Finance staff, accountant/reconciliation | Identify stuck states, drill to Lookup & Audit, and inspect evidence.                 |

This does not require three disconnected modules. The preferred requirement is a
single coherent reporting area with clearly separated lenses/tabs/views and
shared drilldowns into Student 360, Lookup, and Audit.

## Confirmed Reporting Grain and Semester Scope

The reporting lenses use different primary row grains because each lens answers
a different operational question:

| Lens                  | Primary row grain                        | Semester scope                                                                                                                                       | Why                                                                                                                                                 |
| --------------------- | ---------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Fee completeness      | `student × expected_fee_type × semester` | Must obey the global semester selected in `resources/js/components/finance/SemesterSwitcher.vue`.                                                    | Missing expected fees have no `finance_charge` row yet, so the row cannot be charge-based.                                                          |
| Collection progress   | `student × semester_balance`             | Must obey the global semester selected in `resources/js/components/finance/SemesterSwitcher.vue`.                                                    | Staff and leads follow up debt by student in the selected semester, then drill into invoices, charges, payments, and allocations.                   |
| DNG/payment lifecycle | `DNG/payment request`                    | Must not hard-filter by the global semester. Every row/detail must show the request's related semester, while the selected semester is context only. | Stuck payment, webhook, invoice, and allocation states may be urgent even when they belong to a different semester than the current topbar context. |

The two semester-bound lenses must use the shared Finance semester context
instead of adding a local semester filter. Current implementation surface:

- `SemesterSwitcher.vue` reads `page.props.semester.selected_id`.
- `useFinanceSemester()` exposes `selectedId`, `selectedLabel`, and `labelFor`.
- `FinanceSemesterContextController` stores `current_semester_id` in session.
- `HandleInertiaRequests` reflects the selected semester through the shared
  `semester` Inertia prop for Finance users.

For DNG/payment lifecycle, the global semester switcher must not hide rows. The
UI must instead surface the semester lineage for each request:

- Request creation time from `dng_payment_requests.created_at`.
- Request due date from `dng_payment_requests.due_date` when available.
- Primary related semester from `dng_payment_requests.semester_id` when present.
- Fallback linked-charge lineage from
  `dng_payment_request_charges.finance_charge_id -> finance_charges.semester_id`
  for aggregate or charge-backed requests.
- Multi-semester indicator when linked charges span more than one semester.
- "Unknown semester" state when no semester can be resolved; the row must remain
  visible.

The selected semester may be shown as a contextual hint, such as "outside
selected semester", but it must not be an implicit filter for DNG/payment
lifecycle worklists.

## Confirmed Expected Fee Sources

Fee completeness can only mark a row as "expected" or "missing" when the fee
belongs to a confirmed expected-fee source. The first-pass expected fee sources
are:

- Tuition plan fees.
- EGC tuition fees.
- Course retake fees backed by an Academic course-retake registration.
- Exam retake fees backed by an Academic exam-resit attempt/decision.
- Admission/enrollment fees.
- BHYT health insurance fees.

Other ad-hoc/manual charges may still appear in collection and lifecycle
drilldowns after they exist as finance records, but they must not be counted as
"missing expected fees" until a later story defines their expectation rule.

## Confirmed Academic Retake/Resit Source Rules

Course retake (`học lại`) and exam retake (`thi lại`) must remain separate
Academic source rules before they become Finance expected fees.

Implementation dependency: Finance Reporting must wait for
`ACAD-RET-001-retake-resit-operations` before treating course retake or exam
resit as missing expected-fee sources. Reporting may show existing charges, but
it must not infer missing `retake_fee` or `exam_resit_fee` from failed grades
alone before Academic owns a queryable source/lifecycle.

Course retake means the student studies the course/unit again in a course
offering. The current code already has this source shape through
`CourseRetakeRegistration`, which is created from failed `academic_records` and
then produces `finance_charges.charge_type = retake_fee`.

Exam retake means the student retakes the whole course exam/outcome. It should
not be modeled as a normal `course_offerings` row by default, because current
course offerings are treated across the system as real study/enrollment units
with capacity, course registrations, class sessions, course completion,
academic-record generation, GPA/progression, survey, and Canvas side effects.
Using a normal course offering for exam resit would risk making "thi lại" look
like "học lại".

Exam retake must instead have a dedicated Academic source/audit object, such as
`exam_resit_attempts` or `AcademicExamResitAttempt`. The source should link to
the affected `academic_records` row, original course offering/unit/semester,
campus, syllabus template, attempt number, status, policy snapshot, fee state,
and schedule state. The resit result updates the final outcome on the relevant
`academic_records` row, including final score/pass state. Because that final row
is mutable, the attempt source remains the immutable/auditable history of each
resit attempt.

Exam retake can happen multiple times. The allowed number of attempts, fee per
attempt, eligibility rule, registration window, and payment deadline are
admin-configurable policy, not Finance Reporting logic. The default policy is
owned by the syllabus: one exam-resit attempt is allowed by default from the
course/unit syllabus, and Academic/Admin UI may configure more attempts when the
syllabus/policy allows it. Each created resit attempt must snapshot the policy it
was created under so future syllabus changes do not rewrite historical
entitlement or fee expectations.

Exam retake also needs a student-visible schedule. The preferred implementation
shape is a dedicated resit session/timetable source, or a targeted timetable
event linked to the resit attempt, so only assigned students see the exam-resit
schedule. The schedule should not require creating a normal course offering only
to make the date appear in the student timetable. If the implementation reuses
existing campus `events`, the student timetable query must filter by participant
or explicit target/source linkage rather than showing a generic campus-wide
event to everyone.

Finance Reporting must not infer expected exam-retake fees from every failed
`academic_records` row. It should count `exam_resit_fee` as expected only after
Academic has approved or scheduled a concrete exam-resit attempt/decision. Until
that source object exists, Reporting may monitor existing `exam_resit_fee`
charges, but it must not show "missing exam-retake fee" from raw failed-grade
data alone.

## Confirmed Filter Scope

Filters are part of the reporting contract, not just table decoration. Every
reporting lens must make its default scope visible, keep drilldown state
consistent, and avoid hidden filters that silently change totals.

Campus is not a user-facing filter on this reporting page. All lenses must be
scoped to the campus of the authenticated account/current session. The UI may
display the active campus as context, but it must not render a campus selector or
allow users to override campus from this page.

### Fee Completeness Filters

Fee completeness answers whether expected fees exist for the selected semester.
It is scoped by the global Finance semester context and should not add a second
local semester selector.

Always-visible filters:

- Program.
- Intake.
- Class/cohort.
- Expected fee type.
- Generation state: missing, generated, skipped, voided, blocked.
- Student status.
- Student search by code/name.

Advanced/drilldown-supporting filters:

- Expected source/rule: tuition plan, EGC tuition, course retake, exam retake,
  admission/enrollment, or BHYT health insurance.
- Charge lifecycle state when a charge exists.
- Has DNG request, has invoice, has payment, or has allocation.
- Missing/skipped/blocked reason.
- Amount range for expected or generated charge amount.

### Collection Progress Filters

Collection progress answers how much of the selected semester has been billed,
paid, and left outstanding. It is also scoped by the global Finance semester
context and should not add a second local semester selector.

Always-visible filters:

- Program.
- Intake.
- Class/cohort.
- Fee type.
- Balance state: unpaid, partially paid, paid, overdue, overpaid, or unapplied.
- Aging/due bucket.
- Student status.
- Student search by code/name.

Advanced/drilldown-supporting filters:

- Invoice status.
- Payment status.
- Has active DNG request.
- Due date range.
- Amount range for billed, paid, outstanding, or unapplied amount.
- Lifecycle exception flag.

### DNG/Payment Lifecycle Filters

DNG/payment lifecycle answers where requests, callbacks, payments, invoices, and
allocations are stuck. It must not be hard-filtered by the global Finance
semester context.

Always-visible filters:

- Created date range.
- DNG request status.
- Payment bridge status: has payment, no payment, or all.
- Webhook state: has webhook, no webhook, failed/invalid webhook, or all.
- Related semester as an explicit optional filter, not the topbar default.
- Student search by code/name, DNG payment id, DNG transaction id, or item id.

Advanced/drilldown-supporting filters:

- Paid date range.
- Invoice status.
- Allocation/reconciliation state.
- Cancel/retry/error state.
- Fee type.
- Amount range.
- Outside-selected-semester flag for users who need to focus on requests whose
  related semester differs from the topbar context.

For DNG/payment lifecycle drilldowns, rows must include both request creation
time and related semester lineage so the evidence explains when the request was
created and which semester it belongs to.

## Confirmed Page Structure

The first-pass UI should be one Finance Reporting menu entry/page with three
clearly separated views:

1. `Fee Monitor`
2. `Collection Progress`
3. `DNG/Payment Lifecycle`

This is a shared reporting shell, not one mixed table. Each view keeps its own
query, statistics, filters, table shape, empty state, and loading state. The
shared shell owns the page title, current account-campus context, Finance
semester context, freshness timestamp conventions, and drilldown navigation
rules.

The active view must survive reload, browser back/forward, and shared links. It
must be represented in navigation state, such as a query param or route segment,
instead of only local component state. Acceptable examples:

- `/finance/reporting?view=fee-monitor`
- `/finance/reporting?view=collection-progress`
- `/finance/reporting?view=dng-lifecycle`

The implementation can choose the final route naming, but it must preserve the
same behavior: reloading the page reopens the active view.

For a first visit with no active view in URL/navigation state, the default view
is `Collection Progress`.

## Confirmed Export Placement

This reporting page must not include export actions in the first-pass UI. The
page is for monitoring, statistics, filtering, and drilldown.

Export requirements are deferred to a separate future surface/story, likely an
Export Center or saved report flow, after the monitoring/statistics page is
stable. The reporting page may still define stable filter and data lineage
contracts so a future export surface can reuse them, but users should not see
"Export" as an action on this page.

## Confirmed Statistics Freshness

Statistics on this reporting page must be operational near-realtime. The page is
for Finance staff and leads making same-day follow-up decisions, not for
end-of-day snapshots or period-close/accounting reports.

Operational near-realtime means:

- Statistics should be computed from current source-of-truth data on initial
  page load and filter changes.
- Every statistics block must expose a visible `last updated` or `computed at`
  timestamp.
- Statistics must preserve the active filter context used by the current lens.
- Expensive statistics may load progressively, but stale or still-loading values
  must be visually distinguishable from fresh values.
- Cached snapshot columns may be used only when the statistic explicitly labels
  itself as a snapshot/cache value or when the implementation proves the snapshot
  is refreshed from the canonical source before display.
- Period-close/accounting numbers are out of scope for this page and should move
  to a later dedicated reporting/accounting story if needed.

The intended user promise is: "If Finance is looking at this page during daily
work, the numbers are fresh enough to decide whom to follow up, where the
collection process is stuck, and which drilldown needs investigation now."

## Confirmed Money Source

Collection Progress must use canonical settlement/ledger-derived money truth for
Finance amounts:

- `billed`
- `paid`
- `outstanding`
- `overdue`
- `overpaid`
- `unapplied`

Implementation stories should map these terms to the existing canonical
settlement/ledger read model before building UI numbers. Cached snapshot columns
must not be treated as source of truth unless the statistic labels them as
snapshot/cache values or the implementation proves they are refreshed from the
canonical source before display.

## Confirmed DNG/Payment Attention (Stuck) Buckets

DNG/Payment Lifecycle should separate normal in-progress rows from rows that need
operator attention. The default attention queue should prioritize rows where the
payment/request lifecycle is failed, suspicious, or too old for normal waiting.

Rows requiring attention:

- `failed` DNG payment requests.
- Webhook events with `failed_retryable`, `failed_terminal`, `mismatch`,
  invalid checksum, or callback mismatch evidence.
- `paid_uninvoiced` DNG payment requests.
- `pending` DNG payment requests older than 60 minutes from `created_at`.
- `pushed_to_dng` DNG payment requests past `due_date`.

Normal in-progress rows by default:

- `pending` DNG payment requests up to 60 minutes old.
- `pushed_to_dng` DNG payment requests not past `due_date`.
- `paid_invoiced`, `reconciled`, `cancelled`, and `cancel_pushed_to_dng`
  terminal states unless an explicit lifecycle exception exists.

The view may still filter and search all statuses. Each attention row should
expose a reason label, such as `Failed`, `Webhook invalid`,
`Paid but not invoiced`, `Pending > 60m`, or `Past due without payment`.

## Confirmed Action Boundary

Finance Reporting is monitoring and drilldown only. It must not create, update,
void, cancel, retry, allocate, or reconcile Finance records directly.

When an operator needs to act on Reporting rows:

- Missing/blocked fee rows hand off to Batch Studio with selected context for
  batch generation or follow-up.
- Student-context investigation opens Student 360.
- Charge, invoice, payment, DNG request, graph, timeline, payload, or audit
  investigation opens Lookup & Audit.

The Reporting page may pass selected row ids, active lens, filters, semester,
and focus target into the destination, but the destination owns the actual
mutation or investigation workflow.

## Confirmed Reporting vs Lookup & Audit Boundary

Reporting owns aggregate, grouped, and worklist views. Lookup & Audit owns
object-level investigation.

Reporting should include:

- Summary cards, trend/status counts, grouped breakdowns, and worklists by the
  active lens and filter context.
- Enough row-level context to explain why a student/fee/request appears in the
  list.
- Drilldown links into Student 360 for student-context investigation.
- Drilldown links into Lookup & Audit for charge, invoice, payment, DNG request,
  ledger graph, timeline, payload, or audit-log investigation.
- Navigation context, such as selected lens, filters, row identifiers, and focus
  target, so the destination page can reopen the relevant subject.

Reporting should not include:

- Universal search across finance objects.
- Full money-flow graph, signed ledger timeline, raw DNG payloads, webhook event
  history, or audit-log history.
- A second object-detail screen for charge, invoice, payment, or DNG request.
- Void, cancel, retry, allocation, fee generation, or accounting close actions.
  These must hand off to Batch Studio, Student 360, or a later accepted action
  surface instead.

For DNG/payment lifecycle, the reporting page may list stuck requests, show
status/reason/date/semester context, and group by lifecycle state. The full
request history, callback payload, invoice/payment bridge details, and audit
timeline remain in Lookup & Audit.

## Requirement Areas To Clarify

### 1. Fee Tracking

Questions this area may need to answer:

- Which students have had fees generated for the selected semester within the
  authenticated account campus?
- Which confirmed expected-fee sources are missing: tuition plan, EGC tuition,
  course retake, exam retake, admission/enrollment, or BHYT health insurance?
- Which Academic course-retake registrations or exam-resit attempts exist but do
  not yet have the expected Finance charge?
- Which students are still missing expected fees?
- Which charge types are active, voided, discounted, installment-linked, or
  blocked by lifecycle state?
- Which generated fees have DNG requests, invoices, payments, allocations, or
  outstanding balances?
- Which rows need operator action today versus historical review only?

### 2. Statistics

Questions this area may need to answer:

- Total billed, paid, outstanding, overdue, and unapplied by selected semester
  within the authenticated account campus.
- Progress by fee type: tuition plan, EGC tuition, course retake, exam retake,
  admission/enrollment, BHYT health insurance, and existing ad-hoc/manual
  charges.
- Course-retake and exam-resit counts by Academic source state, charge state,
  payment state, and attempt number.
- Collection progress by program, intake, class, student status, or lifecycle
  status.
- DNG funnel statistics: created, pending, paid uninvoiced, paid invoiced,
  failed webhook, cancelled, expired.
- DNG/payment attention buckets: failed requests, failed/invalid/mismatched
  webhooks, paid without invoice, pending over 60 minutes, and pushed requests
  past due date.
- Exception statistics: lifecycle exceptions, billing exceptions, invariant
  findings, cache drift.

### 3. Deferred Export Data

Export is not part of this page. A later dedicated export story may need to
answer:

- Which exports are operational worklists versus accounting reports?
- Which exports must be line-level and which can be summary-level?
- Which filters must be preserved exactly in exports?
- Which columns are mandatory for reconciliation: student code, name, campus,
  program, semester, invoice, charge, DNG request, payment, allocation,
  outstanding, due date, status, lifecycle flags, and audit metadata.
- Which exports need permission gates, row limits, queued generation, or an
  audit log trail.

## Requirement Questions For Discussion

Use these questions to drive the next conversation before any page design is
locked:

1. Role scope: answered — all four role groups are in scope from the initial
   reporting architecture.
2. Fee tracking scope: answered — build all three lenses: fee completeness,
   collection progress, and DNG/payment lifecycle, with UI separation between
   them.
3. Reporting grain: answered for the first pass — fee completeness is
   `student × expected_fee_type × semester`; collection progress is
   `student × semester_balance`; DNG/payment lifecycle is `DNG/payment request`.
   The global semester applies only to fee completeness and collection progress.
   DNG/payment lifecycle rows remain visible across semesters and must display
   request creation time plus related semester lineage.
4. Filter scope: answered for the first pass — fee completeness and collection
   progress use global semester plus academic/finance filters; DNG/payment
   lifecycle uses created date, DNG status, payment bridge status, webhook
   state, related semester as an explicit optional filter, and lifecycle
   evidence filters.
5. Export placement: answered — no export action on this reporting page. Export
   belongs to a separate future surface/story after monitoring/statistics are
   stable.
6. Statistics freshness: answered — use operational near-realtime numbers for
   daily follow-up, with visible `last updated`/`computed at` timestamps;
   end-of-day snapshots and period-close/accounting reports are out of scope for
   this page.
7. Reporting vs Lookup & Audit boundary: answered — Reporting owns
   aggregate/worklist/grouped views and drilldown entry points; Lookup & Audit
   owns object-level investigation, graph/timeline, raw payloads, audit history,
   and detailed object pages.
8. Page structure: answered — create one Finance Reporting menu/page with three
   separated views: Fee Monitor, Collection Progress, and DNG/Payment Lifecycle.
   Campus is implicit from the authenticated account/current session, not a UI
   filter. Active view must be URL/navigation-state backed so reload preserves
   the selected tab/view.
9. Expected fee/action/money defaults: answered — expected fee sources are
   tuition plan, EGC tuition, course retake, exam retake, admission/enrollment,
   and BHYT health insurance; Reporting is monitoring and drilldown only; missing
   fee work hands off to Batch Studio; Collection Progress uses canonical
   settlement/ledger money truth; first visit defaults to Collection Progress.
10. DNG/payment lifecycle stuck buckets: answered — attention rows are `failed`
    requests, failed/invalid/mismatched webhooks, `paid_uninvoiced`, `pending`
    older than 60 minutes, and `pushed_to_dng` past `due_date`; `pending` within
    60 minutes and `pushed_to_dng` before due date are normal in-progress rows by
    default.
11. Course retake/exam retake boundary: answered — `học lại` is course retake
    and uses an Academic course-retake registration; `thi lại` is whole-course
    exam/outcome resit, updates the final `academic_records` result, uses a
    dedicated Academic resit-attempt source rather than a normal course offering,
    defaults to one allowed resit attempt from syllabus policy, may allow more
    attempts by admin-configured policy, needs student-visible targeted schedule
    tracking, and should produce expected `exam_resit_fee` only from an
    approved/scheduled Academic resit attempt/decision rather than from every
    failed record.

## Recommended Page Structure

This is the accepted first-pass page structure for later implementation stories:

| Surface                    | Purpose                                                                                       | Product note                                                                                                  |
| -------------------------- | --------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Finance Reporting shell    | One menu/page that owns account-campus context, active view persistence, freshness, and tabs. | Prevents three disconnected reporting modules while keeping each view separate.                               |
| Fee Monitor view           | Operational tracking of expected/generated/paid/outstanding fees by student and fee type.     | Semester-bound by global Finance semester; campus-bound by account/session; actions hand off to Batch Studio. |
| Collection Progress view   | Summary and worklist view for billed/paid/outstanding, aging, DNG funnel, and exceptions.     | Semester-bound by global Finance semester; campus-bound by account/session.                                   |
| DNG/Payment Lifecycle view | Worklist for stuck DNG requests, payment bridge, webhook, invoice, and allocation states.     | Not hard-filtered by global semester; campus-bound by account/session; related semester is displayed.         |
| Lookup & Audit drilldowns  | Destination for object-level investigation opened from Reporting rows.                        | Required as a navigation target, not a replacement for a dedicated Reporting page.                            |
| Export Center              | Separate future export surface with saved definitions, columns, permissions, and history.     | Deferred from this page; useful later if Finance needs official/scheduled/exported reports.                   |

## Implementation Story Split

This requirement holder is complete when the reporting contract is stable and
the build work is split into separate Harness stories. Product implementation
must continue through these stories instead of adding runtime code directly to
`FIN-REV-012`.

| Story id                                               | Packet                                             | Scope                                                                                               | Key dependency                                                                                 |
| ------------------------------------------------------ | -------------------------------------------------- | --------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `FIN-REV-016-finance-reporting-shell`                  | `S-016-finance-reporting-shell/`                  | Finance Reporting route, permission, sidebar entry, shared shell, URL-backed active view state.      | `FIN-REV-012`, `FIN-REV-010-cutover-and-uat`                                                    |
| `FIN-REV-017-finance-reporting-fee-monitor`            | `S-017-finance-reporting-fee-monitor/`            | Fee Monitor view for expected/generated/missing fee completeness by student and expected fee type.   | `FIN-REV-016`; `ACAD-RET-001` before missing `retake_fee` or `exam_resit_fee` completeness     |
| `FIN-REV-018-finance-reporting-collection-progress`    | `S-018-finance-reporting-collection-progress/`    | Collection Progress view for billed, paid, outstanding, overdue, overpaid, and unapplied balances.  | `FIN-REV-016`, canonical settlement/ledger read model                                           |
| `FIN-REV-019-finance-reporting-dng-lifecycle`          | `S-019-finance-reporting-dng-lifecycle/`          | DNG/Payment Lifecycle attention worklist with webhook, payment bridge, invoice, and semester lineage. | `FIN-REV-016`, DNG request/webhook audit model                                                  |

The Export Center remains a deferred future story. It should not be registered
or built until Finance confirms official export definitions, columns,
permissions, row limits, queueing, and audit requirements.

## Acceptance Criteria

- The story records a clear user-role matrix for fee tracking and statistics
  needs.
- The story records the agreed reporting grains and required filters.
- The story records that campus is implicit from the authenticated
  account/current session and is not a reporting-page filter.
- The story records the confirmed expected-fee sources for Fee Monitor.
- The story records the Academic source boundary for course retake versus exam
  retake fees.
- The story records that exam retake is not modeled as a normal course offering
  by default; it uses a dedicated Academic resit-attempt source with syllabus
  policy defaults, policy snapshots, targeted schedule tracking, and final
  `academic_records` update.
- The story records which filters are always visible versus advanced/drilldown
  supporting for each reporting lens.
- The story records that DNG/payment lifecycle is not hard-filtered by the
  selected semester and must expose request creation time plus related semester
  lineage per row/detail.
- The story records the DNG/payment attention buckets that distinguish stuck
  rows from normal in-progress lifecycle rows.
- The story records that export actions are out of scope for this reporting page
  and should move to a separate future surface/story.
- The story records that statistics are operational near-realtime, must expose
  freshness timestamps, and are not period-close/accounting reports.
- The story records that Reporting is not a duplicate Audit Workspace: grouped
  monitoring stays in Reporting, while object investigation stays in Lookup &
  Audit.
- The story records that Reporting is monitoring/drilldown only and action work
  hands off to Batch Studio, Student 360, or Lookup & Audit.
- The story records that Collection Progress money uses canonical
  settlement/ledger truth.
- The story records the accepted page structure and active-view persistence
  behavior.
- The story records source-of-truth lineage for any proposed statistic before
  implementation begins.
- The story separates operator reporting from `FIN-REV-008` BOD oversight and
  from `FIN-REV-009` audit investigation.
- The story ends with a recommended page list for the new Finance UI and the
  implementation stories needed to build those pages.

## Design Notes

- Commands: none yet.
- Queries: to be mapped after the requirement matrix is approved.
- API: likely read-only Inertia/query endpoints for monitoring, statistics, and
  drilldown; export endpoints are deferred to a separate future story.
- Campus scope: derive from the authenticated account/current session for every
  reporting query; do not expose a campus filter on this page.
- Expected-fee sources: tuition plan, EGC tuition, course retake, exam retake,
  admission/enrollment, and BHYT health insurance.
- Academic retake/resit sources: course retake uses an Academic course-retake
  registration; exam retake uses an Academic whole-course exam-resit
  attempt/decision and may update the final `academic_records` result while
  preserving attempt history separately.
- Dependency: retake/resit missing-fee implementation waits for
  `ACAD-RET-001-retake-resit-operations`.
- Money source: Collection Progress numbers derive from canonical
  settlement/ledger truth.
- Action boundary: Reporting does not mutate Finance data; it passes context to
  Batch Studio, Student 360, or Lookup & Audit.
- Tables: existing Finance charge, invoice, payment, DNG, allocation, student,
  semester, campus, and lifecycle data sources must be traced before building
  metrics.
- DNG semester lineage: prefer `dng_payment_requests.semester_id`; fall back to
  `dng_payment_request_charges.finance_charge_id -> finance_charges.semester_id`
  when the request is charge-backed or aggregate.
- DNG attention buckets: default attention worklist includes failed requests,
  failed/invalid/mismatched webhooks, `paid_uninvoiced`, `pending` older than 60
  minutes, and `pushed_to_dng` past `due_date`.
- Domain rules: do not derive Finance totals from stale cache columns unless the
  statistic explicitly says it is using a cache snapshot; prefer existing
  settlement/ledger read models where available.
- Statistics freshness: operational near-realtime for daily follow-up, with
  visible computed-at timestamps and progressive loading allowed for expensive
  blocks.
- Boundary: Reporting links to Student 360 and Lookup & Audit for investigation;
  it does not own universal search, full ledger graph/timeline, raw payloads, or
  object detail pages.
- UI surfaces: one Finance Reporting shell with Fee Monitor, Collection
  Progress, and DNG/Payment Lifecycle views; active view must persist across
  reload via URL/navigation state. Default first-visit view is Collection
  Progress.

## Validation

| Layer       | Expected proof                                                                                                         |
| ----------- | ---------------------------------------------------------------------------------------------------------------------- |
| Unit        | Requirement-only story; no unit tests until implementation stories exist.                                              |
| Integration | Future implementation must prove statistics and drilldowns use documented source-of-truth queries.                     |
| E2E         | Future implementation must prove staff can filter, inspect, and drill into Student 360/Lookup from the accepted pages. |
| Platform    | Story/docs formatting and Harness registration.                                                                        |
| Release     | Requirement sign-off plus split implementation stories before product code starts.                                     |

## Harness Delta

- Registered this story as the dedicated requirement holder for Finance fee
  tracking, statistics, and drilldown boundaries.
- Split later implementation into `FIN-REV-016` through `FIN-REV-019` so the
  requirement conversation stays product-first and each build slice can carry
  its own query, UI, and validation proof.

## Evidence

Requirement holder closed on 2026-06-18 with the implementation story split
above. No runtime code was changed in this story. Confirmed on 2026-06-16:
DNG/payment
lifecycle worklists are not hard-filtered by the global semester, but every
request row/detail must show creation time and related semester lineage.
Confirmed on 2026-06-16: first-pass filter scope follows the recommended split
between semester-bound fee/collection lenses and non-semester-bound DNG/payment
lifecycle worklists.
Confirmed on 2026-06-16: no export action belongs on this reporting page; export
is deferred to a separate future surface/story.
Confirmed on 2026-06-16: statistics on this page are operational
near-realtime, not end-of-day snapshots or period-close/accounting reports.
Confirmed on 2026-06-16: Reporting is for aggregate/worklist/grouped monitoring
and drilldown entry points; Lookup & Audit remains the object-level
investigation destination.
Confirmed on 2026-06-16: Finance Reporting is one menu/page with three separated
views, no campus UI filter, campus scope from the authenticated account/current
session, and active view persistence across reload.
Confirmed on 2026-06-16: expected fee sources are tuition plan, EGC tuition,
course retake, exam retake, admission/enrollment, and BHYT health insurance.
Confirmed on 2026-06-16: Reporting is monitoring/drilldown only; missing-fee
action work hands off to Batch Studio.
Confirmed on 2026-06-16: Collection Progress uses canonical settlement/ledger
money truth, and the first-visit default view is Collection Progress.
Confirmed on 2026-06-16: DNG/payment attention buckets are failed requests,
failed/invalid/mismatched webhooks, `paid_uninvoiced`, `pending` older than 60
minutes, and `pushed_to_dng` past `due_date`; normal in-progress rows are
`pending` within 60 minutes and `pushed_to_dng` before due date.
Confirmed on 2026-06-16: `thi lại` means a whole-course exam/outcome resit that
updates the final `academic_records` result, can happen multiple times according
to admin-configured policy, and should become an expected `exam_resit_fee` only
from an approved/scheduled Academic resit attempt/decision.
Confirmed on 2026-06-16: exam retake should not be modeled as a normal
`course_offerings` row by default. `học lại` remains the real course-offering
flow; `thi lại` needs a dedicated Academic resit-attempt source, syllabus-owned
default of one allowed attempt, policy snapshot, targeted student-visible
schedule, and Finance charge linkage from the approved/scheduled attempt.
Confirmed on 2026-06-16: create and prioritize
`ACAD-RET-001-retake-resit-operations` before continuing Finance Reporting for
retake/resit expected-fee completeness. Academic staff creates and approves;
staff-created records auto-approve, while future student-created requests must
wait for staff approval or rejection before any fee is generated.
