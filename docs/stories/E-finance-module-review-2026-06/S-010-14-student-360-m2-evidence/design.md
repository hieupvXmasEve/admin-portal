# Design

## Domain Model

This is a validation/evidence story. It covers the integrated behavior from:

- `FIN-REV-010-08-student-360-status-ledger` status cards and grouped ledger.
- `FIN-REV-010-09-manual-allocation-preview` allocation preview.
- `FIN-REV-010-10-record-payment-adapter` record-payment adapter.
- `FIN-REV-010-11-reviewed-dng-cancel` reviewed DNG cancel.
- `FIN-REV-010-12-student-360-display` display components.
- `FIN-REV-010-13-student-360-actions` action drawers and focus highlight.

## Application Flow

1. Run the full M2 backend suite.
2. Re-run M1 Student 360/search/shell regression tests.
3. Run frontend build/lint checks relevant to M2.
4. Smoke the Student 360 page with the permission matrix.
5. Run finance invariant checks before and after exercising record-payment and
   DNG-cancel/void paths.
6. Append M2 acceptance evidence to the umbrella validation doc.
7. Record a Harness trace.

## Interface Contract

Evidence must include:

- New routes and permission gates.
- Test names and pass/fail results.
- Permission matrix smoke rows.
- Invariant command name and before/after result.
- Note that writes wrap existing tested actions/services:
  `PaymentService::recordPayment`, `AllocatePaymentAction`,
  `CancelDngPaymentRequestAction`, and `PushNextInstallmentAction`.

## Data Model

No schema changes.

## UI / Platform Impact

Browser smoke must cover rendered Student 360 behavior for the full M2 surface:

- status cards,
- DNG stepper,
- dual ledger,
- action menu,
- record-payment drawer,
- allocation preview drawer,
- DNG cancel drawer,
- focus highlight.

## Observability

The core output is evidence in
`S-010-finance-staff-workspace/validation.md` plus the Harness trace. This story
must be honest about any command that could not run.

## Alternatives Considered

1. Let each implementation story own only isolated validation.
   - Rejected because the combined money/DNG path needs integrated proof.
2. Skip invariant evidence because feature tests pass.
   - Rejected because M2 exercises payment creation and DNG cancel/void paths.
