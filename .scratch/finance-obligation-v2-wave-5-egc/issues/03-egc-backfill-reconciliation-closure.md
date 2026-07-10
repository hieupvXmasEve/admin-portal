# 03 - EGC legacy backfill and reconciliation closure

Status: done
Depends on: 01, 02
Program PRD: ../../finance-obligation-v2-migration/PRD.md
Portal impact: none

## What to build

Backfill legacy EGC debit and reduction rows after the EGC intake and entitlement paths are live. The backfill must cover active and void EGC level-fee rows, EGC exempt credit rows, and any related retake discount evidence, then close the wave with a hard-gate reconciliation report.

## Acceptance criteria

- [x] Active and void EGC level-fee rows are linked to obligations with legacy provenance and no duplicate rows on rerun.
- [x] Legacy EGC credit or discount rows convert to the correct entitlement carrier with duplicate negative lines voided.
- [x] Reconciliation compares derived settlement before and after conversion for every backfilled row.
- [x] DNG EGC push reads existing payables and blocks missing obligations rather than creating charges.
- [x] Tests cover backfill idempotency, exception reporting, and unchanged EGC settlement totals.

## Delivered

- `BackfillLegacyEgcLevelFeeObligationsAction` + `finance:backfill-legacy-egc-level-fee-obligations` (hard-gate)
- `BackfillLegacyEgcExemptCreditEntitlementsAction` + `finance:backfill-legacy-egc-exempt-credit-entitlements`
- `BackfillLegacyEgcRetakeDiscountEntitlementsAction` + `finance:backfill-legacy-egc-retake-discount-entitlements`
- DNG HP guard: `tuition_term` + `egc_level_fee` require accepted obligations
- Tests: `tests/Feature/Finance/EgcBackfillDngClosureTest.php`

## Blocked by

- `.scratch/finance-obligation-v2-wave-5-egc/issues/01-egc-level-fee-intake-cutover.md`
- `.scratch/finance-obligation-v2-wave-5-egc/issues/02-egc-retake-exempt-entitlements.md`
