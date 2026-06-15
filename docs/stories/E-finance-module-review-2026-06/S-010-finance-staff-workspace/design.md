# Design

## Domain Model

This story records the staff workspace direction plus the Milestone 1 foundation
from the implementation plan. Milestone 1 is intentionally read-only and
presentation/session oriented.

Working vocabulary:

- **Staff workspace**: a task-based Finance entry point for day-to-day staff
  operations.
- **Collection cycle**: a UI-level working context for MVP, represented by
  semester/campus plus a fee group or operational task. It is not a persisted
  entity in this story.
- **Task checklist**: the first workspace screen, showing operational tasks for
  the selected context.
- **Source page**: an existing technical page such as Charges, Invoices,
  Payments, DNG Requests, or Audit Workspace.

Known task groups:

- Proactive charge creation.
- DNG collection.
- Training-driven finance decisions.
- Payment follow-up and exceptions.
- Audit/search/deep-dive.

Milestone 1 foundation concepts:

- **Finance shell context**: global command search and selected semester shared
  through the app shell.
- **Student 360 shell**: read-only student destination for search, showing
  identity, lifecycle status, four derived balances, and a deferred basic ledger.
- **Finance route constants**: named route helpers used by Finance navigation.
- **Work-group IA**: five Finance Office groups plus the existing Discounts &
  Funding group kept separate.

## Application Flow

The desired staff flow is:

1. Staff opens Finance Staff Workspace.
2. Staff chooses semester and, where applicable, campus.
3. The workspace shows a checklist of collection-cycle tasks.
4. Staff opens a task drawer/modal from the checklist.
5. The drawer uses existing Finance backend logic to preview, confirm, and show
   result state.
6. Staff can open the related source page only when deeper investigation is
   needed.

Confirmed direction:

- The workspace is organized by what staff needs to do, not by database tables.
- The first screen is a checklist, not a raw table of charges/payments.
- Drawers/modals are preferred over redirecting to existing pages.
- Future implementation priority is:
  1. proactive charge creation,
  2. DNG collection,
  3. training-driven finance decisions.

## Interface Contract

Milestone 1 locks these route and prop contracts:

- `GET /finance/students/{student}` named `finance.students.overview`.
  - Permission: `view_finance_student_overview`.
  - Campus scope: current campus only unless the user has
    `view_finance_all_campus`.
  - Query: optional `focus=<type>:<id>` where type is
    `dng|invoice|charge|payment|installment`.
  - Inertia page: `Finance/Student360/Show`.
- `GET /finance/search` named `finance.search`.
  - Permission: `view_finance_student_overview`.
  - Response envelope: `ApiResponse::success()`.
  - Resolves student code/name, invoice number, and DNG identifiers through the
    existing audit resolver, then maps results to Student 360 URLs.
- `POST /finance/semester-context` named
  `finance.semester-context.update`.
  - Stores only `current_semester_id` in the operator session.
  - Reflected through the shared Inertia `semester` prop.
- Shared Inertia prop: `semester?: { selected_id: number|null, options: [...] }`
  for users with Finance shell permissions.
- Route helper surface: `FINANCE_ROUTE_NAMES` and `financeRoutes`.

Candidate future route for the broader staff workspace remains:

- `GET /finance/staff-workspace`

Likely inputs:

- `semester_id`
- `campus_id`
- `task`
- `fee_group`
- search/filter terms inside individual task drawers

Likely outputs:

- selected context
- task checklist counts and statuses
- permission-aware available actions
- drawer payloads for the selected task

Open questions:

- Should this page become the primary Finance Office menu entry, above Audit
  Workspace, or should it sit next to Audit Workspace?
- Which staff permissions should open the workspace?
- Which proactive fee types are official in MVP beyond BHYT?
- Should "books/materials" become a first-class enum value, or remain a manual
  fee description under `manual_fee`?
- Which existing pages are safe to embed first without duplicating too much UI
  logic?

## Data Model

No new data model is planned for Milestone 1.

Important constraints:

- No persisted collection-campaign table in the first staff-workspace slice.
- Do not introduce new ledger/money tables.
- Do not weaken existing Finance data guards or audit invariants.
- New permissions must be declared in `config/permission.php`, synced to the DB,
  and role-mapped before route-gated UI is treated as shippable.
- If later design proves that a persisted campaign is necessary, pause and create
  a separate data-model decision before implementation.

## UI / Platform Impact

Expected platform impact is admin/staff web UI only.

Milestone 1 UI impact:

- Topbar mounts global Finance command search and semester switcher for Finance
  users.
- Finance Office sidebar is reorganized into five work groups:
  Hôm nay, Sinh phí, Thu & Đối soát, Ngoại lệ, Tra cứu & Audit.
- Discounts & Funding remains a separate group because those routes live outside
  the Finance module.
- Student 360 is not a sidebar item; it is reached through global search and
  later through row clicks/deep links.

Broader desired IA:

- Finance Staff Workspace: primary operational entry.
- Finance Audit Workspace: read-only investigation/audit entry.
- Existing source pages remain available under a lower-level group for admin,
  audit, and repair work.

Desired UX:

- Staff sees work by semester/collection cycle.
- Each checklist item shows count, readiness, risk, and primary action.
- Drawer/modals let staff preview and confirm without losing context.
- Deep links to source pages remain available.

## Observability

Future write actions must preserve current audit and history behavior. Milestone
1 does not add Finance money writes, so it does not require new audit records or
`finance:audit-invariants` evidence.

For Milestone 1:

- Search, Student 360, and semester context are covered by feature tests.
- Browser smoke must prove the palette, switcher, sidebar IA, and 360 route are
  visible and navigable.
- If a later drawer performs a new write path, it must define audit evidence
  before implementation.

## Alternatives Considered

1. Link-only checklist.
   - Faster and safer, but it keeps staff moving between many pages and does not
     solve the primary usability problem.
2. Fully rewritten Finance workflow app.
   - Cleaner UX in theory, but too much scope and high risk of duplicating
     backend rules.
3. Staff workspace shell with embedded drawers.
   - Preferred direction. It keeps existing backend truth while creating a staff
     workflow layer over the current technical pages.
