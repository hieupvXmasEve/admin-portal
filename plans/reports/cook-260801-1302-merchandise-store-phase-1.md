# Phase 1 — Gold Ledger Foundation — Completion Report

Date: 2026-08-01
Plan: [260801-0203-merchandise-store](../260801-0203-merchandise-store/index.md) · Phase [1](../260801-0203-merchandise-store/phase-1-gold-ledger-foundation.md)
Branch: `claude/merchandise-store-review-840d9b`
Status: **DONE** (12/12 new tests pass; code-reviewed; no CRITICAL/HIGH forward-path bug)

## What shipped

Money-critical: converted the live Gold wallet ledger to an integer, debt-forbidden design with row-locking + audit trail.

**Migrations** (per-table, idempotent via `information_schema`, pre-flight):
- `..._convert_gold_transactions_to_integer_ledger` — `type`/`source_type` enum→varchar(32); `amount` decimal→INT signed; add `balance_before`/`balance_after` unsigned nullable; add `performed_by` FK→users (ON DELETE SET NULL). Pre-flight logs fractional-amount rows.
- `..._convert_student_wallets_balance_to_unsigned_integer` — clamps existing `balance < 0` rows to 0 with a `write_off` audit entry BEFORE the unsigned ALTER, then `balance`→UNSIGNED INT.
- `..._convert_events_gold_reward_amount_to_integer` — `gold_reward_amount` decimal→UNSIGNED INT (largest Gold source; stops silent truncation).

**GoldService** — every write path locks the wallet row (`lockWalletForUpdate`) inside its transaction; records `balance_before/after`; `performed_by` threaded; `getBalance():int`; `adjustBalance` non-negative guard (unsigned floor); `deductGold` re-checks under lock; `bulkAddGold` chunked to one transaction per student; new atomic `reclaimGold` (clamp + write_off under a single lock); `writeOff` primitive.

**Reclaim redesign** (`EventParticipationOperations::reclaimGoldReward`) — no longer writes a negative balance; delegates to `reclaimGold`: deduct `min(balance, amount)`, write off the shortfall (amount 0). Award call sites cast `(int)` and pass `auth()->id()` as `performed_by`.

**Mint-permission hole closed** (RT-1) — added `adjust_gold_wallet` to `config/permission.php` (config-driven seeder + `permissions:sync` pick it up automatically; secure-by-default: only Super Admin has it). Route `can:adjust_gold_wallet` + real `AdjustWalletBalanceRequest::authorize()`.

**Model/resource/controller** — GoldTransaction int casts + `TYPES`/`SOURCE_TYPES` allow-lists + `performedBy` relation; Event cast integer; resources drop `number_format` (amounts/balance now int JSON); GoldTransactionController type filter uses the allow-list.

## Tests (tests/Feature/Gold/, 12 pass)
- `GoldServiceTest` — add/deduct/adjust/writeOff, insufficient-throw, below-zero-throw, reconciliation invariant (`balance == SUM(amount)`), lock-path arch assertion.
- `ReclaimGoldRewardTest` — balance 10, reclaim 50 → balance 0, one −10 spend + one write_off(0, notes “40”), invariant holds.
- `AdjustWalletBalanceAuthorizationTest` — route gated + slug registered; 403 without grant; Super Admin adjusts and writes an integer ledger entry.

Run: `docker run --rm -v <repo>:/app -v swinx-dev_composer_vendor:/app/vendor --network swinx-dev_default -w /app -e DB_CONNECTION=testing swinx-dev-app:latest php artisan test tests/Feature/Gold/`

## D11 (migrate db_test) — RESOLVED, no code change
Root cause was NOT the canvas enum (canvas migrates fine). `migrate:fresh --database=testing` only sets the migration connection, not the **default** connection, so Telescope's migration targeted `asia`. Under `php artisan test` phpunit.xml sets `DB_CONNECTION=testing` as default, so everything routes to db_test. **Fix = invocation, not code:** run test-DB migrations with `-e DB_CONNECTION=testing`. Full `migrate:fresh` is green that way.

## Code review (code-reviewer agent)
No CRITICAL/HIGH forward-path money bug. Fixed in this phase:
- **M1** reclaim TOCTOU (read-then-deduct could abort the clamp under a concurrent spend) → replaced with atomic `reclaimGold` (single lock).
- **M2** migration `down()` unconditionally reverted varchar→enum, which would corrupt/abort on the new value-set → down() now keeps varchar (safe superset); only the lossless INT→DECIMAL revert remains.
- **L4** `write_off` label rendered “Write_off” → now “Write off”.

Accepted / deferred (documented, not bugs):
- **M3** fractional legacy rows round independently on convert → **deploy runbook gate**: run the pre-flight counts on prod and reconcile before ALTER (already listed in plan Unresolved Q1). Migrations log fractional counts loudly.
- **L1** reclaim deduction typed `spend` inflates `total_spent` stat (display only).
- **L2** reclaim notification reports full award, not reclaimed amount (cosmetic).
- **L3** clamped-negative-wallet write_off has amount 0, so those legacy wallets don’t satisfy `balance == SUM(amount)` — invariant is forward-looking (new entries), per acceptance criteria wording.

## Contract changes (intentional)
- `GoldService` amounts `float`→`int`; new `?int $performedBy`; `getBalance():int`; new `reclaimGold`/`writeOff`. All in-repo callers updated.
- Wallet/transaction resource JSON: `balance`/`amount`/`amount_raw` now integers (`123`) not strings (`"123.00"`); `display_amount` `"+100"`/`"0"`. **FE (student portal, Phase 3b) must consume the int shape.**

## Pre-existing failures on this branch (NOT caused by Phase 1)
- `tests/Feature/Architecture/MigrationDebtInventoryTest` — baseline drift `shared_model_imports 289→304`, `literal_frontend_urls 43→44`. Scanner source roots = `app, routes, resources/js, config/mcp.php` (not tests/migrations). My diff adds zero `use App\Models` lines and touches zero `resources/js` — both drifts predate this work.
- `tests/Feature/Architecture/AcademicNotificationBoundaryArchTest` — self-aborts (exit 2, 0 assertion failures) in isolation; no Gold linkage. This is what makes the whole `tests/Feature/Architecture/` dir abort mid-run.

## Unresolved questions
1. Prod counts for `amount != FLOOR(amount)` / `balance < 0` and the reconciliation rule (round vs write_off) — pre-flight before deploying migrations (M3).
2. `gold_transactions` row count + maintenance window for the ALTERs.
3. Should the two pre-existing Architecture-test failures be fixed on this branch or tracked separately?
