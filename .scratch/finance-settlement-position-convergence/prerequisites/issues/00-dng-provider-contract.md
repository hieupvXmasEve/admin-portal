# 00 — Xác nhận contract thật của DNG

**Status:** ready-for-human
**Portal impact:** student

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Xác nhận và lưu lại một contract DNG đủ tin cậy để các agent có thể triển khai collection mà không đoán hành vi provider. Contract phải bao phủ cách chuẩn hóa VND, tính duy nhất và khả năng tra cứu của `ItemId`, retry, hủy request, pay-one/pay-all, callback và daily reconciliation. Kết quả cần có provider evidence hoặc sanitized request/response fixtures có thể dùng trong automated tests.

## Acceptance criteria

- [x] Quy tắc amount, scale và rounding VND tại DNG boundary được xác nhận bằng provider evidence.
- [x] Phạm vi duy nhất và hành vi retry của `ItemId` được xác nhận; cùng một logical reservation không tạo debt record thứ hai.
- [x] Cách xác minh kết quả push/hủy không rõ được ghi rõ: provider lookup API hoặc quy trình staff-to-DNG có bằng chứng tương đương.
- [x] Pay-all được xác nhận trả một hay nhiều transaction và mỗi transaction liên kết về fee-type request nào.
- [x] Callback và daily reconciliation fixtures chứa đủ payer, campus, `ItemId`, fee type, amount và payment identity để test correlation.
- [x] Hành vi payment đến trong hoặc sau cancellation được xác nhận; contract không cho phép Swinx bỏ tiền thật.
- [x] Không lưu credential hoặc dữ liệu sinh viên thật trong fixture.

## Blocked by

None - can start immediately.
