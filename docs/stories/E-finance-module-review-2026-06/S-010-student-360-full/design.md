# Design

## Domain Model

Milestone 2 treats Student 360 as an operator surface over existing Finance
truth. It composes current read models, audit graph data, and tested write
actions into one student-centered page.

Important concepts:

- **Status cards**: read-only summaries derived from existing Finance data.
- **Ledger lens**: grouped ledger rows plus the existing signed timeline.
- **Action flags**: backend-computed booleans that decide which UI actions are
  visible or disabled.
- **Reviewed DNG cancel**: a destructive flow that first previews impact, shows
  blocking reasons, requires a reason, and records an acknowledgement.

## Application Flow

1. Staff lands on `/finance/students/{student}` from search or a deep link.
2. Page loads the identity header, four balance numbers, status cards, action
   flags, and deferred ledger data.
3. Staff reviews ledger/timeline and DNG state.
4. Staff opens the action menu only when permitted actions exist.
5. Drawers preview impact before write actions.
6. Write actions call existing services/actions and return to the same Student
   360 route.

## Interface Contract

Route and page contract:

- `GET /finance/students/{student}` named `finance.students.overview`
- Inertia page: `Finance/Student360/Show`
- Optional query: `focus=<type>:<id>`
- Permission: `view_finance_student_overview`
- Campus scope: current campus unless user has `view_finance_all_campus`

Additional M2 web contracts:

- `POST finance.students.payments.store`
- `GET finance.payments.allocate-preview`
- `POST finance.payments.allocate`
- `GET finance.dng.payment-requests.cancel-impact`
- `POST finance.dng.payment-requests.cancel-reviewed`
- `POST finance.charges.installments.retry-push`

Write adapters wrap existing behavior:

- `PaymentService::recordPayment`
- `AllocatePaymentAction`
- `CancelDngPaymentRequestAction`
- `PushNextInstallmentAction`

## Data Model

No new tables are introduced by this story.

The story may add read-model/query classes and request/controller adapters, but
it must not create a new money source of truth.

## UI / Platform Impact

Student 360 becomes a fuller working page:

- Sticky student header.
- Four status-card groups.
- DNG state stepper.
- Ledger/timeline lens switch.
- Permission-aware action menu.
- Sheets/drawers for write-adjacent operations.

The page remains an admin/staff web UI. It is not a student portal screen.

## Observability

Because M2 includes write-adjacent and write actions, final validation must
include:

- Backend feature tests for permission, campus scope, validation, and state
  changes.
- Finance invariant evidence before/after the M2 suite.
- Manual rendered-UI smoke for drawer/focus behaviors where no E2E harness
  exists.

## Alternatives Considered

1. Keep seven task-sized stories.
    - Useful during debugging, but too noisy for ongoing planning and review.
2. Build a new Finance workspace page first.
    - Rejected for M2 because global search already depends on the Student 360
      destination, and the design says M2 should augment that route.
