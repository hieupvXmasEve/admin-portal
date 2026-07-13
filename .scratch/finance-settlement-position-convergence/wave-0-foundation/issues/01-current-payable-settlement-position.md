# 01 — Đọc Settlement Position hiện tại cho một khoản phí

**Status:** ready-for-human
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Tạo tracer bullet đầu tiên của canonical Settlement Position cho một exact Payable Line/Finance Obligation. Reader trả Money VND, raw signed cash/discount/credit evidence, derived gross/discount/cash/credit/remaining, trạng thái hợp lệ và stable issue codes. Reader không tái sử dụng phép clamp hiện tại làm nguồn chân lý: dữ liệu sai phải được trả về dưới dạng invalid với bằng chứng có thể sửa.

## Acceptance criteria

- [x] Một exact payable scope trả đúng gross, discount, cash, applied credit và remaining bằng Money DTO VND.
- [x] Cash và applied credit luôn là hai thành phần riêng; credit không tăng `Đã thu`.
- [x] Raw signed applications được giữ nguyên trước validation; overpayment, over-discount và over-credit không bị `max`/`min` che đi.
- [x] Invalid position trả stable blocking issue code cùng raw evidence; không trả một amount đã clamp như thể hợp lệ.
- [x] Missing obligation/payable line không được coi là cleared.
- [x] Contract Finance-internal không buộc consumer đọc trực tiếp settlement tables.
- [x] Characterization tests chứng minh khác biệt có chủ đích với derivation hiện tại ở các case bị clamp.

## Blocked by

None - can start immediately.
