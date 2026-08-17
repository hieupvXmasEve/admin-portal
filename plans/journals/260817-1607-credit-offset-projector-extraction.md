# EGC batch-charge preview was silently missing credit-offset deduction

**Date**: 2026-08-17 16:07
**Severity**: Low (display gap, not a money bug) — Medium note on two pre-existing test flakes hit while verifying
**Component**: Finance/BatchStudio (charge preview), Finance/Support
**Status**: Resolved (shipped); two unrelated flakes found, unowned

## What Happened

`/ak-ask` consult asked why EGC batch-charge preview never showed `unapplied_credit` / `credit_offset_projected` the way HP/Major preview does. Root cause: `PreviewEgcChargeGenerationQuery::classify()` never computed those fields at all — HP's sibling query (`PreviewMajorChargeGenerationQuery`) had the gate+cap math inlined, EGC's never got it when EGC preview was built. Confirmed via read of `CreateFinanceChargeAction::offsetWithUnappliedCredit()` that the real commit-time offset already applies identically to both charge types (no `charge_type` branch) — so this was purely a preview-display gap, not a correctness or money bug. Good, no panic needed.

User then asked for the fix via `/ak-cook`: extract a shared `CreditOffsetProjector` service instead of duplicating gate+cap logic a second time.

## The Brutal Truth

The annoying part wasn't the fix — it was that the code reviewer caught a real accuracy bug hiding inside the "just wire up the same logic" task: `GenerateEgcChargesAction` charges every deferred EGC block unconditionally, but the preview's `netAmount` excluded deferred blocks entirely. So even after wiring the projector in, rows with `has_deferred_blocks=true` would've shown an understated (sometimes zero) projected offset — a *new* subtly-wrong number shipped under the banner of "fixing the missing number." Would not have caught this without the mandatory review pass; worth remembering that "extract and reuse" tasks aren't risk-free just because the target logic is already proven.

Also spent real time chasing a `BatchChargeCommitTest` 404 that turned out to be nothing to do with this change — reproduced identically on a stashed baseline. Don't trust a subagent's first guess ("missing mock") without reproducing against clean HEAD first.

## Technical Details

- New `app/Modules/Finance/Support/CreditOffsetProjector.php`: `unappliedCashForStudents()` (batch lookup, avoids N+1) + `project(unappliedCash, netAmount, FinanceSetting)` — pure gate+cap: `enabled && cash>0 && cash>=min_balance → min(cash, netAmount)`.
- `PreviewMajorChargeGenerationQuery` refactored to inject it via constructor (replaced direct `StudentFinanceSettlementPositionReader` dep) — verified byte-identical behavior, `MajorPreviewCreditOffsetTest.php` 5/5 still green.
- `PreviewEgcChargeGenerationQuery` wired via the file's existing lazy `??= app(...)` accessor pattern (no constructor in this file, 3 other deps already resolved this way). Both eligible-return branches (reissue + fresh/deferred) now populate the two fields.
- Accuracy fix: `netAmount` in EGC preview now sums deferred blocks' resolved per-level fee too, matching what `GenerateEgcChargesAction` actually charges.
- New `EgcPreviewCreditOffsetTest.php` (5 tests: disabled-setting default, full-absorb, cap-at-net-amount, below-min-balance, reissue branch via `seedBatchEgcBlockCharge` with voided blocks).
- New `CreditOffsetProjectorTest.php` (5 tests) — pure logic on `project()`, no DB. `StudentFinanceSettlementPositionReader` is `final`, can't Mockery-mock it; worked around by resolving a real container-built instance since `project()` never touches it anyway.

## What We Tried

- code-reviewer subagent pass: approved, no critical/high, but flagged the deferred-blocks netAmount gap (fixed) and missing test coverage on the reissue branch (fixed, added test).
- Investigated `BatchChargeCommitTest` 404 by git-stashing the 2 production edits and re-running against clean baseline — failed identically, confirmed pre-existing and unrelated.
- While chasing that, ran `BatchChargePreviewTest.php` + `GenerateEgcChargesTest.php` together (both untouched files) and got off-by-one `eligible_count` mismatches not present running either alone. Reproduces with `EgcPreviewCreditOffsetTest.php` entirely absent from the run, so confirmed unrelated to this change — likely some cross-test pollution in `StudentCollectionEligibilityReader` or similar not scoping cleanly across sequential tests in one PHP process. Not investigated further, out of scope for this task.

## Root Cause Analysis

EGC and HP/Major preview queries were built as parallel siblings rather than sharing logic from the start — HP got the credit-offset math inlined at some point, EGC never did. Classic copy-paste-diverge: two "preview a charge" queries with 90% identical projection logic, no shared abstraction, so a feature landed in one and silently never made it to the other. Extracting `CreditOffsetProjector` closes that gap and removes the future risk of a third preview query repeating the same omission.

## Lessons Learned

- When two sibling query classes clearly do the same projection with copy-pasted math, extract the shared piece the first time a bug surfaces in one of them — don't wait for a second omission.
- "Extract and reuse proven logic" tasks still need a full data-flow check against the *real* commit-time action, not just the preview query being extracted from — the deferred-blocks netAmount gap was invisible by only reading the two preview queries.
- Reproduce test failures against clean HEAD (stash + rerun) before accepting any explanation for why a test fails — saves chasing phantom fixes for pre-existing flakes.

## Next Steps

- Deferred as non-blocking per reviewer: bind `CreditOffsetProjector` as `scoped` to memoize `unappliedCashForStudents()` across Major+EGC calls within one request — relevant to `ListFeeMonitorQuery` which calls both previews but doesn't consume these fields (pure waste today, not urgent).
- Unowned: the pre-existing `BatchChargeCommitTest > it allows an EGC-only operator to commit an unchanged EGC preview token` 404 — reproduces on clean baseline, needs its own investigation.
- Unowned: cross-file test-pollution flake between `BatchChargePreviewTest.php` and `GenerateEgcChargesTest.php` (off-by-one `eligible_count` when run together, not alone) — root cause unknown, worth a dedicated investigation someday.

## Unresolved Questions

- Who owns the pre-existing `BatchChargeCommitTest` 404 fix?
- Who owns the `BatchChargePreviewTest` / `GenerateEgcChargesTest` cross-pollution flake?

Commit: `accdc1b42` on `dev` (unpushed). Plan: `plans/260817-1529-credit-offset-projector-extraction/plan.md` (status: Done).
