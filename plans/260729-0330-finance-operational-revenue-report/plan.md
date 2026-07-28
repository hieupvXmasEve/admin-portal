---
title: "Finance Operational Revenue Report"
description: "Trang /finance/revenue: doanh thu phát sinh + thực thu, so sánh nhiều học kỳ, toàn trường, breakdown theo campus/loại phí."
status: pending
priority: P2
effort: "2-3d"
branch: dev
tags: [finance, report, inertia, reporting]
blockedBy: []
blocks: []
created: 2026-07-29
---

# Finance Operational Revenue Report

## Overview

Trang report mới `/finance/revenue` trả lời câu hỏi **"doanh thu từng học kỳ là bao nhiêu"** — thứ mà 4 lens hiện có trong `/finance/reporting` không trả lời được.

Không mở rộng `collection-progress`. Lens đó là **worklist thu hồi công nợ**: 1 row = 1 sinh viên, 1 học kỳ tại 1 thời điểm, campus-bound. Revenue là **bảng tổng hợp**: 1 row = 1 học kỳ, nhiều kỳ cạnh nhau, toàn trường. Nhồi vào cùng shell làm card "Semester context" ở đầu trang sai ngữ cảnh và làm nặng thêm hiểu nhầm hiện có (người dùng đã tưởng collection-progress = doanh thu).

## Quyết định kiến trúc

**Tái dùng `SettlementPositionReader`, KHÔNG viết SQL aggregation song song.**

Quy mô hiện tại (verify trên DB `asia`, 2026-07-29): 988 `invoice_lines`, 553 `student_invoices`, 7 semesters, 2 campuses. Ở quy mô này `batch()` toàn bộ line là một lần load — không cần tối ưu.

Nếu viết SQL riêng thì phải chép lại 11 validity gate của công thức canonical. Hai bản cài đặt sẽ **lệch âm thầm** — số trên trang doanh thu sẽ khác số trên collection-progress mà không ai biết tại sao. Tái dùng reader cho kết quả khớp **theo cấu trúc**, không phải nhờ may mắn.

Ceiling: khoảng ~50k invoice_lines. Ghi `ponytail:` comment kèm đường nâng cấp (SQL CTE) tại query.

## Công thức doanh thu (canonical, từ `CurrentPayableSettlementPositionReader`)

Mỗi payable line:

```
gross     = invoice_lines.amount_snapshot
discount  = Σ discount_allocations.amount
cash      = Σ payment_applications.amount  (payments.status = 'completed')
credit    = Σ credit_applications.amount
remaining = gross − discount − cash − credit
netDue    = gross − discount
```

Cột báo cáo (chỉ cộng từ line **hợp lệ**):

| Cột | Công thức | Ý nghĩa |
|---|---|---|
| Doanh thu phát sinh | Σ netDue | Accrual. Đây là "doanh thu theo kỳ". |
| Đã thu | Σ cash | Tiền mặt thực vào, đã gán invoice. |
| Miễn giảm áp dụng | Σ credit | **Không phải doanh thu, không phải tiền thu** (ADR-0030). Chỉ giảm còn phải thu. |
| Còn phải thu | Σ remaining | |
| Tỷ lệ thu | cash / netDue | |
| Cần kiểm tra | count + Σ raw gross | Line invalid. Hiển thị riêng, **không bao giờ gộp vào tổng**. |

## Bẫy phải tránh

**Không được `SUM(invoice_lines.amount_snapshot)`.** Line có `gross <= 0` bị đánh `PAYABLE_LINE_NOT_COLLECTIBLE` → invalid. Đó chính là cơ chế giữ các dòng `finance_charges` âm legacy (migration ADR-0030 đang dở) ra khỏi tổng. Cộng thẳng `amount_snapshot` sẽ trừ hai lần các khoản voucher/scholarship vừa có dòng âm vừa có `invoice_discounts`.

**Tiền chưa phân bổ không quy được về kỳ.** `payments` không có `semester_id`. Đường duy nhất từ payment tới semester là qua `payment_applications` → `invoice_lines` → `student_invoices`. Tiền đã thu nhưng chưa gán invoice **không thuộc kỳ nào**. Hiển thị thành dải riêng "Chưa phân bổ kỳ", không cộng vào cột nào. Cùng cách xử lý cho refund/forfeit qua `payment_surplus_dispositions`.

**Bỏ qua campus context.** Report này luôn toàn trường. `app('campus')` phải bị bỏ qua có chủ đích, ghi comment rõ, và UI phải gắn badge "Toàn trường" để không ai tưởng đang xem campus của mình.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Bảng doanh thu nhiều kỳ: phát sinh + thực thu + còn phải thu + % tăng trưởng | P1 |
| 2 | Số khớp chính xác với Collection Progress khi lọc cùng kỳ + cùng campus | P1 |
| 3 | Breakdown theo campus và loại phí | P2 |
| 4 | Dải "chưa phân bổ kỳ" hiển thị tách bạch, không bóp méo tổng | P2 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Revenue Query](./phase-01-start.md) | Pending |
| 2 | [Phase 2: HTTP Surface and Permission](./phase-02-http-surface-and-permission.md) | Pending |
| 3 | [Phase 3: Revenue Report UI](./phase-03-revenue-report-ui.md) | Pending |

