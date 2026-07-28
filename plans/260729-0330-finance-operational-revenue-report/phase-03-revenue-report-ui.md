---
phase: 3
title: "Revenue Report UI"
status: pending
priority: P2
effort: "1d"
dependencies: [2]
---

# Phase 3: Revenue Report UI

## Overview

Trang Vue: bảng so sánh nhiều kỳ + breakdown + dải chưa phân bổ. Trang này phải tự nói rõ nó là số gì — hiểu nhầm về ngữ nghĩa doanh thu chính là lý do plan tồn tại.

## Requirements

Functional:
- Bảng chính: 1 row = 1 học kỳ, sort mới nhất trước. Cột: Học kỳ, Phát sinh, Đã thu, Miễn giảm, Còn phải thu, Tỷ lệ thu, Tăng trưởng, Cần kiểm tra.
- Row tổng cộng ở cuối.
- Breakdown theo campus + loại phí (tab hoặc hai bảng cạnh nhau).
- Dải "Chưa phân bổ kỳ" tách hẳn khỏi bảng chính.
- Filter: học kỳ (multi), campus, loại phí.

Non-functional:
- Badge "Toàn trường" ở header, luôn hiện.
- Cột `Cần kiểm tra` khác 0 → nhấn mạnh trực quan (không chỉ là số xám).
- Responsive: bảng cuộn ngang trong container riêng, body không cuộn ngang.

## Architecture

Dùng lại pattern của `CollectionProgress.vue`: `Card`, `Select`, `Badge` từ `@/components/ui`, filter đẩy lên URL qua `router.get` với `preserveState`.

**Không** dùng `DataPagination` — trang này không phân trang. (Component đó từng gây crash vì prop mismatch, và ở đây nó vô nghĩa.)

**Không** nhúng vào `Finance/Reporting/Index.vue`. Trang độc lập, layout `AppLayout`.

### Ngữ nghĩa phải hiện trên UI

Đây là yêu cầu chức năng, không phải trang trí:

- Tooltip/chú thích cột **Miễn giảm**: "Giảm còn phải thu. Không phải doanh thu, không phải tiền đã thu."
- Chú thích dải **Chưa phân bổ kỳ**: "Tiền đã thu nhưng chưa gán hoá đơn nên không quy được về học kỳ nào."
- Chú thích cột **Cần kiểm tra**: "Dòng phí có bất thường settlement, đã loại khỏi mọi cột tiền."
- Ghi chú cuối trang: "Số tính lại từ ledger tại thời điểm xem, chưa có khoá sổ kỳ."

### Điều hướng

Thêm mục sidebar "Doanh thu" dưới "Finance Reporting", `requiredPermissions: ['view_finance_revenue_report']`.

## Related Code Files

- Create: `resources/js/pages/Finance/Revenue/Index.vue`
- Modify: `resources/js/constants/finance-routes.ts` — thêm `REVENUE_INDEX: 'finance.revenue.index'` + entry trong `financeRoutes`
- Modify: `resources/js/constants/menu-sidebar.ts` — mục sidebar mới
- Read trước khi viết: `resources/js/pages/Finance/Reporting/CollectionProgress.vue` (pattern filter + format tiền)

## Implementation Steps

1. `finance-routes.ts` + `menu-sidebar.ts`.
2. `Index.vue`: interface props khớp payload phase 2, bảng chính + row tổng.
3. Filter bar, đẩy state lên URL.
4. Breakdown campus + loại phí.
5. Dải chưa phân bổ + toàn bộ chú thích ngữ nghĩa ở trên.
6. Kiểm bằng browser: 1440 và 375. Xác nhận bảng cuộn trong container, body không cuộn ngang.

## Success Criteria

- [ ] Bảng render đúng thứ tự kỳ, có row tổng
- [ ] Badge "Toàn trường" luôn hiện
- [ ] Cả 4 chú thích ngữ nghĩa có mặt
- [ ] `Cần kiểm tra` > 0 được nhấn mạnh
- [ ] Filter đổi URL và giữ khi reload
- [ ] Sidebar chỉ hiện với người có permission
- [ ] 375px không tràn ngang

## Risk Assessment

| Rủi ro | Giảm thiểu |
|---|---|
| Người dùng lại hiểu nhầm "Đã thu" = toàn bộ tiền vào | Dải "Chưa phân bổ kỳ" hiện ngay dưới bảng chính, không giấu trong tab. |
| Type-check OOM trong container dev | `npm run type-check` toàn dự án bị SIGKILL trong `swinx-app-dev`. Dùng eslint từng file, hoặc type-check trên host/CI. |
| Số tiền lớn tràn cột | Format VND rút gọn, giữ số đầy đủ trong `title`. |
