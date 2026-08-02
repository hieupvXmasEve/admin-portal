---
title: Merchandise Store — user guide
description: Hướng dẫn dùng tính năng Merchandise Store cho staff (quản trị) và sinh viên (đổi quà bằng Gold).
audience:
    - Staff vận hành cửa hàng
    - Sinh viên
status: current
owner: Merchandise Team
last_verified: 2026-08-02
scope: merchandise-user-guide
source_of_truth:
    - app/Modules/Merchandise/routes/web.php
    - resources/js/pages/Merchandise/*
    - resources/js/pages/RedemptionOrders/*
    - docs/api/student/merchandise.md
    - plans/260801-0203-merchandise-store/index.md
---

# Merchandise Store — hướng dẫn sử dụng

Merchandise Store cho phép sinh viên dùng **Gold** (điểm thưởng) đổi quà vật lý
(áo, mũ, phụ kiện...). Staff quản lý sản phẩm/tồn kho ở khu vực admin (Swinx,
Inertia/Vue), sinh viên đổi quà ở student portal riêng (`FE/student-nuxt`).

Phụ huynh **không** truy cập được Store/Gold của con (chỉ sinh viên tự đăng
nhập mới thấy).

## 1. Staff — Quản lý sản phẩm (`/merchandise`)

Quyền cần: `view_merchandise` (xem), `create_merchandise` (tạo),
`edit_merchandise` (sửa), `archive_merchandise` (ẩn/xoá), `manage_merchandise_image`,
`manage_merchandise_variant`, `adjust_merchandise_stock`, `view_merchandise_audit`.

- **Danh sách** (`Merchandise/Index.vue`): xem toàn bộ sản phẩm, trạng thái
  quản trị (`active` / `coming_soon` / `hidden` / `archived`).
- **Tạo/Sửa** (`Merchandise/Form.vue`): tên, mô tả, giá Gold, trạng thái, ảnh
  (upload qua module Upload chung, không giới hạn 1 ảnh — chọn 1 ảnh làm
  primary), biến thể (variant: màu/size) theo từng campus.
- **Chi tiết** (`Merchandise/Show.vue`): xem variant theo campus, tồn kho
  từng variant, lịch sử điều chỉnh tồn kho (`variants.stock-movements`).
- **Điều chỉnh tồn kho**: mỗi lần adjust đều ghi log (`performed_by` +
  before/after) — không sửa tay số liệu, luôn qua form adjust.
- **Archive**: sản phẩm archived biến mất khỏi Store sinh viên nhưng đơn cũ
  vẫn hiển thị đúng tên/giá tại thời điểm đặt (snapshot).

**Availability hiển thị cho sinh viên** không phải cột riêng — suy ra từ
trạng thái quản trị + tồn kho variant theo campus (`available`,
`out_of_stock`, `coming_soon`, `not_visible`).

## 2. Staff — Xử lý đơn đổi quà (`/redemption-orders`)

Quyền cần: `view_redemption_order`, `approve_redemption_order`,
`reject_redemption_order`, `confirm_redemption_collection`,
`mark_redemption_shipped`, `cancel_redemption_order`.

Staff chỉ thấy/thao tác đơn thuộc campus mình được phân quyền (campus lấy từ
snapshot của đơn, không lấy từ session).

### Luồng trạng thái đơn

```
pending_review --approve--> approved
pending_review --reject(lý do)--> rejected            (hoàn Gold + tồn kho)
pending_review --cancel--> cancelled                    (hoàn Gold + tồn kho)

approved --đánh dấu sẵn sàng--> ready_for_collection
approved --đánh dấu đã gửi--> shipped                   (kết thúc)
approved --yêu cầu huỷ--> cancellation_requested

ready_for_collection --xác nhận đã nhận--> collected     (kết thúc)
ready_for_collection --đánh dấu quá hạn--> pickup_overdue
ready_for_collection --yêu cầu huỷ--> cancellation_requested

pickup_overdue --gia hạn (mở lại ready)--> ready_for_collection
pickup_overdue --xác nhận đã nhận--> collected            (kết thúc)
pickup_overdue --staff huỷ--> cancelled                   (hoàn Gold + tồn kho)

cancellation_requested --staff chấp nhận--> cancelled     (hoàn Gold + tồn kho)
cancellation_requested --staff từ chối--> <trạng thái trước đó>
```

`collected`, `shipped`, `rejected`, `cancelled` là trạng thái cuối — không
thao tác nào đổi được nữa.

### Việc staff làm ở màn hình chi tiết đơn

| Hành động | Khi nào dùng |
|---|---|
| Approve | Duyệt đơn `pending_review`, không cần nhập lý do (mặc định hiện tại). |
| Reject | Từ chối đơn `pending_review`, **bắt buộc nhập lý do** — hoàn Gold + tồn kho ngay. |
| Mark ready for collection | Đơn `approved`, hàng đã sẵn sàng tại điểm nhận. |
| Mark as shipped | Đơn `approved` chọn ship — staff tự gửi hàng qua nền tảng ngoài (không có module shipping riêng), xong thì đánh dấu `shipped`. |
| Extend pickup deadline | Gia hạn hạn nhận hàng cho đơn `ready_for_collection`/`pickup_overdue`. |
| Confirm collected | Sinh viên đã đến nhận hàng trực tiếp. |
| Mark pickup overdue | Sinh viên quá hạn chưa đến nhận (đánh tay, chưa có cron tự động). |
| Cancel (từ pickup_overdue) | Staff chủ động huỷ đơn quá hạn lâu — hoàn Gold + tồn kho. |
| Accept cancellation | Đồng ý yêu cầu huỷ của sinh viên — hoàn Gold + tồn kho. |
| Reject cancellation | Từ chối yêu cầu huỷ — đơn quay lại trạng thái trước đó. |

Mọi thao tác hoàn Gold/tồn kho chỉ chạy **đúng một lần** cho mỗi đơn (khoá ở
tầng DB, không thể hoàn 2 lần dù bấm nhiều lần/nhiều tab).

## 3. Staff — Báo cáo (`/merchandise/reports`)

Quyền cần: `view_merchandise_report`. Phạm vi campus giống các trang trên
(theo quyền được cấp, không theo session).

Nội dung: đơn theo trạng thái, Gold đã dùng/đã hoàn (đối chiếu với ledger),
sản phẩm đổi nhiều nhất, tồn kho theo campus. Xuất Excel: **chưa làm**
(deferred, chờ chốt với stakeholder).

## 4. Sinh viên — Đổi quà (student portal, `FE/student-nuxt`)

Chi tiết API/response: [docs/api/student/merchandise.md](../../api/student/merchandise.md).

1. **Store** — xem danh sách/chi tiết sản phẩm, chỉ thấy variant thuộc
   campus của mình.
2. **Giỏ hàng** — client-side only; khi bấm đặt hàng hệ thống kiểm tra lại
   giá/tồn kho mới nhất (giá/tồn có thể đổi từ lúc thêm vào giỏ đến lúc đặt).
3. **Đặt hàng** — chọn `pickup` (nhận tại điểm) hoặc `shipping` (nhập địa chỉ
   ngay trên đơn, không xác nhận lại, không phụ phí). Tối đa 5 sản
   phẩm/dòng, tối đa 5 sản phẩm cùng loại/đơn. Trừ Gold theo số dư ví hiện
   tại lúc đặt (không giữ chỗ trước).
4. **Lịch sử đơn** — xem trạng thái, timeline, thông tin nhận hàng/giao
   hàng. Đổi tên/giá sản phẩm sau này không ảnh hưởng đơn cũ (snapshot).
5. **Huỷ đơn**:
   - Đơn còn `pending_review`: huỷ ngay, hoàn Gold + tồn kho tức thì.
   - Đơn đã `approved` / `ready_for_collection` / `pickup_overdue`: gửi yêu
     cầu huỷ, chờ staff duyệt mới hoàn.
   - Đơn đã `collected`/`shipped`/`rejected`/`cancelled` hoặc đã có yêu cầu
     huỷ: không huỷ được nữa.
6. **Dashboard** — số Gold hiện có, tổng Gold đã đổi (không tính đơn
   `rejected`/`cancelled`), tổng số đơn đã đặt.

## 5. Ngoài phạm vi hiện tại

- **Gold Transfer** (chuyển Gold giữa sinh viên): chưa xây, để sau khi hỏi
  lại stakeholder.
- **Đổi variant sau khi đặt**: không hỗ trợ — phải huỷ rồi đặt lại.
- **Cấu hình pickup/shipping theo từng sản phẩm**: chưa có, mọi sản phẩm
  hiện cho cả 2 hình thức.
- **Email song song với thông báo trong app**: chưa gửi, mới có notification
  trong portal.
