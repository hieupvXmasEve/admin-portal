---
title: Administration
description: Người dùng, phân quyền, cơ sở, phòng ban, kết nối ngoài và cấu hình hệ thống.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Users/Index.vue
  - resources/js/pages/Roles/Index.vue
  - resources/js/pages/Campuses/Index.vue
  - resources/js/pages/Admin/Departments/Index.vue
  - resources/js/pages/SystemConfig/Index.vue
  - resources/js/pages/Systems/ActivityLogs.vue
  - resources/js/pages/Admin/EmailMonitoring/Index.vue
---

**Administration** dành cho quản trị viên. Thay đổi ở đây ảnh hưởng tới **mọi người dùng**, không riêng bạn.

Nguyên tắc chung: đổi ít một, ghi lại lý do, và kiểm tra lại bằng một tài khoản thật sau khi đổi.

## Identity & Access — Người dùng và quyền

### Users — Người dùng

**Dùng để làm gì.** Thêm, sửa, khóa tài khoản nhân viên.

**Ai vào được.** Người có quyền xem người dùng.

**Các bước**

1. Vào **Administration → Identity & Access → Users**.
2. Thêm người dùng mới, hoặc mở tài khoản có sẵn để sửa.
3. Gán vai trò và cơ sở được phép truy cập.

**Lưu ý**

- Người dùng đăng nhập bằng Google. Email khai ở đây phải **trùng khớp** email Google của họ, nếu không sẽ không đăng nhập được.
- Nhân viên nghỉ việc thì **vô hiệu hóa tài khoản**, đừng xóa. Xóa sẽ mất dấu vết ai đã làm gì trong quá khứ.
- Cơ sở được gán quyết định người đó thấy dữ liệu của cơ sở nào.

### Roles & Permissions — Vai trò và quyền

**Dùng để làm gì.** Khai báo vai trò và quyền đi kèm. Màn hình tên **Roles**.

**Ai vào được.** Người có quyền xem vai trò.

**Các vai trò sẵn có**

| Vai trò | Phạm vi thường dùng |
| --- | --- |
| Super Admin | Toàn quyền hệ thống |
| Giám Đốc Đào Tạo | Quản lý học vụ toàn trường |
| Trưởng Phòng | Quản lý phạm vi phòng ban |
| Cán Bộ | Tác nghiệp hằng ngày |
| Phụ huynh | Xem thông tin con em |

**Lưu ý**

- Quyền quyết định menu người dùng nhìn thấy. Đồng nghiệp báo "mất menu" thì kiểm tra vai trò của họ ở đây.
- Sửa một vai trò ảnh hưởng **tất cả** người đang mang vai trò đó. Cần quyền riêng cho một người thì tạo vai trò mới, đừng sửa vai trò chung.
- Cấp quyền vừa đủ để làm việc. Quyền thừa là rủi ro, nhất là ở khu vực tài chính.

## Organization — Tổ chức

### Campuses — Cơ sở

**Dùng để làm gì.** Khai báo các cơ sở của trường.

**Các bước.** Vào **Administration → Organization → Campuses**. Bấm **Clear** để xóa bộ lọc.

**Lưu ý.** Cơ sở là ranh giới dữ liệu của toàn hệ thống. Thêm hay đổi cơ sở là việc hiếm và ảnh hưởng rộng — làm khi chắc chắn.

### Departments — Phòng ban

**Dùng để làm gì.** Khai báo phòng ban. Nội dung ở khung **Department List**.

**Ai vào được.** Người có quyền quản lý phòng ban.

## Integrations — Kết nối ngoài

| Trang | Dùng để làm gì |
| --- | --- |
| Canvas Integrations | Cấu hình kết nối tới hệ thống học trực tuyến Canvas |
| Staff Copilot | Theo dõi trợ lý AI dành cho nhân viên |
| AI Provider Settings | Cấu hình nhà cung cấp dịch vụ AI |

**Lưu ý**

- **Canvas Integrations** là chỗ sửa khi màn hình **Canvas Courses** báo kết nối lỗi.
- Phần AI cần quyền riêng. Đổi cấu hình ở đây ảnh hưởng tính năng AI của toàn hệ thống.

## System Operations — Vận hành hệ thống

### System Configuration — Cấu hình hệ thống

**Dùng để làm gì.** Đổi thông tin và nhận diện của hệ thống. Màn hình tên **System configuration**.

**Ai vào được.** Người có quyền xem cấu hình hệ thống.

**Nội dung màn hình.** Khung **Application details** (tên hệ thống, thông tin chung) và **Branding assets** (logo, hình ảnh nhận diện).

**Các bước.** Vào **Administration → System Operations → System Configuration**, sửa rồi lưu. Bấm **Reset** để hoàn tác về giá trị trước đó.

**Lưu ý.** Tên hệ thống và logo hiển thị trên mọi màn hình và trong thư gửi đi. Đổi là mọi người thấy ngay.

### Activity Logs — Nhật ký hoạt động

**Dùng để làm gì.** Xem lịch sử thao tác trên toàn hệ thống.

**Ai vào được.** Người có quyền xem nhật ký hệ thống.

**Lưu ý.** Khác với **Student Actions Audit** — bên đó chỉ ghi thao tác trên hồ sơ sinh viên, ở đây là toàn hệ thống.

### Email Monitoring — Giám sát email

**Dùng để làm gì.** Theo dõi sức khỏe hệ thống gửi thư.

**Ai vào được.** Người có quyền xem hệ thống email.

**Lưu ý.** Xem ở đây khi có báo cáo thư không tới, trước khi đi sửa từng cấu hình.

## Việc thường gặp

| Tình huống | Thứ tự làm |
| --- | --- |
| Nhân viên mới vào làm | Users (thêm tài khoản, gán vai trò và cơ sở) |
| "Tôi không thấy menu X" | Users (xem vai trò) → Roles & Permissions |
| Nhân viên nghỉ việc | Users (vô hiệu hóa, không xóa) |
| Canvas báo lỗi kết nối | Canvas Integrations |
| Đổi logo, tên hiển thị | System Configuration |
| Điều tra một thay đổi bất thường | Activity Logs |
| Thư toàn trường không gửi được | Email Monitoring → Email Configuration |
