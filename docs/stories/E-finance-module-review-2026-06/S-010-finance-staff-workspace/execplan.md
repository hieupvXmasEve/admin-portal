# Exec Plan

## Goal

Capture the desired Finance staff workflow as a separate story before deciding
implementation logic.

This story is a product/IA discovery holder. It should prevent the staff-flow
requirements from being mixed into S-009 Finance Audit Workspace.

## Scope

In scope:

- Record that S-009 is audit-only and not the staff workflow solution.
- Define the desired staff workspace direction.
- Preserve confirmed choices:
  - task-based workflow,
  - organized by semester and collection cycle,
  - no new campaign table in MVP,
  - checklist first screen,
  - embedded drawers/modals,
  - priority order: proactive charge creation, DNG collection, training-driven
    finance decisions.
- List existing Finance pages/actions that future slices should reuse.
- Keep open questions visible for further discussion.

Out of scope:

- Implementing routes, controllers, queries, actions, or Vue components.
- Choosing final drawer component architecture.
- Adding DB tables or migrations.
- Changing Finance write behavior.
- Removing or replacing existing source pages.

## Risk Classification

Risk flags:

- finance-write-path-adjacent
- authz
- PII
- DNG provider adjacency
- workflow IA
- staff operational UX

Hard gates:

- Finance write behavior must not change without a later approved story.
- Any future drawer that writes money, cancels DNG, voids charges, or applies
  settlement must prove it reuses existing tested actions or explicitly justify a
  new action.
- Any future route must be permission-scoped and campus-scoped.

## Work Phases

1. Discovery: continue discussing staff tasks and the actual pages/actions they
   map to.
2. Design: choose the first implementation slice, likely proactive charge
   creation.
3. Validation planning: define tests and browser smoke for that slice.
4. Implementation: only after a separate approved plan.
5. Verification: targeted backend/frontend checks plus browser proof.
6. Harness update: record evidence and any deferred workflow slices.

## Stop Conditions

Pause for human confirmation if:

- The design starts requiring a persisted collection-campaign table.
- A drawer would duplicate a large existing page instead of reusing query/action
  boundaries.
- A proposed UX hides source data needed for audit or repair.
- Staff terminology differs from current Finance/Academic terminology and needs
  product naming approval.
- A write action lacks an existing audit trail or permission boundary.
