# Design

## Domain Model

No new Finance model. The shell reuses:

- `Student`
- `GetStudentBalanceQuery`
- `GetFinanceAuditGraphQuery`
- `FinanceLedgerTimelineBuilder`
- lifecycle exception reason resolver

## Application Flow

1. User opens `GET /finance/students/{student}`.
2. Route middleware requires `view_finance_student_overview`.
3. Controller checks current campus, unless the user has
   `view_finance_all_campus`.
4. Controller returns an Inertia page with identity, balances, links, focus, and
   deferred ledger.

## Interface Contract

Route:

- `GET /finance/students/{student}`
- Name: `finance.students.overview`
- Query: `focus` must match
  `dng|invoice|charge|payment|installment:<numeric-id>`
- Page: `Finance/Student360/Show`

Props:

- `student`
- `balances`
- `focus`
- `links`
- deferred `ledger`

## Data Model

No schema changes.

## UI / Platform Impact

New Inertia page under the existing admin/staff app shell. Uses Inertia v3
deferred prop rules.

## Observability

No audit record because this is read-only. Feature tests prove permission and
campus scope.

## Alternatives Considered

1. Send search users to Audit Workspace.
   - Rejected because design requires Student 360 as the student-centered axis.
2. Build full Student 360 immediately.
   - Rejected for Milestone 1 because full 360 contains additional action and
     destructive-flow patterns.
