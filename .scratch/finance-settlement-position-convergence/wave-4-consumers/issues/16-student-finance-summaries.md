# 16 — Chuyển Student 360 và student finance API

**Status:** ready-for-human
**Portal impact:** student

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Chuyển Student 360 finance summaries, payment/action cards và student finance API summaries sang canonical Settlement Position. Backend quyết định semantic/actionability; frontend chỉ render. Student thấy `Đã thu`, `Còn phải thu`, `Còn dư` và selected/pay-all availability đúng, còn invalid state dùng thông báo trung tính không lộ internal issue hoặc untrusted amount.

## Acceptance criteria

- [x] Student 360 current KPIs và cards dùng canonical current position, không dùng local `total - paid`.
- [x] `Đã thu` là matched cash, applied credit tách riêng, `Còn dư` là cash chưa phân bổ.
- [x] Paid DNG/history vẫn hiện ở payment history nhưng không tạo current DNG action giả.
- [x] Student API summary và portal shared types giữ cùng contract/field semantics.
- [x] Invalid/held/unknown scope ẩn money action và trả neutral student message; staff evidence không leak.
- [x] Per-fee/pay-all availability khớp issue 11 và không tự infer ở frontend.
- [x] Backend focused tests cùng portal typecheck/build pass; portal lint và full-suite gaps được ghi rõ bên dưới.

## Verification

- Added `StudentFinanceSettlementPositionReader` as the Finance-owned seam for current Billing Account/semester summaries. Student 360 KPIs/cards and student API `/finance`, `/finance/balance`, and `/finance/overview` now expose canonical gross/net, matched cash, applied credit, remaining collectible, unapplied cash, validity, and neutral unavailable state.
- Student 360 adds a separate applied-credit KPI, keeps paid DNG rows in history, and disables allocation when the current position is invalid. Student DNG list/pay-all access now revalidates reservation targets through the canonical reader before reporting `available`.
- Portal types/page render `null` money as neutral unavailable copy; the frontend does not infer per-fee/pay-all actionability.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Student360/StudentFinanceSettlementSummaryTest.php` — 3 passed / 20 assertions.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/StudentFinanceDngAccessTest.php` — 7 passed / 22 assertions.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Student360/StudentFinanceSettlementSummaryTest.php tests/Feature/Finance/Student360/Student360OverviewTest.php` — 8 passed / 85 assertions.
- `./scripts/dev.sh npm run type-check` — passed.
- `cd FE/student-nuxt && pnpm typecheck` — passed with existing Nuxt warnings.
- `cd FE/student-nuxt && pnpm build` — passed with existing duplicate-import/component warnings.
- Scoped parent ESLint/Prettier and portal Prettier checks — passed.
- `cd FE/student-nuxt && pnpm lint` — blocked by the pre-existing nested-repo configuration gap: `pnpm-workspace.yaml not found` from `eslint-plugin-pnpm`.
- Full `./scripts/dev.sh test` — attempted; wrapper exited `255` without diagnostics in the current Docker test environment. Parallel DB test invocation was not used as evidence because the shared `db_test` database raced during concurrent runs.
- Existing Student 360 fixtures that contain active payable lines without canonical Finance Obligations now fail closed as required; the focused regression expectation was updated accordingly. Human staging verification remains required before completion.

## Comments

### 2026-07-12 — Student finance summary implementation

- Scope stayed within Student 360 current summaries/cards, student finance summary APIs, the student portal contract/rendering, and focused regression coverage. Parent PRD and sibling issue states were not changed.
- Status remains `ready-for-human` because portal lint, the environment-wide test wrapper, and human staging verification remain unresolved.

### 2026-07-13 — Resolved paid-DNG/voided-fee false positive

- Browser QA on rehearsal student `AUH116717` exposed a false review signal after all `48M` had been reversed from void tuition/retake lines and fully reallocated to the approved FORFEIT adjustment. Current surplus and collectible were both `0`.
- `GetStudentFinanceReviewSignalsQuery` now requires reversal evidence for the exact charge(s) targeted by that DNG, requires every relevant void-line application group to net to zero, and requires no remaining undisposed cash before clearing the warning. Missing Payment bridges, missing/mismatched reversal evidence, non-zero void-line nets, and real surplus remain reviewable.
- Explicit refund/retain-forfeit dispositions are deducted consistently from both the generic surplus signal and the paid-DNG/voided-fee signal.
- Focused boundary regression: `4 passed / 40 assertions`; adjacent Student 360 settlement/API regression: `8 passed / 85 assertions`; Pint and scoped `git diff --check` passed.
- Before fixture repair, the latest `StudentOverviewShellTest` run had `14 passed / 5 failed`: five legacy fixtures lacked canonical currency evidence and failed closed with `settlement_position.missing_currency` before their assertions.
- Final authenticated browser verification on rehearsal student `AUH116717` passed: surplus `0`, no current DNG work, no exception card, and no paid-DNG/voided-fee warning; browser console was clean.
- Follow-up fixture repair materialized test payable charges through BillingAccount/FinanceObligation with canonical `VND` currency instead of weakening the production fail-closed guard. `StudentOverviewShellTest` now passes `19 tests / 347 assertions`; the full Student 360 directory passes `37 tests / 460 assertions`. This supersedes the `11 passed / 5 failed` fixture-gap result above.
- Subsequent discovery repair removed a duplicate global Pest helper, so `pest --list-tests` now lists `1,979` tests successfully. The scoped post-UI regression gate passes `89 tests / 942 assertions`, including Student 360, legacy paid-PTL reconciliation, canonical fixture consumers, durable exam-resit cancellation expectations, and the materializer architecture guard.
- The normal full suite still exhausts its `256 MB` PHP memory limit. A `1 GB` diagnostic run completed with `1,763 passed / 204 failed / 13,322 assertions`; failures include unrelated AI/Notification/Academic baseline issues plus broad legacy fixture/isolation debt. This is not a green full-suite gate and does not supersede the focused green evidence.
- Status remains `ready-for-human`; portal lint, full-suite environment, and staging verification gates remain open.

## Blocked by

- [02 — Tổng hợp Settlement Position theo business scope](../../wave-0-foundation/issues/02-aggregate-batch-as-of-settlement-position.md)
- [11 — Sinh viên thanh toán một fee type hoặc tất cả](../../wave-1-dng-safety/issues/11-student-pay-one-or-all-fee-types.md)
- [13 — Chuyển invoice paid cache thành cash-only](../../wave-2-cash-cache/issues/13-cash-only-invoice-paid-cache.md)
