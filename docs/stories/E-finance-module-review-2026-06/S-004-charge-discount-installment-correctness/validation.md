# Validation

## Proof Strategy

Prove exact amount parity between preview and execute paths, and prove totals
reconcile after discount, installment, and DNG pivot operations.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Scholarship cap, EGC fee source, rounding remainder, casts |
| Integration | Charge generation, split installment, DNG batch request creation |
| Integration | DNG pivot from installment plan: multi-charge where balance is NOT proportional to installment amount; assert each pivot equals its installment (not balance-proportional), pivot links installment, Σ pivot == request == Σ linked installments |
| Integration | Void charge with linked installments is cancelled, blocked, or audited as designed |
| E2E | Smoke charge preview/execute page if UI output changes |
| Platform | Docker wrapper commands |
| Performance | Batch generation should not add uncontrolled per-student queries |
| Logs/Audit | Existing charge creation/void audit behavior preserved |

## Fixtures

- Scholarship greater than charge amount.
- Voided charge with same student/semester/type.
- EGC preview with page-local block override.
- Installment split followed by discount change.
- DNG request with multiple charge links and rounding remainder.
- Charge with linked unpaid and paid installments before void.

## Commands

```text
./scripts/dev.sh test --filter=Generate
./scripts/dev.sh test --filter=Installment
./scripts/dev.sh test --filter=Dng
./scripts/dev.sh artisan finance:audit-invariants --sample
./scripts/dev.sh artisan pint
./scripts/dev.sh npm run lint
git diff --check
```

## Acceptance Evidence

Implemented 2026-06-14. Product decisions taken before coding:

- **FIN-06 EGC fee source**: `Unit.base_fee` canonical, flat `15,000,000` fallback
  when a level has no unit/base_fee. Single resolver feeds preview + both execute
  paths (`EgcLevelFeeResolver`).
- **FIN-12 void → installments**: void cancels non-settled installments
  (pending + awaiting_payment → cancelled; paid → cancelled only when the charge
  is fully reversed). Backfill-first for legacy data; new invariant forbids live
  installments on voided charges.
- **UI-SAFE-3**: server-authoritative full-scope execute + honest projected
  block/amount totals in the preview summary and confirm banner.

### Changes (by finding)

| Finding | Change | Proof |
| --- | --- | --- |
| FIN-04/07 | `ScholarshipDiscountResolver` caps discount at charge; used by CreateFinanceCharge, batch/major execute, all 3 previews | `ScholarshipDiscountResolverTest`, `ChargeDiscountCorrectnessTest` |
| FIN-05 | `createChargeIfNotExists` filters `status='active'` | `GenerateChargeTransitionSameSemesterTest` "regenerates a tuition charge after the prior one was voided" |
| FIN-06 | `EgcLevelFeeResolver` (Unit.base_fee → 15M fallback) in dedicated + batch execute + both previews | `EgcLevelFeeResolverTest`, EGC preview "uses Unit.base_fee" |
| FIN-08 | `FinanceCharge.amount` cast `decimal:0 → decimal:2` | full Finance suite green (no regressions) |
| FIN-09 | `ReconcileChargeInstallmentsAction` recomputes pending installments on discount change; blocks when committed rows exceed net due | `InstallmentDiscountReconcileTest` (3 cases incl. rounding + block) |
| FIN-10 | DNG pivots distributed in cents, remainder to last → `Σ pivot == request amount` | `CreateBatchDngFromChargesActionTest` "keeps pivot sum exactly equal" |
| FIN-10b | Pivot reflects **linked installment** (not `charge.balance`); nullable `finance_charge_installment_id` FK on `dng_payment_request_charges`; ad-hoc override stays balance-proportional & unlinked. INV-14 (`request == Σ pivot`) + INV-15 (`linked pivot == installment`) | `BatchDngInstallmentAwareTest` bundle test asserts A=10M/B=6M + installment links + Σ; migration `2026_06_14_110000_add_installment_link_to_dng_payment_request_charges` |
| FIN-33 | `buildItemId` adds random suffix (no same-second collision) | covered by batch DNG suite |
| FIN-12 | void cancels non-settled installments + `cancelled_installments` in result; `SettleInstallmentFromDngAction` no longer resurrects cancelled rows; `finance:backfill-voided-charge-installments`; INV-13 | `VoidChargeInstallmentsTest` (4 cases) |
| UI-SAFE-3 | `PreviewEgcChargeGenerationQuery` projected totals + EGC fee resolver; `GenerateCharges.vue` honest confirm banner | EGC preview "reports full-scope projected totals" |

