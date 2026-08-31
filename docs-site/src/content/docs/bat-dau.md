---
title: Bắt đầu
description: Đăng nhập, chọn cơ sở, cách đọc màn hình chính và các thao tác dùng chung.
source:
  - resources/js/pages/Auth/Login.vue
  - resources/js/pages/SelectCampus.vue
  - resources/js/pages/Dashboard/Dashboard.vue
  - resources/js/components/AppSidebar.vue
  - resources/js/components/CampusSwitcher.vue
  - resources/js/constants/menu-sidebar.ts
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

Chương này giúp bạn vào được hệ thống và hiểu bố cục màn hình. Đọc một lần là đủ dùng cho mọi chương sau.

## Đăng nhập

**Dùng để làm gì.** Vào hệ thống bằng tài khoản nhà trường cấp.

**Các bước**

1. Mở địa chỉ Swinx do nhà trường cung cấp.
2. Bấm **Sign in with Google** (Đăng nhập bằng Google).
3. Chọn tài khoản email nhà trường cấp cho bạn.

**Lưu ý**

- Hệ thống **chỉ đăng nhập bằng Google**, không có ô nhập mật khẩu riêng.
- Nếu hiện thông báo lỗi kèm địa chỉ email, nghĩa là email đó chưa được cấp quyền vào hệ thống. Liên hệ quản trị viên để được thêm tài khoản.
- Đăng nhập nhầm tài khoản cá nhân là lỗi thường gặp nhất. Kiểm tra lại email hiển thị ở góc trên bên phải.

## Chọn cơ sở

**Dùng để làm gì.** Chọn cơ sở bạn đang làm việc. Toàn bộ dữ liệu hiển thị sau đó — sinh viên, lớp môn, phòng học, học phí — đều thuộc cơ sở đang chọn.

**Các bước**

1. Sau khi đăng nhập, màn hình **Select Your Campus** hiện các cơ sở bạn được phép truy cập.
2. Bấm vào thẻ cơ sở cần làm việc. Thẻ được chọn có viền xanh.
3. Xác nhận để vào hệ thống.
4. Đang dùng hệ thống mà muốn đổi cơ sở: bấm vào logo ở **góc trên cùng cột menu bên trái** (dòng có tên hệ thống và `Campus: ...`), rồi chọn cơ sở trong danh sách hiện ra.

**Lưu ý**
- Nếu chỉ được cấp một cơ sở, hệ thống tự chọn sẵn cho bạn và logo không hiện danh sách.
- Tên cơ sở đang dùng luôn hiển thị ngay dưới tên hệ thống, góc trên bên trái màn hình — dòng `Campus: ...`.
- Đổi cơ sở tại logo thì bạn ở nguyên trang đang mở; dữ liệu trang sẽ làm mới theo cơ sở mới.
- Nhập nhầm cơ sở là lỗi khó sửa vì dữ liệu đã gắn vào cơ sở đó. Luôn nhìn dòng `Campus:` trước khi tạo mới bất cứ thứ gì.

## Bố cục màn hình

Màn hình chia ba phần:

- **Cột trái — thanh menu.** Toàn bộ chức năng, gom theo nhóm công việc.
- **Trên cùng.** Tên màn hình đang mở và đường dẫn quay lại.
- **Giữa.** Nội dung làm việc: thẻ thống kê, bộ lọc, bảng dữ liệu.

Các nhóm menu chính:

| Nhóm menu | Công việc |
| --- | --- |
| Overview | Màn hình tổng quan |
| Academic Operations | Học vụ: chương trình, kỳ học, lớp môn, điểm danh, điểm, giảng viên |
| Students | Hồ sơ sinh viên, nhập học, quyết định |
| Reports & Audits | Báo cáo và đối chiếu số liệu |
| Finance | Học phí: sinh phí, thu tiền, đối soát, học bổng, gói học phí, voucher |
| Store & Clubs | Cửa hàng đổi Gold, câu lạc bộ |
| Campus | Phòng học, đặt phòng, sự kiện |
| Forms & Surveys | Biểu mẫu, khảo sát |
| Communications | Email và thông báo |
| Administration | Người dùng, phân quyền, cấu hình hệ thống |

**Lưu ý quan trọng.** Menu hiển thị theo quyền của bạn. Đồng nghiệp có thể thấy nhiều hoặc ít mục hơn bạn. Không thấy một mục nghĩa là chưa được cấp quyền, không phải hệ thống lỗi.

## Màn hình tổng quan (Dashboard)

**Dùng để làm gì.** Nắm nhanh tình hình cơ sở đang chọn.

**Ai vào được.** Mọi người dùng đã đăng nhập.

**Nội dung hiển thị**

- Số liệu tổng: sinh viên theo trạng thái, giảng viên, số chương trình đào tạo, số phòng học đang trống / đang dùng / đang bảo trì.
- Kỳ học hiện tại: mã kỳ, ngày bắt đầu và kết thúc, thời gian đăng ký môn, và trạng thái đăng ký còn mở hay đã đóng.
- Cảnh báo cần xử lý: sinh viên bị khóa học vụ, vấn đề điểm danh, thay đổi chương trình học.
- Biểu đồ phân bố sinh viên theo chương trình đào tạo.

**Lưu ý.** Cảnh báo ở đây chỉ là đầu mối. Xử lý chi tiết nằm ở khu vực học vụ và khu vực sinh viên.

## Tài khoản cá nhân

**Các bước**

1. Bấm vào tên bạn ở **góc dưới cùng cột menu bên trái**.
2. Chọn mục cần dùng: xem hồ sơ, cài đặt, hoặc đăng xuất.

**Lưu ý**

- Không có chức năng đổi mật khẩu trong Swinx. Mật khẩu do tài khoản Google quản lý; đổi tại trang tài khoản Google của bạn.
- Xong việc trên máy dùng chung thì phải **đăng xuất**, không chỉ đóng trình duyệt.

## Thao tác lặp lại ở hầu hết màn hình

Các màn hình danh sách trong Swinx dùng chung một số thao tác:

- **Ô tìm kiếm** — gõ tên hoặc mã để lọc nhanh.
- **Bộ lọc (Filters)** — thu hẹp danh sách theo kỳ học, trạng thái, chương trình đào tạo…
- **Xóa bộ lọc (Clear filters)** — đưa danh sách về ban đầu khi không thấy dữ liệu như mong đợi.
- **Thẻ thống kê phía trên bảng** — con số tổng hợp, thay đổi theo bộ lọc.
- **Biểu tượng cuối mỗi dòng** — xem, sửa, xóa bản ghi đó.

**Lưu ý.** Danh sách trống thường do bộ lọc còn sót, không phải do mất dữ liệu. Bấm **Xóa bộ lọc** trước khi báo lỗi.
