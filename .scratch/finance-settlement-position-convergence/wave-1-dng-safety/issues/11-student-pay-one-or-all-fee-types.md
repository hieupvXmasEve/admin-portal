# 11 — Sinh viên thanh toán một fee type hoặc tất cả

**Status:** ready-for-human
**Portal impact:** student

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Chuyển student DNG access end-to-end sang active request contract mới. Per-request action chỉ gửi fee type đã chọn; pay-all gửi toàn bộ active fee types nhưng giữ exact request/breakdown correlation. Backend fail closed khi bất kỳ selected target invalid/held, và student portal hiển thị đúng action riêng/toàn bộ cùng neutral unavailable state.

## Acceptance criteria

- [x] Per-request QR/payment access gửi đúng một selected fee type, không expand sang mọi pending type.
- [ ] Pay-all gửi đúng toàn active fee-type set và giữ mapping từ provider transaction về exact local request(s). **Blocked:** provider fixture hiện vẫn `confirmation_required` và không trả transaction-to-`ItemId` mapping; backend fail closed thay vì mở combined flow.
- [x] Một invalid/held selected type làm pay-all fail closed; valid types vẫn thanh toán riêng được.
- [x] Portal không hiển thị amount/action từ untrusted Settlement Position và dùng neutral unavailable copy.
- [x] Backend authorization/campus context không cho truy cập request của Billing Account khác.
- [x] Student shared types/composable/page được cập nhật cùng backend contract.
- [ ] Backend và portal tests bao phủ một fee type, nhiều fee types, pay-all mismatch và invalid target; backend focused tests và portal typecheck/build đã chạy. Portal lint bị chặn vì nested repo thiếu `pnpm-workspace.yaml`; portal hiện không có test runner tương ứng.

## Blocked by

- [00 — Xác nhận contract thật của DNG](../../prerequisites/issues/00-dng-provider-contract.md)
- [04 — Push một fee type qua guarded DNG reservation](04-guarded-single-fee-dng-reservation.md)
- [05 — Ghi nhận DNG receipt chính xác vào Payment](05-canonical-dng-receipt-capture.md)
- [06 — Xử lý DNG receipt lệch hoặc chưa khớp](06-mismatched-and-unmatched-dng-receipts.md)

## Comments

### 2026-07-11 — Implementation

- Added a guarded student DNG access action that scopes by the authenticated student, Billing Account, campus, provider rail, active request status, request metadata, and (when present) reservation targets against Settlement Position.
- Per-request access sends only the selected request's fee type. Pay-all rejects multiple active fee types until DNG supplies an exact provider transaction-to-local-request/`ItemId` mapping.
- Added neutral unavailable states and contract types in the student portal; payment amounts/actions are hidden when access is unavailable.
- Backend focused tests pass: `15 passed, 118 assertions`; portal typecheck/build pass with existing warnings. Portal lint remains blocked by the nested repo configuration gap above.
- Remaining handoff gate: obtain provider evidence for combined pay-all mapping, then implement and test the multi-fee provider path before marking this issue complete.
