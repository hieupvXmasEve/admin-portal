# Overview

## Current Behavior

The Finance Office menu is functionally rich but organized around technical
surfaces: charges, invoices, payments, DNG requests, webhook events, generation
pages, exceptions, and audit pages. This matches how developers understand the
database and service boundaries, but it is difficult for staff who think in
operational work:

- Create fees for a collection cycle.
- Confirm fees that come from academic results.
- Send payment requests through DNG.
- Follow up overdue or exceptional cases.

Existing pages already cover many pieces of the job:

- Manual charge creation: `/finance/charges/create`.
- Bulk non-academic charges: `/finance/operations/generate-charges`.
- Major tuition generation: `/finance/major/charges`.
- EGC charge generation: `/finance/egc/charges`.
- DNG push: `/finance/operations/dng-worklist`.
- Billing exceptions: `/finance/operations/exceptions`.
- Lifecycle exceptions: `/finance/operations/lifecycle-exceptions`.
- EGC retake/carry-forward adjustments.
- Audit/traceability: `/finance/audit`.

The gap is not primarily missing backend capability. The gap is that staff must
know which technical page to open for each operational moment.

## Target Behavior

Create a separate Finance Staff Workspace story that captures the desired staff
workflow and the first implementation foundation without mixing it into S-009
Finance Audit Workspace.

This packet is the canonical Milestone 1 story for
`docs/superpowers/plans/2026-06-15-finance-office-shell-and-360-foundation.md`.
The former task-sized child stories are retained only as historical notes; the
milestone is planned, reviewed, and accepted as this one story.

| Plan task                                                | Included in this story                                                       |
| -------------------------------------------------------- | ---------------------------------------------------------------------------- |
| Task 1: Finance route constants                          | route-name constants and `financeRoutes` helper                              |
| Task 2: New finance permissions + seed                   | `view_finance_student_overview`, `view_finance_all_campus`, and role mapping |
| Task 3: Student 360 minimal route shell                  | read-only Student 360 destination for search                                 |
| Task 4: Global finance search endpoint + command palette | search endpoint, result mapping, and command palette UI                      |
| Task 5: Semester topbar contract                         | shared semester Inertia prop, session route, and switcher                    |
| Task 6: Finance Office sidebar IA                        | five work groups plus separate Discounts & Funding group                     |
| Task 7: Topbar mount + evidence                          | shell integration and M1 validation evidence                                 |

Together they ship the operator-console foundation:

- Finance-aware app shell affordances: global command search and shared semester
  switcher.
- Work-organized Finance Office sidebar with five groups plus a separate
  Discounts & Funding group.
- Finance route-name constants so new Finance navigation uses route helpers
  instead of literal URLs.
- Minimal Student 360 route shell that global search can land on immediately.
- Read-only/search/session plumbing only; no Finance money-write logic changes.

Milestone 2 is the separate canonical story
`FIN-REV-010-student-360-full` at `S-010-student-360-full/`. It keeps the same
`finance.students.overview` route and augments Student 360 into the full
operator surface. Its scope guard:

- no route/destination change from M1,
- no money-calculation rewrite,
- write UI must wrap existing tested actions/services,
- DNG cancel must add the missing `void_finance_charges` gate when linked
  charges exist,
- final evidence must include finance invariant output after exercising write
  paths.

The broader intended product direction remains:

- A task-based workspace for staff, organized by semester and collection cycle.
- The first screen is a checklist of work to do for the selected semester/campus.
- "Collection cycle" is an MVP UI concept only: no new database table yet.
- Existing source pages remain available for deep-dive, audit, and admin repair.
- Normal staff work happens in embedded drawers/modals from the workspace rather
  than requiring staff to navigate across many Finance pages.

Priority order for future slices:

1. Proactive charge creation.
    - Manual one-student charge.
    - Bulk non-academic charges such as BHYT.
    - Later: HP/EGC generation entry points if the drawer scope remains safe.
2. DNG collection.
    - Review eligible students/charges.
    - Push or update DNG payment requests.
    - See active DNG, due reminders, and overdue follow-up entry points.
3. Training-driven finance decisions.
    - Retake / exam resit fees.
    - Academic defer cases: preserve fee, forfeit fee, or partial preserve.
    - Lifecycle exceptions: keep debt, cancel DNG, void linked charges, or route to
      settlement.
    - EGC retake adjustments and carry-forward.

## Affected Users

- Finance staff who create and collect fees.
- Finance lead/admin who needs to monitor collection-cycle progress.
- Academic operations staff indirectly, because some finance tasks depend on
  academic outcomes such as retake, exam resit, defer, dropout, or transfer.

## Affected Product Docs

- `docs/stories/E-finance-module-review-2026-06/README.md`
- Existing Finance review story set.
- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-shell-and-360-foundation.md`
- `docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md`
- Future staff-workspace implementation plan.

## Portal Impact

Portal impact: none.

This story targets admin/staff web UI only. It must not change
`/api/v1/student/*` or `/api/v1/lecturer/*` contracts unless a later approved
slice explicitly says so.

## Non-Goals

- Do not implement money-write logic, ledger recalculation, DNG state mutation,
  charge generation, payment allocation, or settlement changes in Milestone 1.
- Do not replace S-009 Finance Audit Workspace; audit remains a read-only trace
  and investigation surface.
- Do not add a `finance_collection_campaigns` or similar campaign table in MVP.
- Do not remove existing Finance source pages.
- Do not duplicate money math. Future implementation must reuse current Finance
  actions, queries, `SettlementService`, DNG actions, and invariant/audit helpers.
