---
phase: 2
title: "Close the void-guard orphan hole"
status: pending
priority: P1
effort: "0.5d"
dependencies: []
---

# Phase 2: Close the void-guard orphan hole

## Overview

`VoidFinanceChargeAction` blocks voiding a charge that still has a live DNG
collection — but its guard only recognises two of the four statuses that mean
"the provider may be holding this", and only finds requests through the
installment table. Charges that slip past leave a collectible record at DNG for
a debt Swinx has written off.

## Root cause

The guard is `assertNoLiveDngAwaitingInstallment()`. Two independent holes:

**Hole A — the status list is too narrow.** It matches
`[STATUS_PENDING, STATUS_PUSHED_TO_DNG]`. But `HOLDING_COLLECTION_STATUSES`
also contains `unknown_outcome` and `needs_review`. `unknown_outcome` *by
definition* means Swinx does not know whether DNG holds the record — precisely
the case where a void must not proceed unchecked. `CancelDngPaymentRequestAction`
itself accepts all four holding statuses in `runLocallyForLifecycle()`, which
shows the narrow list is the outlier.

**Hole B — the lookup path is too narrow.** It reaches the request through
`finance_charge_installments.dng_payment_request_id`. A charge with **no
installment plan** and a live pushed request is invisible to it. The charge
pivot `dng_payment_request_charges` is the general link and is not consulted.

### What this phase does *not* target

The first draft of this plan blamed
`CancelFinanceObligationAction::cancelAwaitingDngRequestsForCharge()`, which
mass-updates requests to `cancelled` with no provider call. Red team found that
class has **no caller**: `FinanceObligationCancellationContract` is bound in
`FinanceServiceProvider` but nothing in `app/` or `tests/` resolves it. The
live Academic path is `FinanceCancellationOperationRequestContract` →
`RequestFinanceCancellationOperationAction` →
`ProcessFinanceCancellationOperationAction`, which already sequences a
provider cancellation before the void.

Rewriting an unreachable class would have consumed the phase while the
reachable hole stayed open. Verified with:

```bash
grep -rn "FinanceObligationCancellationContract" app/ tests/
```

which returns only the interface, the provider binding, an enum comment, and
the implementation itself. Deleting the class is a separate cleanup — see plan
open question 1.

## Requirements

- Functional: the guard covers all four holding statuses.
- Functional: the guard finds live requests through the charge pivot as well as
  through installments.
- Functional: `ProcessFinanceCancellationOperationAction`'s existing
  cancel-before-void sequencing keeps working unchanged.
- Functional: a multi-charge request is not silently killed when one of its
  charges is voided.
- Non-functional: no behaviour change for charges with no live DNG request.

## Architecture

Holes A and B are both inside one method, and both fixes are widenings of
existing queries — no new call paths, no transaction restructuring.

Rename the method: it is no longer installment-specific.

```php
// was: assertNoLiveDngAwaitingInstallment()
private function assertNoLiveDngCollection(FinanceCharge $charge): void
```

Resolve live requests as the union of:
- `finance_charge_installments` where `finance_charge_id = charge.id` and
  `dng_payment_request_id` is not null
- `dng_payment_request_charges` where `finance_charge_id = charge.id`

filtered by `DngPaymentRequest::holdingCollection()` rather than the hardcoded
two-status list. The error message should keep naming the request id and the
cancel-first remedy, since that is what makes the block actionable.

### Multi-charge requests (M19)

One DNG request can cover several charges — `CreateBatchDngFromChargesAction`
builds exactly that for retake fees. Voiding one charge must not cancel the
siblings' collection.

`ProcessFinanceCancellationOperationAction` already solves this: it cancels the
collection at the provider and then calls `createReplacementForRemainingTargets`
for the charges that survive. The widened guard should therefore **block and
point at that operation** rather than trying to cancel inline. Blocking is
correct here: the operator needs the replacement path, not a bare cancel.

## Related Code Files

- Modify: `app/Modules/Finance/Actions/VoidFinanceChargeAction.php` —
  `assertNoLiveDngAwaitingInstallment()`: widen statuses, widen lookup, rename.
  The call site inside `handle()` stays where it is.
- Read: `app/Modules/Finance/Actions/ProcessFinanceCancellationOperationAction.php`
  — the sequencing the error message should steer operators toward.
- Read: `app/Modules/Finance/Dng/Models/DngPaymentRequest.php` —
  `HOLDING_COLLECTION_STATUSES`, `scopeHoldingCollection`.
- Modify: `tests/Feature/Finance/CancellationOperationTest.php`.
- Create: a test file for the widened guard, or extend the nearest existing
  `VoidFinanceChargeAction` coverage.

## Implementation Steps

1. Failing test: charge with **no installments** and a `pushed_to_dng` request
   linked only through `dng_payment_request_charges`; void it; assert it is
   blocked. Today it succeeds and orphans the record.
2. Failing test, parameterised over `unknown_outcome` and `needs_review`: a
   charge whose live request sits in either status is blocked from voiding.
3. Passing-guard test: a charge whose request is terminal
   (`cancelled`, `cancel_pushed_to_dng`, `paid_*`) voids normally — the guard
   must not become a blanket block.
4. Widen the status filter to `holdingCollection()`.
5. Widen the lookup to the union of installment link and charge pivot.
6. Rename the method and update its docblock to state what it protects and why.
7. Confirm the multi-charge case blocks with a message naming the cancellation
   operation path.
8. Check `ApplyDeferFinancePolicyAction` and `ResolveLifecycleDueExceptionAction`
   for the same narrow-status pattern — both already hold a
   `CancelDngPaymentRequestAction` reference, so they are probably fine.
   Confirm rather than assume.

## Test / Validation Gate

Run individually, resetting `db_test` first (command in Phase 1):

- the new / extended void-guard test
- `tests/Feature/Finance/CancellationOperationTest.php`
- `tests/Feature/Finance/CancelDngPaymentRequestActionTest.php`
- `tests/Feature/Finance/Defer/` (per file)
- `tests/Feature/Finance/BhytIntakeBackfillDngClosureTest.php`
- `tests/Feature/Finance/EgcBackfillDngClosureTest.php`
- `tests/Feature/Finance/TuitionTermBackfillDngClosureTest.php`

The three `*BackfillDngClosureTest` files are the highest-risk consumers: they
close DNG requests in bulk. A widened guard may now block backfill paths that
previously sailed through. If so, that is a real discovery — surface it rather
than mocking it away, because it means those backfills have been voiding
charges with live provider records.

## Success Criteria

- [ ] A charge with no installments and a live pushed request cannot be voided.
- [ ] `unknown_outcome` and `needs_review` requests block a void.
- [ ] Terminal-status requests do not block a void.
- [ ] The block message names the request id and the cancellation path.
- [ ] Multi-charge requests are not cancelled wholesale by a single-charge void.
- [ ] Backfill closure suites green, or their changed expectations are
      deliberate and documented as a real defect found.

## Risk Assessment

| Risk | Mitigation |
|---|---|
| Widened guard blocks legitimate bulk backfills | Step 3 keeps terminal statuses passing. If a backfill still blocks, it was voiding charges with live provider records — a finding, not a regression to paper over |
| `unknown_outcome` requests block voids indefinitely | That is the intended fail-closed behaviour. The escape hatch already exists: `ResolveDngReservationOutcomeAction` resolves a held reservation to a terminal status |
| Union lookup is slower on large charges | Both sides are indexed foreign keys; the guard runs once per void, not per row |
