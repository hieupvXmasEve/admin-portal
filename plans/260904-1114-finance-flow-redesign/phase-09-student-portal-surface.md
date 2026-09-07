---
phase: 9
title: "Bề mặt portal sinh viên (FE/student-nuxt)"
status: completed
priority: P2
effort: "1.5-2d"
dependencies: [4, 8]
---

# Phase 9: Bề mặt portal sinh viên (FE/student-nuxt)

## Overview

Red team 2026-09-06 (Critical): mọi deliverable "portal" của Phase 4 và Phase 8
nhắm vào **một ứng dụng khác** — `FE/student-nuxt`, một repo Nuxt **lồng, có
`.git` riêng**, không phải submodule (`.gitmodules` không tồn tại ở root). Không
một file nào dưới `FE/student-nuxt/` được liệt kê trong bất kỳ phase nào, trong
khi tiêu chí nghiệm thu lại nói về giao diện sinh viên. Phase này tách riêng để
việc đó có file, có review, có pipeline deploy riêng.

## Key Insights

- Repo riêng: `FE/student-nuxt/.git/` tồn tại; root **không** có `.gitmodules`
  ⇒ commit/PR/deploy độc lập với ứng dụng Laravel.
- Type/nhãn riêng, **không** dùng chung `resources/js/types/finance.ts`:
  `FE/student-nuxt/shared/types/finance.ts`, `FE/student-nuxt/app/lib/finance.ts`
  (`:20-27` `FINANCE_PAYMENT_METHOD_LABELS`, `:64-77`).
- **Fallback hiện tại đang phơi mã nội bộ cho sinh viên**:
  `FE/student-nuxt/app/lib/finance.ts:29-37` `titleCaseFromSnakeCase()` render
  `snake_case` thô khi không có nhãn.
- Mã loại phí đã hardcode trong portal:
  `FE/student-nuxt/app/components/cash-wallet/InvoiceDetailDialog.vue:37`
  (`case 'retake_fee':`).
- Trang liên quan: `FE/student-nuxt/app/pages/(protected)/finance/index.vue`,
  `.../finance/[id].vue`, `FE/student-nuxt/app/composables/finance.ts`.
- Hợp đồng API portal có tài liệu riêng phải cập nhật:
  `FE/student-nuxt/docs/student-finance-api.md:508-509`.
- **`PTL` / `KHAC` không xoá được**: đó là mã thu của **provider DNG**
  (`ObligationTypeRegistry.php:198,255,286`,
  `app/Modules/Finance/Dng/Support/DngFeeTypeOptions.php:58,62`), thuộc hợp đồng
  tích hợp bên ngoài. Chỉ **nhãn hiển thị** đổi được, mã thì không.

## Requirements

- Functional: portal render `money_item_status` (Phase 4) thay vì tự suy trạng thái.
- Functional: portal hiển thị ngữ cảnh đợt từ payload Phase 8
  ("Học phí kỳ 2025.1 · Đợt 1/3 · Hạn 15/10 · còn 2 đợt sau").
- Functional: nút trả góp đối tác dùng ngôn ngữ đối tác ("Thanh toán trả góp qua
  cổng"), tách rõ khỏi đợt trả góp nội bộ của trường.
- Functional: không còn `snake_case` thô hiện với sinh viên; nhãn thiếu ⇒ chuỗi
  trung tính, không phải mã.
- Non-functional: portal **không** tự tính tiền và **không** tự suy trạng thái —
  đọc khoá backend.

## Architecture

1. Xoá `titleCaseFromSnakeCase()` khỏi đường hiển thị loại phí; thay bằng nhãn
   backend gửi kèm, fallback là chuỗi trung tính.
2. Xoá `case 'retake_fee'` hardcode ở `InvoiceDetailDialog.vue`; đọc nhãn từ payload.
3. Render `money_item_status.label_student` cho mỗi khoản; không map lại ở FE.
4. Thêm khối ngữ cảnh đợt vào trang chi tiết khoản.
5. Cập nhật `FE/student-nuxt/shared/types/finance.ts` theo payload mới.

## Related Code Files

- Modify: `FE/student-nuxt/app/lib/finance.ts:20-37,64-77`
- Modify: `FE/student-nuxt/shared/types/finance.ts`
- Modify: `FE/student-nuxt/app/components/cash-wallet/InvoiceDetailDialog.vue:37`
- Modify: `FE/student-nuxt/app/pages/(protected)/finance/index.vue`, `.../finance/[id].vue`
- Modify: `FE/student-nuxt/app/composables/finance.ts`
- Modify: `FE/student-nuxt/docs/student-finance-api.md:508-509`

## Implementation Steps

1. Xác nhận quyền commit + cửa sổ deploy của `FE/student-nuxt` (xem Unresolved);
   deploy dùng `scripts/deploy-fe.sh`.
2. Cập nhật type theo payload Phase 4 + Phase 8.
3. Render `label_student`; xoá fallback snake_case và mã hardcode.
4. Khối ngữ cảnh đợt + nhãn nút Foxpay.
5. Cập nhật tài liệu hợp đồng API của portal.
6. Kiểm bằng mắt trên môi trường portal; lint theo tooling của repo đó.

## Todo

- [x] Xác nhận quyền + cửa sổ deploy `FE/student-nuxt` — nested git; deploy vẫn `scripts/deploy-fe.sh` / PR riêng
- [x] Type portal khớp payload mới
- [x] Render `label_student`, xoá snake_case fallback + mã hardcode
- [x] Ngữ cảnh đợt + nhãn Foxpay
- [x] Cập nhật `FE/student-nuxt/docs/student-finance-api.md`

## Success Criteria

- [x] `grep -rn "titleCaseFromSnakeCase" FE/student-nuxt/app` không còn nằm trên
      đường hiển thị loại phí.
- [x] `grep -rn "'retake_fee'" FE/student-nuxt/app` trả 0.
- [x] Portal hiển thị "Đợt 1/3 · Hạn …" cho khoản đã chia đợt.
- [x] Khoản `invalid` hiện thông điệp trung tính, không số tiền, không issue code.
- [x] Nút trả góp đối tác không còn bị hiểu là đợt của trường.

## Risk Assessment

- **Cao:** repo khác ⇒ PR khác, review khác, deploy khác. Backend land trước mà
  portal chưa land thì tiêu chí nghiệm thu của Phase 4/8 **không kiểm được** —
  đó chính là lý do tách phase này ra thay vì để lẫn.
- **Trung bình:** `PTL`/`KHAC` là mã hợp đồng DNG, không được đổi — chỉ đổi nhãn.
- **Thấp:** portal có thể còn màn hình khác đọc cùng khoá; quét cả
  `app/pages` và `app/components` trước khi land.

## Security Considerations

Bề mặt sinh viên chỉ nhận thông điệp trung tính (ADR-0028): không issue code,
không tên bảng, không số tiền của position `invalid`.

## Unresolved questions

- **Deploy đã có tooling trong repo này** (validation 2026-09-06):
  `scripts/deploy-fe.sh` deploy student + lecturer FE theo từng school
  (`scripts/schools.list`), và `scripts/portal-status.sh` kiểm trạng thái.
  Còn lại: `FE/student-nuxt` vẫn là **git repo riêng** nên commit/PR/review đi
  đường khác. Xác nhận cửa sổ deploy và ai merge PR bên đó khi bắt đầu phase này.

## Next Steps

Code portal đã land `FE/student-nuxt`. Plan-wide PHP regression sweep vẫn mở
(Phase 8 Pest `|` harness) — không đóng YAML `completed` cho đến khi gate đó chạy.
