# 08 — Hủy collection mà không void obligation

**Status:** completed
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Tách DNG Collection Request Cancellation khỏi obligation/charge/source cancellation. Staff cancellation gửi provider request ngoài DB transaction, chỉ release hold sau confirmed outcome, và giữ unknown outcome fail-closed trong Exceptions. Payment evidence đến trong hoặc sau cancellation luôn được canonical receipt path ghi nhận; local cancelled status không được skip tiền thật.

## Acceptance criteria

- [x] Cancel collection không void obligation, charge, payable line, installment plan hoặc source workflow.
- [x] Unattempted reservation release cục bộ; pushed request chỉ terminal sau provider-confirmed cancellation.
- [x] Timeout/ambiguous cancellation trở thành Unknown Collection Outcome và tiếp tục giữ target/recollection block.
- [x] Payment đến đồng thời hoặc sau cancellation vẫn tạo/khôi phục canonical Payment và chuyển request sang paid handling.
- [x] Cancellation và payment evidence đều được bảo toàn, idempotent qua retry/webhook/reconciliation.
- [x] Staff thấy confirmed/unknown/paid-during-cancel outcomes và permitted next action trong Exceptions.
- [x] Không giữ payer lock trong provider cancellation call.

## Blocked by

- [04 — Push một fee type qua guarded DNG reservation](04-guarded-single-fee-dng-reservation.md)
- [05 — Ghi nhận DNG receipt chính xác vào Payment](05-canonical-dng-receipt-capture.md)
- [06 — Xử lý DNG receipt lệch hoặc chưa khớp](06-mismatched-and-unmatched-dng-receipts.md)

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/CancelDngPaymentRequestActionTest.php tests/Feature/Finance/Dng/CaptureDngProviderReceiptActionTest.php tests/Feature/Finance/Dng/ReviewedCancelDngTest.php tests/Feature/Finance/Dng/ProcessDngWebhookJobTest.php tests/Feature/Finance/Dng/DngReconciliationServiceTest.php` — 59 passed (218 assertions).
- `./scripts/dev.sh npm run type-check` — passed.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — passed.
- `./scripts/dev.sh test` — passed.
- Review against `HEAD`: no new standards violations; spec-review findings for confirmed cancellation and explicit provider rejection were addressed before final verification.
