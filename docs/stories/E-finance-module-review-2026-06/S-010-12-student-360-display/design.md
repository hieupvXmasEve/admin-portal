# Design

## Domain Model

Frontend types mirror the backend props:

- `Student360StatusCards`
- `Student360Actions`
- `DngCardRequest`
- `LedgerGroup`
- `LedgerInvoice`
- `LedgerInvoiceLine`

The component split:

- `StatusCards.vue`
- `DngStateStepper.vue`
- `LedgerLens.vue`

## Application Flow

1. Student 360 page receives M2 props from the controller.
2. `StatusCards` renders balance, DNG, installment, and exception cards.
3. `DngStateStepper` renders the DNG two-call lifecycle from current status.
4. `LedgerLens` renders grouped ledger and timeline tabs.
5. Action emits may be present as inert/no-op placeholders until the next story.

## Interface Contract

Frontend files:

- `resources/js/types/finance.ts`
- `resources/js/components/finance/student360/StatusCards.vue`
- `resources/js/components/finance/student360/DngStateStepper.vue`
- `resources/js/components/finance/student360/LedgerLens.vue`
- `resources/js/pages/Finance/Student360/Show.vue`

Inertia v3 requirements:

- `<Deferred>` uses a string `data` prop, for example
  `<Deferred data="ledger_groups">`.
- Slots use `#fallback` and `#default` where needed.
- Props remain snake_case.

## Data Model

No data model or backend contract changes beyond consuming existing M2 props.

## UI / Platform Impact

Use existing Swinx UI primitives and Tailwind tokens:

- cards/badges/buttons/tabs/skeletons from `@/components/ui`
- `lucide-vue-next` icons where icon buttons are needed
- compact operational layout, not marketing layout

The component should fit staff repeated-use workflows: dense, scan-friendly, and
stable across desktop/mobile breakpoints.

## Observability

Frontend proof is per-file lint plus browser smoke in the final M2 evidence
story. This story itself changes no money state.

## Alternatives Considered

1. Keep all markup in `Show.vue`.
   - Rejected because cards/stepper/lens are reusable and easier to test
     visually as focused components.
2. Build write drawers at the same time.
   - Rejected to keep display risk separate from write-adjacent workflows.
