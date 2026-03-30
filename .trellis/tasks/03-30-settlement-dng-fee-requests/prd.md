# Settlement DNG Fee Requests

## Goal

Allow staff to start `dng_payment_requests` from the settlement worklist for students in the `No cash` state, capture a free-text fee description, and surface any existing DNG request for manual verification.

## Requirements

- Add a new description field to `dng_payment_requests`.
- Add a settlement worklist action for `No cash` students.
- Show a popup before creation so staff can enter the description.
- Redirect to the existing payment-create flow instead of creating the request directly inside settlement.
- Show an indicator when the student already has a DNG request so staff can verify manually.
- Keep behavior campus-safe and aligned with existing DNG request creation flow.

## Acceptance Criteria

- [ ] Staff can create a DNG request from `finance/operations/settlement` for a `No cash` student.
- [ ] Popup requires a description before submit.
- [ ] Created request stores the new description field.
- [ ] Settlement and payment-create screens show existing DNG request context for manual verification.
- [ ] Focused tests cover backend query/action behavior, prefill, and existing-request indicators.

## Technical Notes

- Existing settlement readiness currently derives from unpaid invoices + unapplied cash only.
- Existing DNG request creation flow already exists via `DngPaymentService::createAndPush()` and `POST /api/v1/finance/dng/payment-requests`.
- User clarified that existing DNG requests should be surfaced as indicators, not hard blockers.
