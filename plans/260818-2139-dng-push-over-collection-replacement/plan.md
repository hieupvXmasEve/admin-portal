---
title: "DNG automatic collection replacement"
description: "Replace a stale unpaid DNG collection automatically via provider-confirmed cancel-then-push, and close the void guard that lets a live provider record be orphaned"
status: in_progress
priority: P1
effort: "2-3d"
tags: [finance, dng, collection]
created: 2026-08-18
---

# DNG automatic collection replacement

## Overview

Swinx enforces "one live DNG collection per fee type" with a unique
`active_slot_key`. When a student's payable for that fee type grows after the
collection was pushed, `DngReservationLifecycle::reserve()` refuses to build a
replacement: it flips the *existing, valid, provider-held* request to
`needs_review` and returns it. The slot stays held, the extra payable is never
collected, and the batch worklist silently drops the student.

This plan makes that replacement automatic: cancel the stale collection at the
provider, then reserve and push a new one covering the full payable. No staff
step, no new slot semantics, no dependence on unverified provider behaviour.

### Worked example (verified against the dev database)

Student `AUH15442` (id 440) has two active `exam_resit_fee` charges in
semester 3 — `#1193` and `#1372`, 3.000.000 each. DNG request `#425` covers
only 3.000.000, because the second course was registered after the push.

| | Today | After this plan |
|---|---|---|
| Push 6.000.000 | refused; `#425` → `needs_review`, slot stuck | `#425` cancelled at DNG, then `#426` pushed at 6.000.000 |
| `#425` | holds the slot forever | `cancel_pushed_to_dng`, provider-confirmed |
| Staff action | cancel by hand, then rebuild | none |

## Design decision: cancel-then-push, not push-over

An earlier draft of this plan proposed *push-over*: push the new collection
first and let DNG implicitly drop the old record, moving the `active_slot_key`
claim from reservation time to after the provider call. Red team killed it.
The reasons are recorded here so it is not re-proposed.

**1. The premise was unverifiable and probably wrong.**
Operations reported that pushing over an unpaid record replaces it. But
`DngClient::cancelRecord()` cancels a record by re-posting **the same
`item_id`** with `Amount = -1`. That is direct evidence that DNG identifies a
record by `item_id`. The push-over design deliberately generated a *fresh*
`item_id` for the replacement — which, under that identity rule, creates a
second collectible record rather than replacing the first. AUH15442 would have
seen 3.000.000 and 6.000.000 live simultaneously.

**2. Moving the slot claim destroyed the only cross-process guard.**
The unique index on `active_slot_key` is claimed inside `reserve()`'s
transaction, i.e. **before** any provider call, so a concurrent contender fails
at the database and never reaches DNG. Claiming after the push would have let
two reservations both call the provider, with the conflict surfacing only after
the money side was already wrong.

**3. The explicit cancel makes the provider question irrelevant.**
If DNG does auto-replace, an explicit `cancelRecord` on the old record is a
harmless no-op. If it does not, the cancel is what prevents a duplicate. Either
way the outcome is one live record, and the plan no longer rests on an
assumption nobody can verify.

Cancel-then-push also reuses machinery that already exists and is already
tested: `CancelDngPaymentRequestAction` releases the slot, releases the
installments, and records provider evidence. `reserve()` then finds no holding
request and builds a fresh one through the unchanged happy path.

## Constraints

- Provider calls stay outside every DB transaction and lock — see the comment
  above the `cancelRecord` call in `CancelDngPaymentRequestAction::cancelPushedRequest()`.
- No settlement mutation outside `SettlementMutationGuard`.
- Paid, invoiced, and reconciled requests are settled facts — never cancelled,
  never replaced.
- `active_slot_key` keeps its current semantics: claimed at reservation time,
  released on any terminal transition (`DngPaymentRequest::transitionTo()`,
  which nulls the slot unless the target status is a holding one).
- Backward compatible with requests pushed before 2026-07-11, which store a
  PascalCase `push_payload` and have no reservation targets.

