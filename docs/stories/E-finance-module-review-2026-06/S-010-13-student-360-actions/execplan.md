# Exec Plan

## Goal

Wire the full Student 360 action UI to the M2 backend contracts.

## Scope

In scope:

- `StudentActionMenu.vue`
- `RecordPaymentDrawer.vue`
- `AllocatePreviewDrawer.vue`
- `CancelDngDrawer.vue`
- `resources/js/pages/Finance/Student360/Show.vue`
- Focus highlight behavior for `focus=<type>:<id>`
- Per-file lint and browser smoke preparation

Out of scope:

- Backend adapter implementation.
- New permissions.
- New money logic.
- Portal code.

## Risk Classification

Risk flags:

- Authorization: permission-aware UI rendering.
- Public contract: consumes new route helpers and JSON envelopes.
- Existing behavior: wires existing Finance write actions into a new surface.
- Audit/security: destructive DNG cancel must be blocked before submit.
- Cross-platform/browser shell: drawer/DatePicker/focus behavior.
- Weak proof: browser smoke is manual/final-evidence driven.

Hard gates:

- Backend must already enforce permissions; UI is not the only guard.
- DNG cancel must implement the 4-layer block/impact/reason/ack pattern.
- Date fields in drawers must use `DatePicker`, not native date inputs.
- `focus` highlight must not break normal navigation or deferred prop reloads.
- No raw literal endpoint strings when route helpers exist.

## Work Phases

1. Build the action menu.
2. Build record-payment drawer with Inertia `useForm`.
3. Build allocation preview drawer with shared API wrapper and existing allocate
   route.
4. Build DNG cancel drawer with impact loading and blocking rules.
5. Wire drawers and focus highlight in `Show.vue`.
6. Run targeted frontend lint.
7. Leave final permission matrix/browser smoke to `FIN-REV-010-14`.

## Stop Conditions

Pause if:

- `unapplied_payment_id` is missing from `status_cards.balance`.
- Existing UI primitives have incompatible APIs and need a design adjustment.
- DNG impact does not expose enough detail to block destructive submit.
- The record-payment UX must auto-allocate immediately.
