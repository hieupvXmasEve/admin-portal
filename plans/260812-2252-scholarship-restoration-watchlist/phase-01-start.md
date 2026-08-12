---
phase: 1
title: "Partial restoration schema & carry-forward"
status: done
priority: P1
effort: "1d"
dependencies: []
---

# Phase 1: Partial restoration schema & carry-forward

## Overview

Add `restored_amount` to restoration proposals and make the carry-forward
path honor an approved partial restoration in all three money consumers.
Pure Finance-module work; no HTTP, no UI.

## Requirements

- Functional: approved proposal with `restored_amount` set ⇒ later semesters
  discount from `restored_amount`; NULL ⇒ full restore (today's behavior).
- Non-functional: adjustment row never mutated; `applied` stays terminal;
  no behavior change for students without proposals.

## Architecture

Exclusion rule in `GetUnresolvedPriorAdjustmentQuery` narrows from
"has approved proposal" to "has approved FULL-restore proposal"
(`restored_amount IS NULL`). Partially-restored rows keep flowing to the three
consumers; effective amount resolved via a model helper so the resolver stays
single-source.

```
prior applied adjustment
  ├─ approved proposal, restored_amount NULL  → excluded (full award)
  ├─ approved proposal(s), restored_amount set → carried, effective = LATEST approved restored_amount
  └─ no approved proposal                     → carried, effective = adjusted_amount
```

Repeat proposals (validation decision): duplicate guard in
`CreateRestorationProposalAction.php:39-49` narrows from "any ACTIVE_STATUSES
proposal" to "a `pending_approval` proposal OR an approved FULL restore".
After an approved partial, a new proposal is allowed with floor = latest
approved `restored_amount` (replaces `adjusted_amount` as the lower bound).

## Related Code Files

- Create: `database/migrations/2026_08_12_XXXXXX_add_restored_amount_to_scholarship_restoration_proposals_table.php`
- Modify: `app/Modules/Finance/Models/ScholarshipRestorationProposal.php` (cast, fillable)
- Modify: `app/Modules/Finance/Models/ScholarshipSemesterAdjustment.php` — add `effectiveAdjustedAmount(): float` (reads latest approved proposal's `restored_amount`, falls back to `adjusted_amount`; document unit = `original_type` value, "remaining value")
- Modify: `app/Modules/Finance/Queries/GetUnresolvedPriorAdjustmentQuery.php:39-40` — exclusion becomes approved AND `restored_amount IS NULL`; eager-load approved proposal on returned row (avoid N+1 in batch consumer)
- Modify: `app/Modules/Finance/Support/ScholarshipDiscountResolver.php:58-82` — `resolveAdjusted()` uses `effectiveAdjustedAmount()`; keep clamps ([0, base], never above unadjusted)
- Modify: `app/Modules/Finance/Actions/CreateRestorationProposalAction.php` — accept optional `restoredAmount`; validate `floor < restoredAmount < original_amount` where floor = latest approved `restored_amount` ?? `adjusted_amount`; narrow duplicate guard (:39-49) to pending OR approved-full
- Delete: `app/Console/Commands/EvaluateScholarshipRestorations.php` + its scheduler registration (locate in console kernel/routes) + its test file — restoration is UI-only (validation decision); keep `ScholarshipRestorationVerdictQuery` (watchlist uses it)
- Verify (no code change expected): `PreviewMajorChargeGenerationQuery.php:365-375`, `CreateFinanceChargeAction.php:214-238`, `app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php:488-537` — all route through `resolveAdjusted`
- Tests: extend `tests/Feature/Finance/` restoration + carry-forward test files (locate existing ones for `GetUnresolvedPriorAdjustmentQuery` / `ScholarshipDiscountResolver` and add cases in place)

## Implementation Steps

1. Migration: nullable `restored_amount` decimal(12,2) + comment stating unit
   convention. Run `./scripts/dev.sh artisan migrate`.
2. Model changes (proposal cast/fillable; adjustment `effectiveAdjustedAmount()`).
3. Query exclusion change + eager-load.
4. Resolver switch to effective amount.
5. Action input + narrowed guard in `CreateRestorationProposalAction`;
   remove console command + scheduler entry + command tests.
6. Tests: percentage-type partial, fixed-type partial, NULL full-restore
   regression, boundary rejects (≤ floor, ≥ original), repeat-after-partial
   allowed with raised floor, repeat-after-full blocked, pending blocks,
   batch consumer uses latest approved restored amount.

## Todo

- [x] Migration + model casts
- [x] `effectiveAdjustedAmount()` helper (latest approved proposal)
- [x] Query exclusion + eager-load
- [x] Resolver uses effective amount
- [x] Create action: `restoredAmount` + floor validation + narrowed guard
- [x] Console command + scheduler entry + tests removed
- [x] Tests all listed cases green

## Success Criteria

- [x] Partial restore changes discount in preview + single + batch generation tests
- [x] Existing restoration/carry-forward test suite green without edits (except added/removed cases)
- [x] `finance:evaluate-scholarship-restorations` no longer registered

## Risk Assessment

- **Money math drift** between 3 consumers — mitigated: all go through
  `resolveAdjusted`, single helper; test each consumer anyway.
- **N+1** on batch generation from proposal lookup — mitigated by eager-load
  in the query.
- Test DB gotcha: never `--env=testing` (hits dev DB `asia`); use project
  test scripts.