## Non-goals

- Changing `active_slot_key` timing or composition.
- Changing how amounts, discounts, or installment plans are computed.
- Reworking `DngWebhookService` payment attribution.
- Auto-resolving requests already stuck in `needs_review` / `unknown_outcome`.
  Those need a human; this plan fails closed on them with a clear message.
- Revisiting 403-rejection handling; the casing bug that made it fire is fixed
  in `8c46718a7`.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Payable growth triggers a provider-confirmed replacement with no staff action | P1 |
| 2 | A failed cancel or push never leaves Swinx and DNG disagreeing silently | P1 |
| 3 | Voiding a charge never orphans a live provider record | P1 |
| 4 | Uncovered payable is visible instead of silently skipped | P2 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Automatic cancel-then-push replacement](./phase-01-start.md) | Done |
| 2 | [Phase 2: Close the void-guard orphan hole](./phase-02-close-the-obligation-cancellation-provider-gap.md) | Pending |
| 3 | [Phase 3: Surface uncovered payable in worklist and batch](./phase-03-surface-uncovered-payable-in-worklist-and-batch.md) | Pending |

Phase 2 is independent of Phase 1 and may run first — it is the cheapest fix
and closes a live money risk. Phase 3 depends on Phase 1.

## Success Criteria

- [ ] Payable growth on a fee type with an unpaid live collection produces a
      provider-confirmed cancel of the old request followed by a new pushed
      request covering the full payable.
- [ ] A failed provider cancel aborts the whole replacement: no new request, no
      local state change beyond the recorded failure.
- [ ] A failed push after a successful cancel leaves an operator-visible state
      with provider evidence, never a silent gap.
- [ ] `active_slot_key` stays unique with unchanged timing semantics.
- [ ] A replacement never collects less than the request it replaced.
- [ ] Voiding a charge with a live DNG request in **any** holding status either
      cancels at the provider or blocks.
- [ ] The batch worklist shows a partially-covered student instead of skipping
      them, scoped to the same semester and campus the push would target.
- [ ] A runtime kill-switch disables automatic replacement without a deploy.

## Rollback

Automatic replacement sits behind a config flag
(`config/finance.php` → `dng.auto_replace_stale_collection`, default off until
soak). Disabling it restores today's `holdForReview` behaviour immediately.

No schema change and no slot-semantics change means rolling back the code
leaves no unreachable rows — the failure mode the push-over design would have
created. Requests already replaced stay terminal and correct.

## Prior work

- `8c46718a7` — repaired DNG cancellation entirely. Payer fields were read with
  the wrong key casing, so every cancel got `403 Email không để trống`; and
  cancelling never returned reserved installments to `pending`. **Phase 1
  depends on that commit**: cancel-then-push is only viable because cancel now
  works and releases installments back to a state the new reservation can
  adopt.

## Red Team Review

### Session — 2026-08-18

3 hostile reviewers (Security Adversary, Failure Mode Analyst, Assumption
Destroyer), 30 raw findings, 18 after deduplication. All three converged
independently on the `item_id` identity problem and on the slot-claim
regression.

**Outcome: the plan's core mechanism was replaced, not patched.** Push-over
became cancel-then-push (see "Design decision" above), which dissolves C1-C5
rather than mitigating them.

