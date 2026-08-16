---
title: Store & Clubs
description: Câu lạc bộ sinh viên và cửa hàng đổi Gold.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Clubs/Index.vue
  - resources/js/pages/Merchandise/Index.vue
  - resources/js/pages/Merchandise/Form.vue
  - resources/js/pages/Merchandise/Show.vue
  - resources/js/pages/Merchandise/Reports/Index.vue
  - resources/js/pages/RedemptionOrders/Index.vue
  - resources/js/pages/RedemptionOrders/Show.vue
  - app/Modules/Merchandise/routes/web.php
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- Campus Operations split into "Campus" (Rooms + Events) and "Store & Clubs" (Clubs + Merchandise); page moved from campus-operations/index.md, plans/260816-2125-sidebar-menu-ia-restructure/). -->

**Store & Clubs** quản lý câu lạc bộ sinh viên và cửa hàng đổi Gold — hai màn hình thuần HQ, tách khỏi phòng ốc/sự kiện của nhóm **Campus**.

## Clubs — Câu lạc bộ

**Dùng để làm gì.** Quản lý câu lạc bộ sinh viên và thành viên.

**Ai vào được.** Người có quyền xem câu lạc bộ.

**Các bước.** Vào **Store & Clubs → Clubs**.

## Merchandise — Cửa hàng đổi Gold

Sinh viên dùng **Gold** (điểm thưởng) đổi quà ở portal riêng của sinh viên
(không phải màn hình này). Khu vực **Merchandise** ở đây là nơi **staff**
quản lý sản phẩm và xử lý đơn đổi quà.

### Store — Danh sách sản phẩm

**Dùng để làm gì.** Khai báo sản phẩm đổi quà: tên, mô tả, giá Gold, ảnh,
biến thể (màu/size) theo từng cơ sở, và tồn kho.

**Ai vào được.** Người có quyền xem/tạo/sửa sản phẩm.

**Các bước**

1. Vào **Store & Clubs → Merchandise → Store**.
2. Thêm sản phẩm mới hoặc mở sản phẩm có sẵn để sửa.
3. Trong màn hình chi tiết, thêm biến thể theo cơ sở và điều chỉnh tồn kho —
   luôn qua form điều chỉnh, không sửa số liệu bằng tay.

**Lưu ý**

- Ẩn/Archive sản phẩm thì sản phẩm biến mất khỏi cửa hàng của sinh viên,
  nhưng đơn cũ đã đặt vẫn giữ nguyên tên/giá lúc đặt.
- Sinh viên chỉ thấy sản phẩm/biến thể thuộc đúng cơ sở của mình.

### Redemption Orders — Đơn đổi quà

**Dùng để làm gì.** Duyệt, từ chối, và theo dõi đơn đổi quà của sinh viên
đến khi giao/nhận xong.

**Ai vào được.** Người có quyền duyệt đơn đổi quà. Chỉ thấy đơn thuộc cơ sở
mình được phân quyền.

**Các bước**

1. Vào **Store & Clubs → Merchandise → Redemption Orders**.
2. Mở đơn cần xử lý.
3. Tuỳ trạng thái đơn, bấm hành động phù hợp: **Approve**, **Reject** (bắt
   buộc nhập lý do), **Mark ready for collection**, **Mark as shipped**,
   **Confirm collected**, **Mark pickup overdue**, **Extend pickup
   deadline**, hoặc xử lý yêu cầu huỷ (**Accept**/**Reject cancellation**).

**Lưu ý**

- Reject hoặc huỷ đơn sẽ hoàn Gold và tồn kho cho sinh viên — chỉ hoàn đúng
  một lần cho mỗi đơn dù bấm nhiều lần.
- Đơn chọn giao hàng (`shipping`): staff tự gửi hàng qua nền tảng ngoài rồi
  mới bấm **Mark as shipped** — hệ thống không có module vận chuyển riêng.
- Sinh viên quá hạn chưa đến nhận hàng: staff tự đánh dấu **Mark pickup
  overdue** (chưa có tự động).

### Reports — Báo cáo Merchandise

**Dùng để làm gì.** Xem đơn theo trạng thái, Gold đã dùng/đã hoàn, sản phẩm
đổi nhiều nhất, tồn kho theo cơ sở.

**Ai vào được.** Người có quyền xem báo cáo Merchandise.

**Lưu ý.** Xuất Excel: chưa có, còn chờ chốt.

## Việc thường gặp

| Tình huống | Thứ tự làm |
| --- | --- |
| Sinh viên báo đơn đổi quà chưa được duyệt | Redemption Orders → tìm đơn theo mã |
| Cần thêm sản phẩm mới lên cửa hàng | Merchandise → Store → thêm sản phẩm + biến thể theo cơ sở |
