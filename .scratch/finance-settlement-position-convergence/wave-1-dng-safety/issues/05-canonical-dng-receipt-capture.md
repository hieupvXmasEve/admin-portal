# 05 — Ghi nhận DNG receipt chính xác vào Payment

**Status:** ready-for-human
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Tạo một canonical provider-receipt command và chuyển exact-match webhook cùng daily reconciliation qua command đó. Một receipt được xác thực và correlated chính xác phải tạo canonical Payment đúng một lần, phân bổ vào reserved targets sau revalidation, settle linked installments và giữ đầy đủ provider evidence bất kể callback/reconciliation đến theo thứ tự nào.

## Acceptance criteria

- [x] Webhook và daily reconciliation hội tụ vào cùng receipt-capture behavior.
- [x] Checksum/authenticity, payer correlation và target/amount validation là các bước riêng có evidence.
- [x] Exact receipt tạo một Payment và application đúng targets; duplicate webhook/reconcile không tạo Payment thứ hai.
- [x] Callbacks đến ngược thứ tự hoặc reconciliation chạy đồng thời không downgrade request state.
- [x] Provider cash được capture trước target allocation; target drift không làm mất receipt.
- [x] Linked installments được settle idempotently và không resurrect cancelled installment.
- [x] Payment, request, provider payment identity và raw receipt có audit linkage hai chiều.

## Blocked by

- [04 — Push một fee type qua guarded DNG reservation](04-guarded-single-fee-dng-reservation.md)
