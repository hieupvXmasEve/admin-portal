# Gold Ledger: Decimal Debt to Integer, Debt-Forbidden

**Date**: 2026-08-01 14:08
**Severity**: High (money-critical, live production table)
**Component**: Gold wallet ledger (`gold_transactions`, `student_wallets`, `events.gold_reward_amount`), `GoldService`, staff wallet-adjustment endpoint
**Status**: Resolved (Phase 1 of merchandise-store plan)

## What Happened

Converted the live Gold wallet from a decimal, negative-balance-capable ledger to an integer, debt-forbidden one. Full detail: `plans/reports/cook-260801-1302-merchandise-store-phase-1.md`. Plan: `plans/260801-0203-merchandise-store/index.md` + `phase-1-gold-ledger-foundation.md`. Commit `5ad4b166`.

Three per-table, idempotent migrations (enum→varchar allow-list, decimal→INT, unsigned balance). `GoldService` now locks the wallet row inside every write transaction, stamps `balance_before`/`balance_after` + `performed_by`, and got a new atomic `reclaimGold` that clamps to balance and write-offs the shortfall instead of going negative. Also closed an ungated Gold-mint endpoint (RT-1) behind a real `adjust_gold_wallet` permission check — that one had zero authorization before this.

12/12 new `tests/Feature/Gold/` tests pass. Code review caught and fixed a TOCTOU in reclaim (M1) and a destructive migration `down()` that would've corrupted the enum on rollback (M2).

## The Brutal Truth

This table let balances go negative in production. Anyone hitting the mint endpoint had no permission gate — a real, exploitable hole, not a theoretical one. And the reclaim path before this fix had a read-then-deduct race: a concurrent spend between the read and the deduct could blow the balance clamp. That's the kind of bug that sits quiet for months and then corrupts a batch of student wallets during a promo rush. Converting a live money table's column types is not something to feel good about doing casually — every migration here had to be idempotent and pre-flight-safe because there was no test-DB dry run luxury; it had to work against real rows with real fractional junk and real negative balances already sitting there.

## Technical Details

- Reconciliation invariant enforced going forward: `balance == SUM(amount)`. Write-off entries carry `amount = 0` (magnitude goes in `notes`) specifically to not break this invariant — a design constraint that took a beat to land on.
- `migrate:fresh --database=testing` was a red herring for a full day (D11): it only sets the *migration* connection, not Laravel's *default* connection, so Telescope's migration silently ran against `asia` instead of `db_test`. Root cause was invocation, not the canvas enum everyone suspected. Fix: `-e DB_CONNECTION=testing` on the artisan call, not code.
- Legacy negative wallets get clamped to 0 with a `write_off` backfill *before* the unsigned ALTER runs, so the schema change itself never fails on bad data — but those write-offs are amount-0, meaning old wallets don't satisfy the invariant retroactively (documented as L3, accepted).
- Fractional legacy rows (`amount != FLOOR(amount)`) are logged loudly by the migration, not silently rounded — deploy gate M3, still open, needs a prod pre-flight count before this ships.

## What We Tried

- Read-then-deduct for reclaim (rejected post-review: TOCTOU under concurrent spend) → atomic single-lock `reclaimGold` instead.
- Migration `down()` that reverted varchar back to enum (rejected: would corrupt/abort on the new value-set once new types exist) → down() keeps varchar, only the lossless INT→DECIMAL part reverts.

## Root Cause Analysis

The original design allowed decimal amounts and negative balances with no row locking — classic "it worked in the demo" ledger that never got hardened before real money (well, Gold, but same discipline) started flowing through it. The mint endpoint had no `authorize()` at all — an oversight from whenever that route was first wired, never caught because nobody wrote a permission test for it until now.

## Lessons Learned

- Any balance/ledger table needs the debt-forbidden + row-lock + audit-trail pattern from day one, not bolted on after production data already has negative rows to clean up.
- `migrate:fresh --database=X` connection-scoping is a trap — it doesn't touch the app's default connection. If a migration seems to hit the wrong DB, check which connection actually ran it before assuming the migration file is wrong.
- Reclaim/refund logic that reads a balance then writes based on that read needs the same lock discipline as a deduct — a "corrective" write path is not exempt from races just because it feels like cleanup code.

## Next Steps

- Owner: whoever runs the prod deploy — pull `amount != FLOOR(amount)` and `balance < 0` counts on prod before running these migrations (M3), decide round-vs-write-off with product.
- Get `gold_transactions` row count to size the ALTER maintenance window.
- FE (student portal, Phase 3b) must switch to consuming integer JSON (`balance`/`amount` no longer `"123.00"` strings) — contract change is live in this commit, FE hasn't caught up yet.
- Two pre-existing Architecture-test failures (`MigrationDebtInventoryTest` baseline drift, `AcademicNotificationBoundaryArchTest` self-abort) are unrelated to this work — confirmed not caused by this diff, but still open questions on whether/when to fix on this branch.
