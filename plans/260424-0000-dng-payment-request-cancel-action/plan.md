# DNG Payment Request Cancel Action

## Status

- In progress

## Scope

- Add admin cancel action for DNG payment requests at `/finance/dng/payment-requests`.
- Show confirmation dialog before cancel.
- Keep filters debounced.

## Implementation Plan

1. Add a POST route under `finance.dng.payment-requests.*` for cancelling a request.
2. Add controller method that allows cancel only through the model state machine (`pending`, `pushed_to_dng`), preserves terminal statuses, and redirects back with flash status.
3. Update the payment request list page to show a cancel action only for cancellable statuses and route it through the existing global confirm dialog.
4. Ensure search/filter behavior uses explicit debounce settings through the existing shared filter/table components.
5. Add or update focused feature coverage for the cancel route.
6. Run targeted backend tests and frontend type check where feasible.

## Success Criteria

- Admin can cancel pending or pushed DNG requests from the list after confirming.
- Paid, reconciled, failed, or already cancelled requests cannot be cancelled.
- Filter search remains debounced.
- Existing index/show behavior remains unchanged.

## Unresolved Questions

- None.
