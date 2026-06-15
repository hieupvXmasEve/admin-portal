# Design

## Domain Model

The UI action layer consumes backend contracts from earlier stories:

- `actions.can_record_payment`
- `actions.can_allocate`
- `actions.can_cancel_dng`
- `actions.can_void_charges`
- `status_cards.balance.unapplied_payment_id`
- cancel-impact blocking reasons

Components:

- `StudentActionMenu.vue`
- `RecordPaymentDrawer.vue`
- `AllocatePreviewDrawer.vue`
- `CancelDngDrawer.vue`

## Application Flow

1. Staff opens Student 360.
2. `StudentActionMenu` shows only permission-allowed actions.
3. Record payment posts through Inertia to `finance.students.payments.store`.
4. Allocation preview reads JSON from `finance.payments.allocate-preview`, then
   applies through existing `finance.payments.allocate`.
5. DNG cancel drawer loads impact, blocks unsafe submit, then posts to
   `finance.dng.payment-requests.cancel-reviewed`.
6. Installment push uses the existing retry-push route.
7. `focus=<type>:<id>` scroll/highlight makes deep links visibly land on the
   intended card/section.

## Interface Contract

Frontend files:

- `resources/js/components/finance/student360/StudentActionMenu.vue`
- `resources/js/components/finance/student360/RecordPaymentDrawer.vue`
- `resources/js/components/finance/student360/AllocatePreviewDrawer.vue`
- `resources/js/components/finance/student360/CancelDngDrawer.vue`
- `resources/js/pages/Finance/Student360/Show.vue`

Behavior rules:

- Hidden when a user lacks permission.
- Disabled with reason when an action is blocked by state, not permission.
- DNG cancel submit disabled unless impact is loaded, no blocking reasons exist,
  reason is valid, acknowledgement is checked, and void permission exists when
  required.
- Allocation must use `unapplied_payment_id`; never infer payment id from a DNG
  request.

Form rules:

- Record-payment route redirects through Inertia, so `useForm` is acceptable.
- JSON preview/impact drawers use the existing `useApi`/`useApiRequest` pattern.
- Do not use `input[type=date]`; use the repo `DatePicker` with the correct
  `portalTo` behavior inside drawer/sheet content.

## Data Model

No schema changes.

## UI / Platform Impact

Use existing drawer/sheet, dropdown, checkbox, textarea, date picker, toast/flash
patterns, and `lucide-vue-next` icons.

The UI must remain compact and task-oriented, with stable dimensions so loading
states, labels, and buttons do not shift the page unexpectedly.

## Observability

This story wires write-adjacent UI. Final M2 validation must smoke the
permission matrix and run invariant evidence after exercising record-payment and
DNG-cancel paths.

## Alternatives Considered

1. Make unavailable actions clickable and rely on backend 403.
   - Rejected because the design requires hidden/disabled-with-reason behavior.
2. Guess allocation payment from the DNG request.
   - Rejected because payment allocation must use a real unapplied payment id.
3. Use raw fetch for JSON drawers.
   - Rejected because Swinx frontend standards prefer shared API wrappers.
