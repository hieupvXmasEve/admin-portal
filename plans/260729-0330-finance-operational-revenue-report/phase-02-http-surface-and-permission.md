---
phase: 2
title: "HTTP Surface and Permission"
status: pending
priority: P1
effort: "0.5d"
dependencies: [1]
---

# Phase 2: HTTP Surface and Permission

## Overview

Route, controller, request validation, permission mới. Mỏng — nhưng permission là phần nhạy cảm: report này lộ số tiền của mọi campus cho người xem.

## Requirements

Functional:
- `GET /finance/revenue` → Inertia `Finance/Revenue/Index`.
- Permission mới `view_finance_revenue_report` gate route + FormRequest.
- Filter qua query string: `semester_ids[]`, `campus_id`, `fee_type`.
- Payload gồm `rows`, `totals`, `breakdowns`, `unattributed`, `filters`, `filter_options`, `computed_at`.

Non-functional:
- Người không có permission → 403, không phải trang rỗng.
- Không phân trang. Số học kỳ nhỏ (7 hiện tại); phân trang một bảng so sánh là vô nghĩa.

## Architecture

Theo đúng khuôn `FinanceReportingController`: FormRequest `authorize()` kiểm permission, controller gọi query, `Inertia::render`.

Khác một điểm cố ý: **không** gọi `FinanceSemesterContextResolver::selectedId()`. Report này đa kỳ; semester context toàn cục không áp dụng. Ghi comment nói rõ để người sau không "sửa cho nhất quán".

### Permission (quyết định validation #2)

Chỉ **tạo** permission `view_finance_revenue_report`. **Không** sửa gán role — user tự set role qua UI.

Sửa duy nhất một file: `config/permission.php`, thêm cạnh `view_finance_reporting`, kèm comment nói rõ đây là gate **toàn trường**, khác `view_finance_reporting` vốn campus-bound.

Đăng ký vào DB bằng:

```bash
php artisan permissions:sync
```

`SyncPermissions` là additive — chỉ thêm permission chưa có, không đụng gì khác.

> **KHÔNG chạy lại `RoleAndPermissionSeeder` để cấp quyền.** `run()` gọi `cleanExistingData()` xoá sạch `RolePermission`, `Permission`, `Role` trước khi dựng lại từ config. Trên môi trường đã có role tuỳ chỉnh, việc này xoá hết. Đây là lỗi đã phát hiện ở vòng validation — đừng "sửa lại cho tiện".

`super_admin` nhận mọi permission qua nhánh riêng trong seeder.

### Không sửa `HandleInertiaRequests.php`

Verification cho thấy claim ban đầu sai:

- Dòng 66 đã ship **toàn bộ** permission code xuống client qua `permissionCodesForUserId()`. `usePermissions.canAny()` (`usePermissions.ts:46`) tự nhận `view_finance_revenue_report`. Menu sidebar hoạt động mà không cần sửa gì.
- List ở dòng ~83 là `$semesterContextPerms` — nó quyết định có ship semester options hay không. Trang này **cố tình bỏ qua semester context**, thêm vào đó là sai chức năng.

### Filter options

`semesters`: mọi kỳ có ít nhất một `student_invoices` không cancelled. `campuses`: từ `campuses` table. `fee_types`: `finance_charges.charge_type` distinct trong phạm vi đã lọc.

## Related Code Files

- Create: `app/Modules/Finance/Http/Web/Admin/FinanceRevenueReportController.php`
- Create: `app/Modules/Finance/Http/Requests/Reporting/GetRevenueReportRequest.php`
- Create: `tests/Feature/Finance/Reporting/RevenueReportPageTest.php`
- Modify: `app/Modules/Finance/routes/web.php` — thêm route sau `reporting.index`
- Modify: `config/permission.php` — file duy nhất cần sửa cho permission
- **Không sửa**: `RoleAndPermissionSeeder.php` (user tự set role), `HandleInertiaRequests.php` (permission đã tự ship)

## Implementation Steps

1. Thêm permission vào `config/permission.php`, chạy `php artisan permissions:sync`.
2. `GetRevenueReportRequest`: `authorize()` trả `$this->user()?->can('view_finance_revenue_report') ?? false`. Rules: `semester_ids` `array`, `semester_ids.*` `integer|exists:semesters,id`, `campus_id` `nullable|integer|exists:campuses,id`, `fee_type` `nullable|string`.
3. Controller + route `->middleware('can:view_finance_revenue_report')`.
4. Test: user không quyền → 403; user có quyền → 200 + `assertInertia` component `Finance/Revenue/Index` và có prop `rows`.
5. Test: filter `campus_id` thu hẹp kết quả đúng.

## Success Criteria

- [ ] User thiếu `view_finance_revenue_report` → 403
- [ ] User có quyền → 200, component `Finance/Revenue/Index`
- [ ] `semester_ids` không hợp lệ → 422
- [ ] Controller không gọi `FinanceSemesterContextResolver`, không đọc `app('campus')`
- [ ] `RoleAndPermissionSeeder.php` và `HandleInertiaRequests.php` không nằm trong diff

## Bàn giao

Sau khi merge, môi trường đã seed cần chạy:

```bash
php artisan permissions:sync
```

Rồi gán `view_finance_revenue_report` cho role mong muốn qua UI quản trị. Report này hiện số tiền của **mọi campus** — cân nhắc trước khi gán cho role campus-bound.

## Risk Assessment

| Rủi ro | Giảm thiểu |
|---|---|
| Ai đó chạy `RoleAndPermissionSeeder` để cấp quyền → xoá sạch role/permission production | Cảnh báo in đậm trong phần Permission. Bàn giao chỉ nêu `permissions:sync`. |
| Cấp quyền cho role campus-bound → lộ tiền campus khác | Ghi cảnh báo ở phần Bàn giao. Quyết định gán role thuộc về user, không hard-code trong seeder. |
| Feature test fail vì CSRF | Repo bật CSRF trong feature test — request POST cần `_token`. Route này là GET nên không vướng, nhưng đừng ngạc nhiên nếu thêm POST sau. |