## Success Criteria

- [ ] `/finance/revenue` render bảng 1 row = 1 học kỳ, sort theo `start_date` giảm dần
- [ ] Mỗi row có: phát sinh, đã thu, miễn giảm, còn phải thu, tỷ lệ thu, % tăng trưởng so kỳ liền trước
- [ ] Line invalid đếm riêng, không nằm trong bất kỳ cột tiền nào
- [ ] Breakdown theo campus + loại phí
- [ ] Dải "chưa phân bổ kỳ": tiền chưa gán, refund, forfeit
- [ ] Test đối chiếu: `cash` của kỳ X == `paid_total` của Collection Progress cùng kỳ + cùng campus
- [ ] Permission mới gate được route, người không có quyền nhận 403

## Không làm (out of scope)

- Khoá sổ kỳ / snapshot bất biến — cần bảng period-close, chưa có. Số vẫn tính lại từ ledger nên có thể đổi khi ai đó void charge cũ.
- Export Excel/PDF.
- Trend theo ngày trong kỳ.
- Doanh thu ghi nhận theo chuẩn kế toán (rải theo thời gian học). Report này gán doanh thu theo `student_invoices.semester_id`.

## Open questions

None. Xem `## Validation Log`.

## Validation Log

### Session 1 — 2026-07-29

#### Verification Results

- Claims checked: 12 | Verified: 9 | Failed: 2 | Unverified: 1
- Tier: Standard (3 phases)

| # | Claim | Kết quả |
|---|---|---|
| 1 | Phase 2 cần sửa `HandleInertiaRequests.php` để share permission | **FAILED** — `HandleInertiaRequests.php:66` đã ship toàn bộ permission code qua `permissionCodesForUserId()`. `usePermissions.canAny()` tự nhận. List ở dòng ~83 là `$semesterContextPerms`, thêm vào đó sẽ **sai** (bơm semester options cho trang cố tình bỏ qua semester context). |
| 2 | "Trích `unappliedByStudent`, hai bên cùng dùng" | **FAILED** — công thức unapplied lặp ở ~10 query file (`ListPaymentsQuery`, `GetStudentFeeSummaryQuery`, `Student360/*`, …), không phải 2. |
| 3 | Môi trường đã seed: chạy lại `RoleAndPermissionSeeder` để cấp quyền | **FAILED (phát hiện thêm)** — seeder gọi `cleanExistingData()` xoá sạch `RolePermission`, `Permission`, `Role`. Đường đúng là `php artisan permissions:sync` (additive, không xoá). |
| 4 | `semesters.start_date` tồn tại | VERIFIED |
| 5 | `SettlementPositionScope::payableLine()` tồn tại | VERIFIED (`SettlementPositionScope.php:23`) |
| 6 | `Money::vnd()` / `Money::zero()` | VERIFIED |
| 7 | `GetCollectionProgressSummaryQuery` có `paid_total` | VERIFIED |
| 8 | `config/permission.php` có `view_finance_reporting` | VERIFIED (~dòng 417) |
| 9 | Chưa có `resources/js/pages/Finance/Revenue/` | VERIFIED — không xung đột |
| 10 | `usePermissions.canAny()` đọc `requiredPermissions` | VERIFIED (`usePermissions.ts:46`) |
| 11 | `payment_surplus_dispositions` có `TYPE_REFUND`/`TYPE_RETAIN_FORFEIT` | VERIFIED |
| 12 | Số test đỏ sẵn có của suite Finance | UNVERIFIED — chưa chạy suite trong session này. Phase 1 phải lấy baseline trước khi kết luận hồi quy. |

#### Decisions

1. **Tiền chưa phân bổ** — tách thành support class dùng chung cho đúng 2 query reporting (Revenue + `ListCollectionProgressQuery`). 8 file còn lại để nguyên; hợp nhất toàn bộ là refactor riêng, ngoài phạm vi.
2. **Permission** — chỉ tạo `view_finance_revenue_report` trong `config/permission.php` + `permissions:sync`. **Không** sửa gán role trong seeder; user tự set role.
3. **Hoàn tiền** — nguồn chuẩn là `payment_surplus_dispositions` (type `refund`/`retain_forfeit`), khớp `ListCollectionProgressQuery`. Không đọc `payments.status='refunded'`.
4. **Neo học kỳ** — `student_invoices.semester_id`. Hiện 0/988 dòng lệch khỏi `finance_charges.semester_id`. Chọn invoice để test đối chiếu với Collection Progress có nghĩa.

#### Whole-Plan Consistency Sweep

Đã đọc lại `plan.md` + 3 phase file sau khi propagate. Không còn mâu thuẫn:
- `HandleInertiaRequests.php` đã gỡ khỏi danh sách sửa của phase 2.
- Hướng dẫn cấp quyền đổi từ "chạy lại seeder" sang `permissions:sync` ở phase 2.
- Phạm vi tách `unapplied` nói rõ 2 site ở phase 1.
- Neo học kỳ ghi rõ `student_invoices.semester_id` ở cả plan và phase 1.
- Mục "Role nào được xem" đã gỡ khỏi phase 2 (user tự set).

<!-- slug: finance-operational-revenue-report -->
