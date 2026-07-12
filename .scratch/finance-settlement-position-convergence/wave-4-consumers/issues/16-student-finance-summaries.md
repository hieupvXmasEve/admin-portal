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

## Blocked by

- [02 — Tổng hợp Settlement Position theo business scope](../../wave-0-foundation/issues/02-aggregate-batch-as-of-settlement-position.md)
- [11 — Sinh viên thanh toán một fee type hoặc tất cả](../../wave-1-dng-safety/issues/11-student-pay-one-or-all-fee-types.md)
- [13 — Chuyển invoice paid cache thành cash-only](../../wave-2-cash-cache/issues/13-cash-only-invoice-paid-cache.md)
