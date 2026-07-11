# 06 — Xử lý DNG receipt lệch hoặc chưa khớp

**Status:** completed
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Mở rộng canonical receipt path cho underpayment, overpayment, target drift và provider evidence chưa đủ xác định payer. Attributable cash được ghi theo actual amount, allocation bị giới hạn bởi canonical collectible và phần không phân bổ trở thành **Còn dư**. Evidence không xác định được Billing Account đi vào unmatched provider-receipt queue. Finance Operations Exceptions hiển thị evidence và resolution actions theo quyền riêng.

## Acceptance criteria

- [x] Verified underpayment/overpayment tạo Payment theo actual received amount đúng một lần.
- [x] Allocation không vượt current target collectible; surplus trở thành `Còn dư`, shortfall giữ remaining theo confirmed provider outcome.
- [x] Payer/campus/`ItemId`/fee-type ambiguity không tạo guessed Payment và xuất hiện trong unmatched queue.
- [x] Mismatched hoặc late receipt block recollection cho affected scope cho tới khi reconcile xong.
- [x] Staff thấy raw provider evidence, mismatch reasons và exact affected scope dưới nhãn `Cần kiểm tra`.
- [x] View-only và resolve permissions được tách; `create_finance_payments` không còn là catch-all repair permission.
- [x] Resolution/retry idempotent và không sửa/xóa raw provider evidence.

## Blocked by

- [05 — Ghi nhận DNG receipt chính xác vào Payment](05-canonical-dng-receipt-capture.md)

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Dng` — passed.
- `./scripts/dev.sh npm run type-check` — passed.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — passed.
- `./scripts/dev.sh artisan route:list --name=finance.dng --except-vendor` confirmed the exception queue and resolve routes.
