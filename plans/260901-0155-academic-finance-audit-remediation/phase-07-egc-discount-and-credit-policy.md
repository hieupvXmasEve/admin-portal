---
phase: 7
title: "EGC 50% động + bỏ flat credit 15M (Q-05, Q-12)"
status: pending
priority: P1
effort: "1.5d"
dependencies: [1]
---

# Phase 7: EGC 50% động + bỏ flat credit 15M (Q-05, Q-12)

## Overview

Hai fix chính sách EGC theo quyết định owner:

1. **Q-05 (P-02)**: `ApplyEgcRetakeDiscountAction.php:34` dùng hằng
   `DISCOUNT_AMOUNT = 7_500_000` trong khi comment (:27,41) gọi là "50%".
   Owner chốt: discount là 50% THẬT, tính động từ phí block đích.
2. **Q-12 (P-12)**: `ApplyEgcMajorEntryCreditAction.php:26` hardcode credit
   15,000,000 qua nút staff (`EgcRetakeAdjustmentsController.php:46-61`),
   chồng chéo với carry-forward (RULE-21) vốn release đúng số đã trả.
   Owner chốt: **carry-forward là cơ chế duy nhất** — bỏ flat credit.

## Requirements

- Functional: EGC retake discount = 50% của `egc_level_fee` của block đích,
  resolve tại thời điểm áp dụng (qua catalog/`EgcLevelFeeResolver` — cùng
  nguồn tạo charge); áp 1 lần/block, các điều kiện RULE-19 giữ nguyên.
- Functional: major-entry credit biến mất hoàn toàn khỏi đường staff; credit
  đã phát sinh trước đó là dữ liệu lịch sử — không hoàn tác.
- Non-functional: comment code khớp hành vi (bỏ mọi chữ "50%" sai hoặc hằng sai).

## Architecture

- Discount (red-team — đã chỉnh base): thay `DISCOUNT_AMOUNT` bằng
  `discount = 50% × số tiền NET của charge đích bị giảm` (charge mà staff
  chọn qua `target_charge_id`) — KHÔNG resolve qua `EgcLevelFeeResolver`
  (resolver có `FALLBACK_FEE = 15M`, có thể lệch với charge thật).
  Khi charge net ≠ catalog fee → log + route review thay vì áp mù. Guard:
  discount ≤ charge amount.
- Credit: xóa action + route + nút UI. **GIỮ nguyên** permission
  `apply_egc_retake_adjustment` (dùng CHUNG với endpoint discount `store`,
  `routes/web.php:87-92` — xóa permission làm vỡ endpoint discount còn lại).
  **GIỮ nguyên** đăng ký obligation type `EgcExemptCredit` (thành viên của
  `FinanceEntitlementType::DEBIT_TYPES/DISCOUNT_TYPES`
  `FinanceEntitlementType.php:16,25,38`, label `FinanceChargeController.php:276`,
  allowedSourceKinds `ObligationTypeRegistry.php:305-311`, backfill
  legacy `FinanceOwnedObligationSource.php:113`) — chỉ xóa ĐƯỜNG TẠO MỚI.

## Related Code Files

- Modify: `app/Modules/Finance/Actions/Egc/ApplyEgcRetakeDiscountAction.php`
- Delete: `app/Modules/Finance/Actions/Egc/ApplyEgcMajorEntryCreditAction.php`
- Modify: `EgcRetakeAdjustmentsController.php` (xóa CHỈ method `applyMajorEntryCredit`; giữ `store` discount), `routes/web.php` (xóa chỉ dòng route credit ~:90-92)
- Modify (test consumers — red-team): `tests/Feature/Architecture/FinanceEgcPricingOwnerReadersArchTest.php:8` (hard-code path action bị xóa → bỏ khỏi mảng), `tests/Feature/Finance/Egc/RetakeAdjustmentsTest.php:428,450,497,509` + `tests/Feature/Finance/Egc/EgcRetakeExemptEntitlementsTest.php:175,240` (case dùng `ApplyEgcMajorEntryCreditAction` → xóa/rewrite)
- Modify: `docs-site` trang hướng dẫn nút credit (VI trước, rồi en/ko cùng change — AGENTS.md end-user guide rule)

## Implementation Steps

1. Discount động: 50% × charge net đích; sửa comments; log review khi lệch catalog.
2. Test baseline P-02 → xanh (block fee ≠ 15M → discount = đúng 50% charge đích).
3. Xóa major-entry credit: action + method + route credit + UI; grep hết usages.
4. Dọn 3 test consumer (arch test + 2 feature test) nêu trên.
5. Test baseline P-12 → xanh (carry-forward vẫn release đúng số đã trả; credit không còn gọi được).
6. Cập nhật `docs-site` (VI → en → ko) cho màn hình mất nút; `./scripts/check-docs.sh`.

## Todo

- [ ] Discount 50% động + comments sửa
- [ ] Flat credit xóa sạch (action, endpoint, UI) — permission + type registration GIỮ
- [ ] docs-site cập nhật 3 locale
- [ ] Không test Egc hiện có nào đỏ ngoài các case được xóa có chủ đích

## Success Criteria

- [ ] Charge đích có net ≠ catalog fee → discount tính trên charge net + log review.
- [ ] Không còn đường nào tạo `EgcExemptCredit` mới; carry-forward hoạt động bất biến.
- [ ] `./scripts/check-docs.sh` xanh.

## Risk Assessment

- **Trung bình:** xóa endpoint staff — permission `apply_egc_retake_adjustment` dùng chung, KHÔNG xóa;
  dữ liệu credit cũ để nguyên (không migration hoàn tác).
- **Thấp:** 50% có thể ra số lẻ nếu phí lẻ — guard làm tròn theo convention VND
  hiện có của catalog (tra trước; phí hiện đều tròn triệu).
