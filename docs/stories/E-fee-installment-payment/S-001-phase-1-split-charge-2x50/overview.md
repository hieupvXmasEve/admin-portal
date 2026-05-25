# Overview — S-001: Phase 1 Split Charge 2×50%

## Current Behavior

- Mỗi `FinanceCharge` đang ACTIVE = 1 lần thu duy nhất với amount đầy đủ.
- `DngPaymentService::createAndPush()` (`app/Modules/Finance/Dng/Services/DngPaymentService.php:49-106`) push toàn bộ `charge.amount` sang DNG. Tạo DNG mới → DNG cũ bị `CANCELLED`.
- Invariants: 1 charge ACTIVE per `(student, charge_type)`; 1 DNG `awaiting_payment` per `(student, fee_type)`.
- SV trả 1 lần xong → invoice receipt.

## Target Behavior

- Admin Finance (có permission `finance.charge.split-installment`) có thể chia 1 `FinanceCharge` thành N đợt (Phase 1 default 2×50%, action support N).
- Hệ thống auto-push đợt kế tiếp sang DNG ngay sau khi đợt trước `paid` (post-commit dispatch để không khoá DB).
- DNG vẫn chỉ thấy 1 đợt mỗi lần, không biết khái niệm "installment". Invariants cũ giữ nguyên.
- Charge cũ (chưa chia đợt) được backfill 1 installment để code path đồng nhất, không if/else legacy.
- SV thấy bảng đợt trong portal với trạng thái + amount + due_date.
- Phase 1 lock: chỉ sửa kế hoạch đợt khi chưa có đợt nào `paid`. Sau đợt 1 paid → readonly.

## Affected Users

- **Admin Finance** (role có permission `finance.charge.split-installment`): tách/sửa đợt, retry push DNG khi lỗi.
- **Sinh viên**: xem các đợt + thanh toán DNG đợt đang `awaiting_payment`.
- **Super-admin / Department head**: nhận noti `InstallmentPushFailed` khi retry 3 lần fail.

## Affected Product Docs

- `docs/features/finance/fee-installment-payment.md` (spec gốc — Draft)
- `docs/system-architecture.md` (mục Finance — sẽ update khi PR merge)
- `docs/rules/contracts.md` (nếu cần Contract cho Notification đọc installment data)

## Non-Goals

- **Phase 1 KHÔNG** support void charge khi đã có đợt `paid` (D2: cấm hoàn toàn, trả 400).
- **Phase 1 KHÔNG** sửa kế hoạch đợt nhiều lần (chỉ sửa 1 lần, trước khi đợt 1 paid).
- **Phase 1 KHÔNG** áp dụng cho fee_type khác ngoài HP (code không hard-code nhưng UI chỉ enable cho HP).
- **Phase 1 KHÔNG** auto-remind SV trước `due_date`.
- **Phase 1 KHÔNG** gộp nhiều charge khác type vào 1 DNG.

## Locked Decisions (from intake)

- **D1**: due_date defaults — đợt 1 = `today + 30d`, đợt 2 = `charge.due_date` (hoặc `today + 60d` nếu null).
- **D2**: Void charge sau khi có đợt paid → cấm Phase 1, defer Phase 2.
- **D3**: Notification — tạo 4 templates mới × 2 lang (EN+VI) qua `EmailContentRegistry`. Template content cần team Noti review trước prod ship (ship-blocker, không block code).
- **D4**: Permission gate — `finance.charge.split-installment` (key mới trong `config/permission.php` → Policy method trong `app/Modules/Finance/Policies/FinanceChargePolicy.php`).
