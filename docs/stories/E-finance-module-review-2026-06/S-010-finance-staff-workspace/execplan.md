# Exec Plan

## Goal

Create and track the Finance Office Shell + Student 360 Foundation task from
`docs/superpowers/plans/2026-06-15-finance-office-shell-and-360-foundation.md`.

Milestone 1 gives Finance staff a usable operator-console foundation: global
search, semester context, five-group Finance IA, route helpers, and a minimal
Student 360 destination. It must stay read-only/presentation-only for Finance
money state. The seven plan tasks are checklist items inside this consolidated
story; the old child story packets are retained only as historical/debugging
notes.

Milestone 2 is tracked from
`docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md` as the
separate story `FIN-REV-010-student-360-full`.

## Scope

In scope:

- Finance route-name constants and `financeRoutes`.
- Permissions `view_finance_student_overview` and `view_finance_all_campus`,
  with seed/role mapping.
- Minimal read-only Student 360 route shell.
- Global Finance search endpoint and command palette.
- Shared semester context prop and switcher.
- Finance Office sidebar migration into five work groups.
- Topbar mount, targeted validation, browser smoke, and Harness evidence.
- Preserve the broader staff-workspace choices already captured here:
  task-based workflow, semester/collection-cycle context, no campaign table in
  MVP, checklist-first future screen, embedded drawers/modals, and priority
  order of proactive charge creation, DNG collection, then training-driven
  finance decisions.

Out of scope:

- Changing Finance money-write logic or settlement/DNG state machines.
- Milestone 2 Student 360 full surface; that is tracked by
  `FIN-REV-010-student-360-full`.
- Adding DB tables, migrations, campaign persistence, or ledger tables.
- Changing student or lecturer portal API contracts.
- Building the Cockpit, Batch Studio, or broader staff workspace workflows.
  Those remain later milestones.
- Choosing final drawer component architecture for the future full staff
  workspace.
- Removing or replacing existing source pages.

## Risk Classification

Risk flags:

- Authorization: new Finance permissions and campus/all-campus scope.
- Public contract: new web routes, JSON search endpoint, shared Inertia prop,
  and route helper surface.
- Existing behavior: Finance sidebar IA and topbar shell change.
- Cross-platform/browser shell: keyboard command palette and shared app shell.
- Weak proof: frontend has no JS unit runner; proof relies on Pest, lint/build,
  and browser smoke.
- PII: search and Student 360 expose student identity to authorized staff.

Hard gates:

- Any route exposing student finance data must be permission-scoped and
  campus-scoped.
- M2 write paths must wrap existing tested actions/services and must not
  duplicate money rules.
- DNG reviewed cancel must require `void_finance_charges` when linked charges
  exist.
- Money state changes in M2 require finance invariant evidence before the slice
  is marked done.
- Search must use `ApiResponse::success()` and must not bypass the existing
  audit resolver/campus scoping.
- Student 360 must reuse existing Finance read models (`GetStudentBalanceQuery`,
  audit graph/timeline builders) instead of recalculating money in the
  controller or Vue.
- Finance write behavior must not change in Milestone 1.

## Work Phases

1. Intake: classify as high-risk because of authorization, PII, public UI/API
   contracts, and existing-shell behavior.
2. Plan review: use Superpowers execution discipline against
   `docs/superpowers/plans/2026-06-15-finance-office-shell-and-360-foundation.md`.
3. Implement or debug each plan task independently while keeping status and
   acceptance evidence on this consolidated milestone story.
4. Verification: run the targeted Pest suites, per-file frontend lint, production
   build, route-list sanity checks, and browser smoke called out in the plan.
5. Harness update: record validation evidence in `validation.md`, update the
   story matrix, and trace the read-only/no-money-state-change outcome.
6. Milestone 2 execution moves to `FIN-REV-010-student-360-full`, with review
   around write-adjacent tasks.

## Stop Conditions

Pause for human confirmation if:

- The design starts requiring a persisted collection-campaign table.
- A drawer would duplicate a large existing page instead of reusing query/action
  boundaries.
- A proposed UX hides source data needed for audit or repair.
- Staff terminology differs from current Finance/Academic terminology and needs
  product naming approval.
- A write action lacks an existing audit trail or permission boundary.
- A Milestone 1 implementation step would require changing Finance money state,
  DNG provider behavior, student/lecturer portal API contracts, or existing
  validation requirements.
- A Milestone 2 implementation step would require new money math, a route
  destination change, or weakening the DNG cancel/void gate.