| # | Finding | Severity | Disposition | Applied to |
|---|---------|----------|-------------|------------|
| C1 | `cancelRecord` proves DNG keys records on `item_id`; a fresh `item_id` creates a second record instead of replacing | Critical | Accept | Design decision — mechanism replaced |
| C2 | Post-push slot claim removes the only pre-provider-call cross-process guard | Critical | Accept | Design decision — slot timing unchanged |
| C3 | Failed push yields `unknown_outcome` (a holding status) → two holding rows, unordered `first()` → deadlock or duplicate-key | Critical | Accept | Dissolved; Phase 1 creates the new row only after the old is terminal |
| C4 | Releasing installments while the new collection covers them enables a third push (9.000.000 for a 6.000.000 debt) | Critical | Accept | Dissolved; cancel releases to `pending` *before* reserve, so the new reservation adopts them |
| C5 | Fallback "restrict supersession to same semester" is impossible — `slotKey` has no semester and the index is single-column | Critical | Accept | Dissolved; no supersession predicate exists now |
| C6 | Phase 2 targeted `CancelFinanceObligationAction`, which has no caller; the real hole is the void guard's status list | Critical | Accept | Phase 2 re-anchored |
| H7 | `item_id` sequence drift breaks exact-retry idempotency | High | Accept | Dissolved; sequence advances only on terminal-then-new, as today |
| H8 | "Closing transaction fails" mitigation described a state the code cannot produce | High | Accept | Dissolved; no closing transaction |
| H9 | Webhook mitigation cited the already-cancelled branch, not the in-window one | High | Accept | Phase 1 — cancel completes before push, so the window is the existing cancel window |
| H10 | Batch caps to the next installment → a smaller collection can replace a larger one | High | Accept | Phase 1 — amount guard |
| H11 | `createCancellationReplacement` rows (pending, no targets) would be replaced, losing the remaining balance | High | Accept | Phase 1 — excluded from automatic replacement |
| H12 | Void guard status list omits `unknown_outcome` / `needs_review` | High | Accept | Phase 2 |
| H13 | Phase 3 `uncovered` mixes scopes: balance is semester-scoped, `active_dng` is not filtered by semester/campus/rail | High | Accept | Phase 3 |
| H14 | Requests with no reservation targets compute `uncovered` = full payable → duplicate push | High | Accept | Phase 3 — fail closed |
| H15 | No rollback, kill-switch, or data-migration story | High | Accept | Plan — Rollback section |
| H16 | Batch replacement records no actor, has no cap, no confirmation | High | Accept | Phase 3 |
| M17 | Four cited line numbers wrong (shifted by `8c46718a7`) | Medium | Accept | All phases re-anchored to symbols |
| M18 | Evidence offered for the semester assumption was circular (a Swinx query, not provider behaviour) | Medium | Accept | Removed; the assumption itself is gone |
| M19 | Void of one charge in a multi-charge request cancels the siblings' collection | Medium | Accept | Phase 2 |
| — | "Worked example unverified" | — | **Reject** | Verified via `artisan tinker`; the reviewer used `dev.sh mysql -e`, which does not forward args and returns empty |

### Whole-Plan Consistency Sweep

Decision delta applied across all four files: push-over → cancel-then-push;
`superseded_by` evidence → provider-confirmed `cancel_pushed_to_dng`; slot
timing unchanged; open question 1 (semester matching) and open question 2 (no
confirmation channel) both **removed** — the explicit cancel makes provider
replace semantics irrelevant. Phase 2 re-anchored from
`CancelFinanceObligationAction` to `VoidFinanceChargeAction` +
`ProcessFinanceCancellationOperationAction`. All bare line-number citations
replaced with symbol names.

No unresolved contradictions remain between `plan.md` and the phase files.

## Open questions

1. **`CancelFinanceObligationAction` appears to be dead code.** It is bound in
   `FinanceServiceProvider` but nothing in `app/` or `tests/` resolves
   `FinanceObligationCancellationContract`; the live Academic path uses
   `FinanceCancellationOperationRequestContract`. Phase 2 assumes it is dead
   and leaves it alone. Confirm before deleting it in a later cleanup — a
   dynamic resolution outside `app/` would change that.
2. **Soak period before the kill-switch defaults on.** The flag ships off. How
   many replacements should run under observation, and who watches them, before
   it becomes the default?
3. **Request `#425` (AUH15442) is still live at DNG**, sitting in
   `needs_review` and holding its slot. Phase 1 fails closed on
   `needs_review`, so it needs a human either way. Decide whether to resolve it
   before or after Phase 1 ships.

<!-- slug: dng-push-over-collection-replacement -->
