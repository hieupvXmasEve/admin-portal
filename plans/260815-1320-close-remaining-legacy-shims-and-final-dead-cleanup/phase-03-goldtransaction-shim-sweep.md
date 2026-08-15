---
phase: 3
title: "GoldTransaction shim sweep"
status: pending
priority: P1
effort: "1-1.5d"
dependencies: [1]
---

# Phase 3: GoldTransaction shim sweep

## Overview

Delete the GoldTransaction shim (owner: Merchandise). Real blocker (corrected by red-team): `app/Modules/Engagement/Actions/EventParticipationOperations.php` runs **three live Eloquent queries** against the Merchandise-owned model — not just constants/typehints:
- `:502-505` — pending-transaction existence check guarding gold **reclaim** (money-safety: prevents double-reclaim)
- `:584-586` — `getGoldRewardAuditTrail()`: filtered query + `->with(['student'])`, six optional filters, currently unbounded `->get()`
- `:621-624` — aggregates (`sum('amount')`, TYPE_EARN/TYPE_SPEND) over that result

Plus `:225,295,433,1395` constants and `:380` typehint. Engagement cannot import `App\Modules\Merchandise\Models\GoldTransaction` (`cross_context_concrete_imports` = exact, ceiling 0).

## Requirements

- Functional: gold award/reclaim + audit trail behavior unchanged; reclaim guard semantics identical.
- Non-functional: no Merchandise model import inside Engagement; **raw `DB::table('gold_transactions')` fallback inside Engagement is forbidden** (same coupling, invisible to arch rule, bypasses casts/scopes).

## Architecture

Move the queries to the owner side; Engagement keeps orchestration only:
- **Reclaim guard** (`:502`): move into `GoldService` as a public method (e.g. `hasPendingEventTransaction(studentId, eventId, since)`). `GoldService` is global `app/Services` — exempt from cross-context rule (rule fires only on `namespace App\Modules\…` files) and already owns add/reclaim.
- **Audit trail** (`:584-624`): move query + aggregation into `GoldService` (e.g. `getEventRewardAuditTrail(filters)`), preserving all six filters. Do NOT design a `->get()`-shaped shared contract; keep pagination/limit concern noted at the moved method.
- **Constants**: pass source as parameter or expose via `GoldService`; `SOURCE_EVENT` string value must stay byte-identical in DB writes (assert in test).
- **Typehints** (`:380` audit logger): accept the transaction untyped or extract scalars at the boundary.
- Global files (`GoldTransactionController`, `GoldService`) + tests: plain namespace swap. Both are `frozen_services`/pinned paths in `config/migration_debt_paths.php` (~:29, :69) — modify in place is fine; do NOT move logic out of them in this phase (that trips the frozen-path snapshot).

## Related Code Files

- Modify: `app/Modules/Engagement/Actions/EventParticipationOperations.php` — remove `use App\Models\GoldTransaction`; reroute 3 query sites through GoldService; untype audit param
- Modify: `app/Services/GoldService.php` — receive the 2-3 moved methods; namespace swap import
- Modify (add explicit `use App\Modules\Merchandise\Models\GoldTransaction`): `app/Models/StudentWallet.php` (~51 — wallet `transactions()` binds bare name), `app/Models/Student.php` (~370)
- Modify (namespace swap): `app/Http/Controllers/Api/GoldTransactionController.php`, `tests/Feature/Merchandise/Redemption/{RedemptionCheckoutTest,RedemptionRefundAndStateMachineTest,RedemptionAccessControlTest}.php`, `tests/Feature/Merchandise/Reports/MerchandiseReportDataTest.php`, `tests/Feature/Gold/{ReclaimGoldRewardTest,GoldTransactionOwnershipTest,GoldServiceTest}.php`
- Delete: `app/Models/GoldTransaction.php`
- Modify: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` — shrink lists
- Modify: `tests/Feature/Architecture/MerchandiseModelPlacementArchTest.php` (~:63 asserts shim exists) — flip to shim-deleted per `UploadModelPlacementArchTest.php:46-53` pattern

## Implementation Steps

1. Move reclaim-guard + audit-trail queries into `GoldService`; keep behavior test-pinned (`ReclaimGoldRewardTest`, gold audit tests) before and after.
2. Refactor `EventParticipationOperations`: constants → params, typehint → untyped/scalars, queries → GoldService calls; drop the import.
3. Add 2 explicit `use` statements in `app/Models` (StudentWallet, Student).
4. Namespace-swap controller + 7 test files.
5. Confirm zero importers by BOTH detectors (string grep + bare `GoldTransaction::` inside `namespace App\Models` files).
6. Delete shim; shrink arch lists; flip Merchandise placement test — same commit.
7. Run: `tests/Feature/Gold`, `tests/Feature/Merchandise`, Engagement event tests, `tests/Feature/Architecture`.

## Success Criteria

- [ ] Shim deleted; no Merchandise model import AND no raw gold_transactions SQL inside `app/Modules/Engagement/`
- [ ] Reclaim double-guard behavior identical (test-pinned); `SOURCE_EVENT` DB value byte-identical
- [ ] Wallet `StudentWallet::transactions()` green
- [ ] Gold + Merchandise + Architecture tests green (no new failures)

## Rollback

Restore shim + revert arch/placement test edits together. Serialized with phases 2/4/5 on `DeprecatedModelShimArchTest`.

## Risk Assessment

Money-adjacent: the reclaim guard move must be covered by an explicit double-reclaim test before refactor. Audit-trail method stays unbounded as today — note ceiling at the moved method (`// ponytail`-style), don't fix scope here.
