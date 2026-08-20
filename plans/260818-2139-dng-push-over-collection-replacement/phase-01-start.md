---
phase: 1
title: "Automatic cancel-then-push replacement"
status: done
priority: P1
effort: "1-1.5d"
dependencies: []
---

# Phase 1: Automatic cancel-then-push replacement

## Overview

When a reservation request finds a live unpaid collection whose targets no
longer match the current payable, cancel that collection at the provider and
then build a fresh one covering the full payable — instead of flipping the live
request to `needs_review` and blocking forever.

## Requirements

- Functional: a non-exact retry against a `pending` or `pushed_to_dng` request
  triggers cancel-then-push and yields one live collection for the full
  payable.
- Functional: a failed provider cancel aborts the replacement entirely — no new
  request is created, no prior state is altered beyond the recorded failure.
- Functional: exact-retry idempotency is unchanged.
- Functional: the replacement never collects less than the request it replaced.
- Functional: `needs_review` and `unknown_outcome` requests are **not**
  auto-replaced; they fail closed with an actionable message.
- Functional: cancellation-replacement rows (created by
  `createCancellationReplacement`, carrying a remaining balance) are never
  auto-replaced.
- Non-functional: behind `config('finance.dng.auto_replace_stale_collection')`,
  default off.
- Non-functional: `active_slot_key` timing and composition unchanged.
- Non-functional: no provider call inside a transaction.

## Architecture

### Today

```
reserve()  → existing live request found, targets differ
           → holdForReview(existing) + return existing     ← blocked forever
```

### After

```
reserve()  → existing live request found, targets differ
           → eligible for automatic replacement?
             ├─ no  → holdForReview (today's behaviour, unchanged)
             └─ yes → CancelDngPaymentRequestAction::run(existing)
                        ├─ provider cancel fails → propagate; nothing created
                        └─ success → existing is terminal, slot released,
                                     installments back to pending
                      → re-enter reserve() on the now-clear slot (happy path)
                      → push() as normal
```

Everything after the cancel is the **existing untouched happy path**. That is
the point of this shape: no new slot semantics, no supersession predicate, no
second holding row, no inference about provider behaviour.

### Why the ordering is cancel-first

Cancel-first has one visible cost — a short window where the student has no
payable record at DNG. Push-first would avoid that window but requires either
trusting an unverified implicit-replace behaviour or generating a second live
record. The window is seconds; a duplicate collection is money. See the plan's
"Design decision" section.

If the cancel succeeds and the subsequent push then fails, the student is left
with no live collection and an operator-visible failure. That is recoverable
and loud. The inverse — a live record nobody in Swinx points at — is neither.

### Eligibility

Replace automatically only when **all** hold:

| Condition | Why |
|---|---|
| `config('finance.dng.auto_replace_stale_collection')` is on | Kill-switch |
| existing status ∈ `{pending, pushed_to_dng}` | The only statuses `CancelDngPaymentRequestAction::run()` accepts; `unknown_outcome` / `needs_review` mean Swinx does not know the provider state |
| existing has reservation targets | Excludes `createCancellationReplacement` rows, which carry a remaining balance and have charge links only (H11) |
| new reservation amount ≥ existing amount | Prevents an installment-capped push from shrinking a live aggregate collection (H10) |
| `isExactRetry()` is false | An exact retry must stay idempotent, with no provider call |

Failing any condition falls back to today's `holdForReview` — same behaviour as
now, no regression.

The amount guard matters because `CreateBatchDngFromChargesAction` caps each
line to the student's *next pending installment*. Without the guard, a split-plan
student's 6.000.000 aggregate collection could be replaced by a 3.000.000 one
while the operator is told the full payable was pushed.

### Installment handoff

No new code. `CancelDngPaymentRequestAction::releaseInstallments()` (added in
`8c46718a7`) returns the cancelled request's installments to `pending`.
`CreateBatchDngFromChargesAction` selects `pending` installments. So the
replacement adopts them naturally, in that order.

This ordering is exactly what made the abandoned push-over design unsafe: there
the new request was built *before* the old one released its installments, so it
could not adopt them and reserved the full line amount instead — leaving a
`pending` installment for money already inside the new collection (red team C4).

### Audit trail

None invented. A provider-confirmed cancel already produces
`cancel_pushed_to_dng`, `cancel_push_payload`, `cancel_push_response`, and a
`cancel_confirmed` receipt exception via `RegisterDngReceiptExceptionAction`.
Add only a `replacement` marker in the cancel's evidence naming the reservation
that triggered it, plus the acting user id (H16).

## Related Code Files

