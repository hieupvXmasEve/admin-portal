# Credit Offset Projector Extraction

## Status
Done. Implemented, code-reviewed (approve), tested.

## Outcome (actual)
- `CreditOffsetProjector` created; Major and EGC previews both use it.
- EGC preview now populates `unapplied_credit`/`credit_offset_projected` on
  both eligible branches (fresh/deferred AND reissue).
- Review flagged a real gap during implementation: deferred EGC blocks are
  charged unconditionally by `GenerateEgcChargesAction` but were excluded from
  the projected net amount, understating (or zeroing) the offset on rows with
  `has_deferred_blocks = true`. Fixed by adding deferred blocks' resolved fee
  to `netAmount` in the fresh/deferred branch.
- Added a reissue-branch test (review flagged it as untested).
- `BatchChargeCommitTest > it allows an EGC-only operator to commit an
  unchanged EGC preview token` fails with 404 — reproduced independently on
  the pre-change baseline (files stashed), confirmed pre-existing/unrelated.
- Also independently reproduced a pre-existing cross-file test-pollution flake
  (off-by-one `eligible_count`) between two untouched files
  (`BatchChargePreviewTest.php` + `GenerateEgcChargesTest.php` run together) —
  unrelated to this change, not fixed (out of scope).
- Deferred: `CreditOffsetProjector` binding as `scoped` + Fee Monitor's unused
  extra query cost — reviewer marked "not required to land."

## Outcome
Extract Major batch-charge preview's inline credit-offset projection math into a
shared `app/Modules/Finance/Support/CreditOffsetProjector.php`, reused by both
`PreviewMajorChargeGenerationQuery` (HP) and `PreviewEgcChargeGenerationQuery`
(EGC). EGC batch-charge preview (`/finance/batch-studio/charges` preview,
`fee_category=egc`) currently always returns `unapplied_credit: null` and
`credit_offset_projected: null` because the classifier never computes them —
HP's preview does. Root cause confirmed via code trace, see
`plans/reports/*-260817-1449-credit-offset-egc-preview-gap.md`-equivalent
consult (ak-ask session).

## Non-goals
- Do NOT change `CreateFinanceChargeAction::offsetWithUnappliedCredit()` (real
  commit-time offset). It already runs identically for HP and EGC charges
  (gated only by `finance_settings.credit_offset_enabled` +
  `credit_offset_min_balance`, no `charge_type` branch) — that part is correct
  today. This change is preview-display parity only.
- Do NOT reconcile the known divergence between the gate-check balance
  (`StudentFinanceSettlementPositionReader`, subtracts
  `PaymentSurplusDisposition`) and the real per-Payment allocation loop in
  `CreateFinanceChargeAction::applyUnappliedCreditToCharge()` (raw
  `Payment::unapplied_amount`, disposition-blind). Both previews will keep
  today's approximation — unresolved product question, out of scope here.
- Do NOT touch `non_academic` batch preview (no credit-offset concept there
  today).

## Constraints
- Preserve Major's current preview output/values exactly (same
  `StudentFinanceSettlementPositionReader::unappliedCashForStudents()` batch
  call — avoids N+1 across a semester's worth of students).
- New EGC fields must reuse the identical gate/cap formula Major uses:
  `credit_offset_enabled && unappliedCash > 0 && unappliedCash >= min_balance`
  → `min(unappliedCash, netAmount)`.
- No change to `AssembleBatchChargePreviewQuery` — it already reads
  `row['unapplied_credit']` / `row['credit_offset_projected']` generically.

## Acceptance Criteria
1. `CreditOffsetProjector` exists in `app/Modules/Finance/Support/`, wraps
   `StudentFinanceSettlementPositionReader` for the batch lookup and exposes
   the gate/cap projection as a pure method.
2. `PreviewMajorChargeGenerationQuery` uses the new service instead of its
   inline block; HP preview output is byte-identical to before for the same
   inputs.
3. `PreviewEgcChargeGenerationQuery` uses the new service; EGC batch-charge
   preview (`fee_category=egc`) now returns non-null `unapplied_credit` /
   `credit_offset_projected` for eligible rows (both fresh and reissue
   branches) when the student has unapplied cash and the setting is enabled.
4. Existing Finance preview/batch tests pass; new unit coverage for
   `CreditOffsetProjector::project()` (enabled/disabled, below/above min
   balance, cap at netAmount).
5. No change to `CreateFinanceChargeAction` or any commit-time behavior.

## Files
- New: `app/Modules/Finance/Support/CreditOffsetProjector.php`
- Edit: `app/Modules/Finance/Queries/Major/PreviewMajorChargeGenerationQuery.php`
- Edit: `app/Modules/Finance/Queries/Egc/PreviewEgcChargeGenerationQuery.php`
- New test: `tests/Unit/Finance/Support/CreditOffsetProjectorTest.php` (or repo's
  matching convention if `tests/Unit/Finance/Support` doesn't exist yet)

## Risk / Rollback
Low risk, additive/preview-only. Rollback = revert the 3 files; no migration,
no schema, no public contract change (batch preview response shape already had
these keys for HP, just null for EGC — no consumer breaks either way).