### Commands run

```text
./scripts/dev.sh test  (tests/Feature/Finance)
  → 340 passed / 26 failed. All 26 failures are PRE-EXISTING (verified by
    stash-baseline: baseline = 26 failed / 324 passed; +16 new passing tests,
    0 new failures). Pre-existing failures: GenerateEgcChargesTest (stale tests
    call action without due_date + name-casing) ×11, DngReconciliationServiceTest
    (constructor arity drift from S-001) ×3, AutoAllocate/PaymentPages/
    StudentFinanceDngAccess route/auth/serialization ×12.
./scripts/dev.sh composer exec -- pint --dirty   → 9 style fixes applied, clean
./scripts/dev.sh pnpm exec eslint GenerateCharges.vue   → clean
git diff --check   → clean
./scripts/dev.sh artisan finance:audit-invariants --sample
  → INV-13 = 6 live installments on voided charges (legacy data; sample ids
    1051,1052,1067,1093,1094). finance:backfill-voided-charge-installments
    --dry-run confirms it would cancel 6.
  → INV-14 (request == Σ pivot) = 0, INV-15 (linked pivot == installment) = 0
    on the dev dataset after the FIN-10b pivot fix + migration.
```

> Note: full `npm run type-check` OOMs in the dev container (Node heap limit,
> pre-existing infra constraint); the changed Vue file was linted individually.

### Remediation to run on each environment

```bash
./scripts/dev.sh artisan finance:backfill-voided-charge-installments --dry-run
./scripts/dev.sh artisan finance:backfill-voided-charge-installments
./scripts/dev.sh artisan finance:audit-invariants   # INV-13 should read 0
```

### Follow-up review fixes (post-implementation hardening)

| # | Issue | Fix | Proof |
| --- | --- | --- | --- |
| 1 | EGC fallback (no `Unit`) reused one charge for both levels — `createChargeIfNotExists` saw every unit-less level as `source_id=0` | When there is no `Unit` source, dedupe by the level-bearing description instead | `GenerateChargeTransitionSameSemesterTest` "creates two distinct EGC charges on the Unit-fee fallback" |
| 2 | Reconcile "block" could leave an active discount/allocation when the caller wasn't already in a transaction | `InvoiceGenerationService::applyInvoiceDiscount` wraps discount-write + reconcile in one `DB::transaction` | `InstallmentDiscountReconcileTest` block case asserts 0 discount + 0 allocation after throw |
| 3 | Void cancelled an `awaiting_payment` installment locally but left the live DNG request | Void now **blocks** when an `awaiting_payment` installment is linked to a live (pending/pushed) DNG — operator must cancel the DNG first (that flow transitions the request terminal, then voids safely, so no deadlock) | `VoidChargeInstallmentsTest` "blocks void when … linked to a live DNG" + `CancelDngPaymentRequestActionTest` green |
| 4 | EGC preview row total hardcoded `15_000_000` despite FIN-06 moving the source to `Unit.base_fee` | Row total sums server-resolved `chargeable_levels[].amount` for the selected block count | `GenerateCharges.vue` `rowTotal()`; eslint clean |
| 5 | EGC override was page-local: confirm only sent the current page, so cross-page block overrides were dropped to default and the banner ignored live overrides | Persist overrides across pages (component survives via the data-table composable's `preserveState`), send the FULL override set on confirm, and recompute the banner block/amount totals from those overrides | `EgcChargeGenerationStoreTest` "applies a per-student block override and defaults un-sent students to max"; `GenerateCharges.vue` `confirmedBlockCount`/`confirmedTotalAmount` |
| 6 | `GenerateCharges.vue` used the legacy `useInertiaFilters` composable (forbidden for edited pages per `docs/rules/filtering.md`) | Migrated to `useDataTable<EgcChargeFilters>`; filter changes route through `setFilter`/`handleSearch` (reset page + navigate), semester select is an `immediateFields` entry. `useDataTable` also sets `preserveState: true`, so override persistence is unchanged | eslint clean; no residual `useInertiaFilters`; backend `EgcChargeGenerationStoreTest` green |

> FIN-06 decision note: the option implemented (`Unit.base_fee` canonical, flat
> 15M fallback) is the one selected via the decision prompt — i.e. the approved
> option, not the earlier flat-15M suggestion.