- Modify: `app/Modules/Finance/Dng/Services/DngReservationLifecycle.php`
  - `reserve()` — at the non-exact-retry branch (the `holdForReview` call whose
    reason is *"An active DNG reservation no longer matches the requested
    payer, settlement targets, amount, version, or provider identity."*),
    insert the eligibility check and the cancel-then-retry path. Leave the
    cross-campus `RuntimeException` guard and the exact-retry `return $existing`
    untouched.
  - No change to `slotKey()`, `itemId()`, the `create()` payload, `finalize()`,
    or `isExactRetry()`.
- Modify: `config/finance.php` — add `dng.auto_replace_stale_collection`
  (default `false`).
- Read: `app/Modules/Finance/Actions/CancelDngPaymentRequestAction.php` —
  `run()` and `releaseInstallments()`.
- Modify: `tests/Feature/Finance/Dng/ReserveAndPushSingleFeeDngActionTest.php`.

**Citation note:** cite symbols, not line numbers. Commit `8c46718a7` shifted
this file's numbering and the first draft of this plan carried four stale
references as a result.

## Implementation Steps

1. Failing test first, flag on: a live `pushed_to_dng` `HL` request at
   1.000.000; payable grows by 500.000; assert the provider received a cancel
   for the original `item_id`, the old request is `cancel_pushed_to_dng`, and a
   new `pushed_to_dng` request exists at 1.500.000.
2. Failing test: flag **off** → today's `holdForReview` behaviour, unchanged.
3. Failing test: provider cancel throws → the exception propagates, the old
   request keeps its status and slot, and **no** new request row exists.
4. Failing test: amount guard — a reservation smaller than the live request
   holds for review instead of replacing.
5. Failing test: `needs_review` and `unknown_outcome` existing requests hold
   for review, no provider call.
6. Failing test: a `createCancellationReplacement` row is not auto-replaced.
7. Add the config flag.
8. Implement the eligibility check and the cancel-then-retry path in
   `reserve()`. The provider cancel must happen **outside** the settlement
   guard closure — `CancelDngPaymentRequestAction::run()` opens its own guarded
   transactions, so calling it from inside `reserve()`'s closure would nest
   them. Structure `reserve()` so the eligibility decision is made inside the
   closure, the closure returns a "needs replacement" signal, the cancel runs
   outside it, and reservation is then re-attempted.
9. Record the replacement marker and acting user id in the cancel evidence.
10. Verify the exact-retry path still issues zero provider calls.

## Test / Validation Gate

`tests/Feature/Finance/Dng/ReserveAndPushSingleFeeDngActionTest.php` — with the
flag off, **every existing test must stay green unchanged**. That is the
regression contract for this phase and the main reason the flag exists.

New tests go in a flag-on block. The following existing tests describe
behaviour that changes only when the flag is on, and need a flag-on counterpart
rather than an edit:

- `holds an active retry with a different invoice-line target for reconciliation`
- `holds an active retry when its exact installment target changes`
- `holds an active retry when its amount and target fingerprint change`
- `holds an active retry when its semester changes`
- `promotes a target-drifted unknown outcome to staff review` (asserts
  `pushReserved` called once and `count()` == 1 — both shift under replacement)
- `holds a stale settlement-version retry for staff reconciliation` —
  **must hold in both modes**; a stale settlement version is a concurrency
  signal, not payable growth
- `blocks a concurrent credit application during the unlocked DNG provider
  call...` — asserts a specific `settlement_version`; replacement adds guarded
  mutations, so re-check the expected number

Run per file after resetting `db_test` — several Finance files in one pest
invocation deadlocks and has crashed the MariaDB container:

```bash
U=$(grep -E "^DB_USERNAME" .env | cut -d= -f2); P=$(grep -E "^DB_PASSWORD" .env | cut -d= -f2)
docker compose --env-file .env -f docker/docker-compose.dev.yml -p swinx-dev exec -T db \
  mariadb -u"$U" -p"$P" --skip-ssl -e "DROP DATABASE IF EXISTS db_test; CREATE DATABASE db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
docker compose --env-file .env -f docker/docker-compose.dev.yml -p swinx-dev exec -T app \
  php ./vendor/bin/pest tests/Feature/Finance/Dng/ReserveAndPushSingleFeeDngActionTest.php --colors=never
```

Also green, individually: `CancelDngPaymentRequestActionTest`,
`PushNextInstallmentActionTest` + `InstallmentEventsTest` (coupled pair — the
second cannot run alone), `BatchDngInstallmentAwareTest`,
`CancellationOperationTest`, `CreditInstallmentReconciliationUnderHoldTest`.

## Success Criteria

- [x] Flag off → every existing test green with no edits.
- [x] Flag on, payable grows → provider cancel for the original `item_id`, then
      a new pushed request for the full payable.
- [x] Provider cancel fails → exception propagates, no new row, prior state
      untouched.
- [x] A smaller reservation never replaces a larger live collection.
- [x] `needs_review` / `unknown_outcome` / replacement rows are never
      auto-replaced.
- [x] Exact retry issues no provider call.
- [x] The replaced request's installments end up owned by the new request
      (true when `installmentIdsByLine` is supplied; a replacement built with
      no installment mapping — e.g. the single-fee/batch path — leaves the
      cancelled request's installments `pending` and the new request's targets
      installment-less. No over-collection or lost money either way; that
      charge just can't be installment-pushed again until reconciled. Left for
      Phase 3, not fixed here).
- [x] `active_slot_key` remains unique with one live request per slot.
- [x] Stale-settlement-version holds still fire in both modes.

## Implementation notes (post red-team code review)

Two rounds of `code-reviewer` review ran against the implementation; round 1
returned **BLOCK** on two CRITICAL findings, both fixed and re-verified as
**APPROVE** in round 2.

**Eligibility rule corrected.** The plan's eligibility condition ("new
reservation amount ≥ existing amount") was implemented literally at first and
found unsound: it compares only totals, so a same/larger-total request
targeting an entirely different invoice line still passed, cancelling a live
collection that covered a charge the replacement never re-covers — and because
every successful push advances `settlement_version` twice (`reserve()` +
`finalize()`), `isExactRetry()` can never match again for a pushed request, so
even an unchanged retry hit this path. Fixed by requiring (a) every one of the
existing request's stored `reservationTargets()` — same `invoice_line_id`, at
no less than its `captured_collectible` — is present in the newly computed
targets (a true superset, not a total comparison), and (b) the new total is
**strictly** greater (not `≥`). `DngReservationLifecycle::eligibleForAutomaticReplacement()`
is the corrected implementation; see its docblock.

**Scope widened beyond the file list above (user-approved).** `reserve()` is
also called nested inside `PushNextInstallmentAction::handle()`'s own
`SettlementMutationGuard` scope (same billing account) — `SettlementMutationGuard`
is reentrant per billing account, so only the outermost call actually opens a
DB transaction. Cancelling right after `reserve()`'s own guard call returns is
only safe when `reserve()` is that outermost call. `DngReservationReplacementRequired`
(new, `app/Modules/Finance/Dng/Exceptions/`) is thrown instead of calling
`holdForReview` when eligible, unwinding whichever transaction is open;
whichever caller owns the *outermost* guard scope for that billing account
catches it, cancels with no transaction/lock held (asserted via
`SettlementMutationGuard::isActive()` + a captured `DB::transactionLevel()`
baseline), and retries once. `PushNextInstallmentAction` was modified to add
this catch. In practice, the single-line-per-installment shape of that path
means the superset rule can never let it legitimately replace a different
charge's collection — it safely falls through to today's (separately
pre-existing, unfixed-by-this-phase) `holdForReview` behaviour instead.

**Audit trail (implementation step 9).** `CancelDngPaymentRequestAction::run()`
takes an optional `replacementContext` (`trigger`, `actor_user_id`), recorded
under `raw_provider_evidence.replacement` on the `cancel_confirmed` receipt
exception. Both auto-replace call sites pass it.

**Open items, not blocking:**
- Kill-switch wording: `config/finance.php` reads `env()`, so flipping it under
  `config:cache` (normal deploy) needs a re-cache + restart, not a bare env
  edit — "without a deploy" in the plan's Success Criteria is optimistic
  unless backed by a DB/cache-backed setting later.
- `DngReservationLifecycle::attemptReserve()` / `PushNextInstallmentAction::attemptHandle()`
  cap the cancel-then-retry to one attempt and assert no transaction is open
  before cancelling, but that assertion only detects guard-based nesting
  (`SettlementMutationGuard`), not a hypothetical bare `DB::transaction()`
  wrapped around these calls by a future caller. No such caller exists today.

## Risk Assessment

| Risk | Mitigation |
|---|---|
| Cancel succeeds, push then fails → student has no live collection | Loud and recoverable: the failure surfaces through the normal push error path. Preferable to an orphaned live record, which is silent |
| Student pays the old record during the cancel window | Pre-existing behaviour of every cancel, already handled: the provider path records `payment_during_cancellation` and `CaptureDngProviderReceiptAction` books the receipt without reviving the request. Not made worse here |
| Nested transactions from calling the cancel action inside `reserve()`'s guarded closure | Step 8 is explicit: decide inside, cancel outside, re-attempt |
| Automatic cancellation fires more often than expected | Kill-switch, default off, soak before enabling |
| An installment-capped batch push shrinks a live collection | Amount guard in eligibility |
| Replacement loop if the new reservation keeps mismatching | The replacement re-enters `reserve()` on an empty slot, so the non-exact-retry branch cannot trigger twice for one call. Assert a single provider cancel per replacement in tests |
