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
workflow before implementation planning.

The intended product direction is:

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
- Future staff-workspace implementation plan.

## Portal Impact

Portal impact: none.

This story targets admin/staff web UI only. It must not change
`/api/v1/student/*` or `/api/v1/lecturer/*` contracts unless a later approved
slice explicitly says so.

## Non-Goals

- Do not implement UI or backend logic in this requirements story.
- Do not replace S-009 Finance Audit Workspace; audit remains a read-only trace
  and investigation surface.
- Do not add a `finance_collection_campaigns` or similar campaign table in MVP.
- Do not remove existing Finance source pages.
- Do not duplicate money math. Future implementation must reuse current Finance
  actions, queries, `SettlementService`, DNG actions, and invariant/audit helpers.
