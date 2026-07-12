# 19 — Đóng settlement bypass allowlist

**Status:** ready-for-human
**Portal impact:** student

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Kết thúc chương trình migration bằng cách đưa architecture formula/write allowlists về zero, xóa scattered balance helpers/SQL và temporary shadow compatibility code, rồi chứng minh tất cả authoritative consumers/writers đi qua canonical Settlement Position hoặc approved narrow contract/guard. CI phải fail khi một local formula, direct money writer hoặc silent fallback được tái giới thiệu.

## Acceptance criteria

- [ ] Consumer và writer inventory của parent PRD không còn unresolved entry.
- [ ] Legacy formula/write allowlists bằng zero; mọi direct mutation đi qua approved Settlement Mutation Guard path.
- [ ] Không controller/query/UI/report/student API nào tự tính authoritative balance.
- [ ] Temporary compatibility helpers, duplicate SQL formulas và shadow-only code không còn operational purpose được xóa.
- [ ] Architecture red-proof fail khi cố thêm local formula, direct money writer hoặc fallback về legacy calculation.
- [ ] Full affected Finance, Academic, API và student portal checks pass; performance không regress ngoài approved budgets.
- [ ] Final audit chứng minh zero unexplained shadow mismatch, zero paid-unbridged attributable receipt và zero blocking integrity issue trong activated scope.

## Blocked by

- [03 — Shadow-check và chặn settlement bypass mới](../../wave-0-foundation/issues/03-shadow-gate-and-architecture-allowlist.md)
- [12 — Migrate DNG active và cutover production](../../wave-1-dng-safety/issues/12-active-dng-migration-and-cutover.md)
- [14 — Hiển thị settlement breakdown trên invoice detail](../../wave-3-invoice-ui/issues/14-invoice-settlement-breakdown.md)
- [15 — Chuyển staff lists và operations worklists](../../wave-4-consumers/issues/15-staff-lists-and-operations-worklists.md)
- [16 — Chuyển Student 360 và student finance API](../../wave-4-consumers/issues/16-student-finance-summaries.md)
- [17 — Chuyển current Finance reports và Fee Monitor](../../wave-5-reporting/issues/17-current-finance-reports.md)
- [18 — Chuyển historical và as-of reports](../../wave-5-reporting/issues/18-historical-as-of-reports.md)

## Verification

- `./scripts/portal-status.sh` — student và lecturer nested repositories sạch; không chạm portal code trong slice này.
- `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/SettlementBypassArchitectureTest.php` — **failed**: `ReverseCreditApplicationAction.php` có direct `CreditApplication` writer ngoài reviewed inventory.
- `./scripts/dev.sh test` — exit `255` từ wrapper, không có diagnostics.
- `./scripts/dev.sh npm run type-check` — exit `134` do `vue-tsc` Node heap OOM; chưa có bằng chứng typecheck xanh.
- Re-scan hiện trạng cho thấy allowlist vẫn còn **17 entries**, cùng các writer/formula operational như `CreateFinanceChargeAction`, `PaymentService`, `SettlementService`, `RequestFinanceDiscountAction`, batch DNG calculation và legacy backfill commands.
- Các dependency còn acceptance chưa hoàn tất: issue 12 còn unresolved active/paid-unbridged/shadow/provider gates; issue 15 còn held/unknown action và production-volume budget; issue 17/18 còn performance gates; issue 16 còn environment/staging gates.

## Comments

### 2026-07-12 — Implementation audit

- Không xóa allowlist hoặc đánh dấu acceptance criteria hoàn tất khi các consumer/writer và release gates nêu trên vẫn còn unresolved.
- `ReverseCreditApplicationAction` đã gọi `SettlementMutationGuard` ở runtime nhưng architecture scanner hiện chưa nhận diện guarded writer; cần sửa scanner/contract cùng với việc reroute các writer còn lại, không thêm entry allowlist mới.
- Issue chuyển sang `ready-for-human` để hoàn tất dependency migration, production shadow evidence và quyết định/refactor mutation-writer boundary trước khi thực hiện wave-6 closure.
