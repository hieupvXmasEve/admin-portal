---
title: Communications
description: Cấu hình email, mẫu thư, gửi hàng loạt, thông báo và theo dõi tình trạng gửi.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Admin/EmailConfiguration/Index.vue
  - resources/js/pages/Admin/EmailTemplate/Index.vue
  - resources/js/pages/Admin/BulkEmail/Index.vue
  - resources/js/pages/Admin/EmailLog/Index.vue
  - resources/js/pages/Admin/Notifications/Send.vue
  - resources/js/pages/Admin/NotificationTemplate/Index.vue
  - resources/js/pages/Admin/Notifications/Ops/Outbox.vue
  - resources/js/pages/Admin/Notifications/Ops/Messages.vue
  - resources/js/pages/Admin/Notifications/Ops/Deliveries.vue
---

**Communications** là nơi gửi thư và thông báo ra ngoài, cùng công cụ kiểm tra thư đã tới nơi chưa.

Hai nhóm: **Email** (thư điện tử) và **Notification Management** (thông báo trong hệ thống).

Đây là khu vực gửi thông tin **ra ngoài trường**. Gửi nhầm không thu hồi được — luôn thử với chính mình trước.

## Email

### Email Configuration — Cấu hình gửi thư

**Dùng để làm gì.** Khai báo máy chủ gửi thư cho từng cơ sở. Màn hình tên **SMTP Configuration**.

**Ai vào được.** Người có quyền quản lý hệ thống email.

**Nội dung màn hình.** Các thẻ: **Total Configurations** (tổng cấu hình), **Active Configurations** (đang dùng), **Tested Configurations** (đã thử thành công), **Success Rate** (tỉ lệ gửi thành công), và bảng **Configuration by Campus** (cấu hình theo cơ sở).

**Lưu ý**

- **Thử cấu hình trước khi đưa vào dùng.** Cấu hình sai thì mọi thư đều rớt, mà không ai biết cho tới khi có người phàn nàn.
- **Success Rate** tụt đột ngột là dấu hiệu cần kiểm tra ngay.

### Email Templates — Mẫu thư

**Dùng để làm gì.** Soạn sẵn mẫu thư để dùng lại: thư báo trúng tuyển, nhắc học phí, thông báo lịch thi.

**Các bước.** Vào **Communications → Email → Email Templates**, tạo hoặc sửa mẫu.

**Lưu ý.** Mẫu có chỗ điền tự động (tên sinh viên, số tiền, hạn nộp). Kiểm tra bằng cách gửi thử cho chính mình trước khi dùng cho cả nhóm.

### Bulk Email — Gửi thư hàng loạt

**Dùng để làm gì.** Gửi một thư cho nhiều người cùng lúc. Màn hình tên **Bulk Email Composer**.

**Các bước**

1. Vào **Communications → Email → Bulk Email**.
2. Chọn nhóm người nhận.
3. Chọn mẫu thư hoặc soạn nội dung.
4. Kiểm tra lại rồi gửi.

**Lưu ý**

- **Gửi thử cho mình trước.** Đây là bước không được bỏ.
- Đọc kỹ số lượng người nhận trước khi bấm gửi. Con số bất thường nghĩa là chọn nhầm nhóm.
- Thư đã gửi không thu hồi được.

### Email History — Lịch sử thư

**Dùng để làm gì.** Tra thư đã gửi và tình trạng của từng thư. Màn hình tên **Email Logs**.

**Các bước**

1. Vào **Communications → Email → Email History**.
2. Lọc để tìm thư cần tra.
3. Bấm **Clear** để xóa bộ lọc.

**Lưu ý.** Khi sinh viên nói "không nhận được thư", tra ở đây trước. Thư gửi thành công mà người nhận không thấy thường nằm ở hộp thư rác của họ.

## Notification Management

### Send Notification — Gửi thông báo

**Dùng để làm gì.** Gửi thông báo tới sinh viên hoặc nhân viên trong hệ thống.

**Các bước**

1. Vào **Communications → Notification Management → Send Notification**.
2. Chọn người nhận ở khung **Recipients**.
3. Điền nội dung ở khung **Notification Details**.
4. Gửi.

### Email Templates (thông báo)

**Dùng để làm gì.** Mẫu thư đi kèm thông báo. Màn hình tên **Notification Email Templates**, nội dung ở khung **Templates**.

**Lưu ý.** Khác với **Email Templates** ở nhóm Email. Nhóm này dành riêng cho thông báo hệ thống.

### Ops — Theo dõi gửi

Ba màn hình để kiểm tra thông báo có đi được không.

| Trang | Dùng để làm gì |
| --- | --- |
| Outbox | Thông báo đang chờ gửi |
| Messages | Nội dung thông báo đã tạo |
| Deliveries | Kết quả gửi tới từng người nhận |

**Ai vào được.** Người có quyền xem vận hành thông báo.

**Lưu ý**

- **Outbox** ùn nhiều nghĩa là hệ thống gửi đang tắc. Báo bộ phận kỹ thuật.
- Khi có người báo không nhận được, tra theo thứ tự: **Messages** (đã tạo chưa) → **Outbox** (đã gửi đi chưa) → **Deliveries** (tới nơi chưa).

## Việc thường gặp

| Tình huống | Thứ tự làm |
| --- | --- |
| Gửi thông báo lịch thi cho cả khóa | Email Templates → Bulk Email (gửi thử trước) |
| Sinh viên báo không nhận được thư | Email History → kiểm tra hộp thư rác của họ |
| Thông báo không tới nơi | Messages → Outbox → Deliveries |
| Thư của cả trường đột nhiên không gửi được | Email Configuration (xem Success Rate) |
| Nhắc hạn đóng học phí | Bulk Email, hoặc DNG Due Reminders ở khu vực Finance Office |
