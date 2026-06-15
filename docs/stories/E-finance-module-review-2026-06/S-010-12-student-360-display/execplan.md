# Exec Plan

## Goal

Render the full Student 360 read-only display for Milestone 2.

## Scope

In scope:

- M2 finance TypeScript types.
- `StatusCards.vue`
- `DngStateStepper.vue`
- `LedgerLens.vue`
- `resources/js/pages/Finance/Student360/Show.vue`
- Per-file lint and browser display check.

Out of scope:

- Record payment drawer.
- Allocation preview/apply drawer.
- DNG cancel drawer.
- Focus highlight.
- Backend query/route changes.

## Risk Classification

Risk flags:

- Public contract: consumes new Inertia props.
- Existing behavior: replaces the M1 single timeline card with dual lenses.
- Finance data display: staff-facing money/status presentation.
- Cross-platform/browser shell: responsive Inertia page behavior.
- Weak proof: no JS unit runner exists.

Hard gates:

- Must use Inertia v3 `Deferred` syntax.
- Must not compute money state in Vue.
- Must preserve the existing Student 360 route and page component.
- Must keep action emits inert until the action-drawer story wires them.

## Work Phases

1. Add TypeScript interfaces matching backend snake_case props.
2. Build `DngStateStepper`.
3. Build `StatusCards`.
4. Build `LedgerLens`.
5. Wire components into `Show.vue` without write behavior.
6. Run targeted frontend lint.
7. Browser-check cards, stepper, and both ledger lenses.

## Stop Conditions

Pause if:

- Backend prop shape differs from the story contract.
- UI primitives such as tabs/skeleton/card exports differ from planned names.
- `Deferred` behavior requires changing backend prop names.
- Layout becomes cramped or unreadable on common desktop/mobile widths.
