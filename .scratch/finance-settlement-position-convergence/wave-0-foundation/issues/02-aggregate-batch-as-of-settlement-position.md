# 02 — Tổng hợp Settlement Position theo business scope

**Status:** ready-for-human
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Mở rộng tracer bullet line-level thành canonical aggregation cho invoice, fee type, Billing Account và exact target set. Cùng contract hỗ trợ current và as-of semantics, trả aggregate lẫn exact Payable Line breakdown, và có batch transport parity với single reads. Lịch sử dùng business-effective timestamps thay vì đoán từ `created_at`.

## Acceptance criteria

- [x] Invoice, fee type, Billing Account và exact target set được aggregate bởi reader, không bởi consumer.
- [x] Aggregate và breakdown reconcile chính xác sau Money normalization và deterministic remainder assignment.
- [x] Một target có blocking issue làm toàn requested scope invalid; reader không âm thầm bỏ target đó.
- [x] Batch result parity với từng single-scope read ở cùng snapshot/version.
- [x] Current position phản ánh evidence hiện hành; as-of position không bị payment/reversal/refund xảy ra sau timestamp viết lại.
- [x] Evidence thiếu reliable business timestamp trả as-of issue hoặc áp dụng documented legacy rule, không đoán chronology.
- [x] Representative query fixtures chứng minh không tạo N+1 theo số target.

## Blocked by

- [01 — Đọc Settlement Position hiện tại cho một khoản phí](01-current-payable-settlement-position.md)
