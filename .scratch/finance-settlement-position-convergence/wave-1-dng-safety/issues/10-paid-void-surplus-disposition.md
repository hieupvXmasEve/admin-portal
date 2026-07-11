# 10 — Xử lý Còn dư sau paid obligation void

**Status:** ready-for-human
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Hoàn thiện staff workflow sau khi một paid obligation bị void. Default behavior release Payment applications khỏi voided payable và để cash thành **Còn dư**; không tự reallocate, refund hoặc forfeit. Staff có preview và independently authorized actions để phân bổ sang eligible obligation, ghi nhận real refund với external evidence, hoặc retain/forfeit theo policy có reason và approval.

## Acceptance criteria

- [x] Paid obligation void mặc định release applications và giữ Payment cash thành `Còn dư`.
- [x] Không action nào tự chạy chỉ vì source gửi `acknowledge_no_refund`.
- [x] Reallocation có preview/confirm, dùng canonical eligibility và không chạm held target.
- [x] Refund chỉ hoàn tất khi có external evidence và ghi signed/auditable refund effect.
- [x] Retain/forfeit yêu cầu explicit policy, reason và permission/approval riêng.
- [x] Mọi disposition idempotent và bảo toàn original DNG, Payment, application/release history.
- [x] Staff UI hiển thị rõ số `Còn dư`, nguồn payment và current permitted actions.

## Blocked by

- [09 — Hủy obligation qua Finance Cancellation Operation](09-finance-cancellation-operation.md)

---

## Comments

### Implementation evidence 2026-07-11

- Added append-only `payment_surplus_dispositions` with idempotency key, signed evidence, approver and external/policy evidence.
- Reallocation confirms through canonical active-line settlement checks and rejects held DNG targets.
- Refund and retain/forfeit use independent permissions and never mutate original Payment, DNG or release history.
- Student 360 exposes only currently permitted actions and keeps `Đã thu` tied to PaymentApplication cash.
- Focused Pest: **11 passed / 59 assertions** (`PaymentSurplusDispositionTest`, `ManualAllocationPreviewTest`, `VoidReleaseAllocationTest`).
- Pint: **passed**. Scoped ESLint for touched Vue/TS files: **passed**.
- Review: Standards and Spec blockers fixed; no remaining acceptance-criteria blocker found.
- Verification gap: repo typecheck was OOM-killed (`137`) without diagnostics; full PHP suite exits `255` without diagnostics while app container remains healthy. Keep `ready-for-human` until these two environment gates are rerun successfully.
