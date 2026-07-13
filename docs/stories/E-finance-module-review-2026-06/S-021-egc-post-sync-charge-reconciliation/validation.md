# Validation

> **Historical INV-6 notice (2026-07-12):** Tài liệu này ghi lại định nghĩa/kết quả audit cũ. Nhiều invoice cùng student/kỳ là hợp lệ theo kiến trúc hiện tại; không cleanup hoặc thêm unique `(student_id, semester_id)` chỉ vì multi-invoice. `INV-6` đã retired; `INV-17` kiểm tra invoice line tham chiếu charge sai student/kỳ.

## Proof Strategy

Prove that post-sync reconciliation:

1. Relevels mis-projected pending blocks to the retake sequence.
2. Auto-applies retake discount when attendance ≥ 80%.
3. Skips discount but still relevels when attendance < 80%.
4. Handles paid invoices via settlement release without breaking payment
   applications.
5. Is idempotent on second sync.
6. Supports the single-block (1-block semester) case.
7. Leaves manual Retake Adjustments for repair-only edge cases.
8. Presents Block Results sync and Retake Adjustments outcomes in one operator
   surface in Finance Office (New UI).

Run `finance:audit-invariants` after any money-changing test fixture setup.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Expected-level planner: fail L3 + targets L4/L5 → L3/L4; fail L4 + target L5 → L4; no-op when already aligned; skip when target charge inactive |
| Unit | Safety guards: multi-line invoice, existing discount allocation, consumed `retake_discount_id`, void invoice |
| Integration | `AUS112882`-style fixture: pre-paid SUMMER L4/L5, sync SPRING fail L3 97.14% → blocks releveled, discount 7.5M on L3 line, `egc_retake_discount_links` created, source `retake_discount_id` set |
| Integration | Paid invoice overpayment release path after auto discount |
| Integration | Attendance 70% on source → relevel without `InvoiceDiscount` |
| Integration | Single-block semester: only L5 generated, fail L4 → reconcile to L4 retake + discount when eligible |
| Integration | Second sync/reconcile → no duplicate discounts or double relevel |
| Integration | `ListEgcRetakeAdjustmentsQuery` no longer shows reconciled student in `eligible_no_targets` |
| E2E | Combined EGC post-sync reconciliation UI shows sync controls, reconciliation summary counts, applied/auto-reconciled rows, waiting/manual-repair rows, and no required navigation between Block Results and Retake Adjustments |
| Navigation | Finance Office (New UI) sidebar exposes one combined EGC reconciliation entry under `Sinh phí`; old Block Results / Retake Adjustments routes remain reachable as redirects or deep links |
| Logs/Audit | Reconciliation log/flash includes student code, block ids, discount ids |

## Fixtures

### Fixture A — two-block ahead (AUS112882 pattern)

- Student `intake_pre_uni_gc`, `gc_total_levels = 6`
- SPRING2026: block fail L3, attendance 97.14%, charge + invoice
- SUMMER2026: pending blocks L4 + L5, charges on one paid invoice

### Fixture B — single-block ahead

- SPRING2026: block fail L4, attendance 85%
- SUMMER2026: one pending block L5, charge + invoice (paid or draft)

### Fixture C — ineligible attendance

- Same as Fixture A but attendance 75% → relevel only

### Fixture D — already reconciled / idempotent

- Run Fixture A twice; assert stable totals and single discount link

## Commands

```bash
./scripts/dev.sh test tests/Feature/Finance/Egc/ReconcileEgcChargesAfterSyncTest.php tests/Feature/Finance/Egc/SyncEgcBlockResultsTest.php tests/Feature/Finance/Egc/RetakeAdjustmentsTest.php tests/Feature/Finance/Egc/CarryForwardTest.php tests/Feature/Finance/Cutover/FinanceOfficeCutoverTest.php
./scripts/dev.sh artisan finance:audit-invariants
./scripts/dev.sh composer exec pint -- --dirty --format agent
./scripts/dev.sh npm exec eslint resources/js/pages/Finance/EgcOperations/BlockResults.vue resources/js/constants/menu-sidebar.ts
./scripts/dev.sh npm exec -- prettier --check resources/js/pages/Finance/EgcOperations/BlockResults.vue resources/js/constants/menu-sidebar.ts
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm exec -- node --max-old-space-size=4096 ./node_modules/vite/bin/vite.js build
```

## Acceptance Evidence

Implementation evidence recorded on 2026-06-21:

- Red check: `./scripts/dev.sh test tests/Feature/Finance/Egc/ReconcileEgcChargesAfterSyncTest.php --filter="relevels future generated charges and auto-applies retake discount after sync"` failed before the action existed.
- Targeted finance regression: `./scripts/dev.sh test tests/Feature/Finance/Egc/ReconcileEgcChargesAfterSyncTest.php tests/Feature/Finance/Egc/SyncEgcBlockResultsTest.php tests/Feature/Finance/Egc/RetakeAdjustmentsTest.php tests/Feature/Finance/Egc/CarryForwardTest.php tests/Feature/Finance/Cutover/FinanceOfficeCutoverTest.php` passed: 42 tests, 187 assertions.
- Formatting/lint: `./scripts/dev.sh composer exec pint -- --dirty --format agent` passed; `./scripts/dev.sh npm exec eslint resources/js/pages/Finance/EgcOperations/BlockResults.vue resources/js/constants/menu-sidebar.ts` passed; `./scripts/dev.sh npm exec -- prettier --check resources/js/pages/Finance/EgcOperations/BlockResults.vue resources/js/constants/menu-sidebar.ts` passed.
- Frontend build: `./scripts/dev.sh npm exec -- node --max-old-space-size=4096 ./node_modules/vite/bin/vite.js build` passed. The plain `./scripts/dev.sh npm run build` was killed with exit 137 in this local container.
- Type-check gap: `./scripts/dev.sh npm run type-check` was killed with exit 137; `./scripts/dev.sh npm exec -- node --max-old-space-size=4096 ./node_modules/vue-tsc/bin/vue-tsc --noEmit --pretty false` was also killed with SIGKILL.
- Finance invariant gap: `./scripts/dev.sh artisan finance:audit-invariants --sample` exited 0 but the dev DB is not clean: INV-6 duplicate invoice groups = 28 (sample invoice ids: 1182, 1343, 1439, 1184, 1185); INV-13 live installment on voided charge = 1 (sample installment id: 1067). Treat as a pre-existing dev DB cleanup gap, not a clean invariant run.
- UI behavior is covered by controller/menu assertions: the old Retake Adjustments index redirects to `finance.egc.block-results.index` with `section=retake-adjustments`, and the Finance Office (New UI) sidebar exposes the combined EGC results/retake reconciliation entry under `Sinh phí`.
