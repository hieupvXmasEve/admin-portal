# 04 — Push một fee type qua guarded DNG reservation

**Status:** ready-for-human
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Chuyển một complete single-fee DNG push path sang reserve → provider call → finalize. Reservation lấy amount và exact targets từ canonical Settlement Position, giữ target bằng line/installment-scoped hold, serialize ngắn theo Billing Account, tăng `settlement_version`, lưu target fingerprint và dùng deterministic `ItemId`. Không giữ DB lock khi gọi DNG và không fallback về công thức cũ.

## Acceptance criteria

- [x] Một eligible fee type được reserve từ canonical collectible và push đúng exact target breakdown.
- [x] Guard serialize mutation theo Billing Account nhưng provider call chạy ngoài transaction/DB lock.
- [x] Reservation lưu provider rail/campus, deterministic `ItemId`, target identities, captured version và target fingerprint.
- [x] Retry cùng logical reservation tái sử dụng `ItemId`; attempt mới bị chặn khi outcome cũ chưa confirmed terminal.
- [x] Mutation không liên quan làm version đổi sẽ revalidate targets; target đổi làm finalize giữ allocation để review.
- [x] Active slot unique theo provider rail/campus + Billing Account + fee type.
- [x] Invalid/held Settlement Position fail closed và không gọi provider.

## Blocked by

- [00 — Xác nhận contract thật của DNG](../../prerequisites/issues/00-dng-provider-contract.md)
- [02 — Tổng hợp Settlement Position theo business scope](../../wave-0-foundation/issues/02-aggregate-batch-as-of-settlement-position.md)
- [03 — Shadow-check và chặn settlement bypass mới](../../wave-0-foundation/issues/03-shadow-gate-and-architecture-allowlist.md)
