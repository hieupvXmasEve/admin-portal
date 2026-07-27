---
title: Student Services
description: Hồ sơ sinh viên, ghi danh, tạm khóa học vụ và hồ sơ nhập học.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Students/Index.vue
  - resources/js/pages/Students/enrollments/Index.vue
  - resources/js/pages/StudentApplications/Index.vue
---

**Student Services** là nơi làm việc với **từng sinh viên**: hồ sơ, tình trạng ghi danh, và hồ sơ ứng tuyển đầu vào.

Phân biệt với nhóm **Reports & Audits**: bên đó là số liệu tổng hợp toàn trường, bên này là từng người cụ thể.

## Students — Danh sách sinh viên

**Dùng để làm gì.** Tra cứu, thêm, sửa hồ sơ sinh viên. Đây là cửa vào hồ sơ chi tiết của một sinh viên.

**Ai vào được.** Người có quyền xem sinh viên.

**Các bước**

1. Vào **Student Services → Students**. Màn hình tên **Students Management**.
2. Tìm sinh viên bằng ô tìm kiếm hoặc bộ lọc.
3. Bấm vào dòng sinh viên để mở hồ sơ chi tiết.
4. Bấm **Đặt lại** để xóa toàn bộ điều kiện lọc.

**Xuất danh sách**

1. Chọn định dạng ở ô **Select format**.
2. Chọn phạm vi ở ô **Select scope** — chỉ các dòng đang lọc, hay toàn bộ.
3. Xác nhận để tải tệp về.

**Lưu ý**

- Hồ sơ sinh viên là nơi tra cứu chéo mọi thứ: điểm, điểm danh, GPA, học phí, quyết định.
- Danh sách lọc theo cơ sở đang chọn. Không thấy sinh viên thì kiểm tra dòng `Campus:`.
- Phạm vi xuất mặc định theo bộ lọc hiện tại. Xuất ra thiếu người thường là do quên xóa bộ lọc.

**Đi tiếp.** Course Registration, Warning Center, Finance Office.

## Enrollments & Holds — Ghi danh và tạm khóa

**Dùng để làm gì.** Quản lý tình trạng ghi danh của sinh viên theo kỳ, và các trường hợp bị tạm khóa học vụ.

**Ai vào được.** Người có quyền xem sinh viên.

**Các bước**

1. Vào **Student Services → Enrollments & Holds**. Màn hình tên **Student Enrollments & Holds Management**.
2. Xem bảng **Student Enrollments** để biết ai đang ghi danh kỳ nào.
3. Lọc để tìm nhóm cần xử lý.
4. Mở từng dòng để xem hoặc thay đổi tình trạng.

**Lưu ý**

- Sinh viên bị tạm khóa thường không đăng ký môn được. Khi có phản ánh "không đăng ký được", kiểm tra màn hình này trước khi kiểm tra thời gian mở đăng ký.
- Tạm khóa hay gặp do nợ học phí. Đối chiếu với khu vực **Finance Office** trước khi gỡ khóa.
- Cảnh báo tạm khóa cũng hiện trên màn hình tổng quan (Dashboard).

**Đi tiếp.** Finance Office, Course Registration.

## Student Applications — Hồ sơ nhập học

**Dùng để làm gì.** Xử lý hồ sơ ứng tuyển: xem giấy tờ, duyệt hoặc từ chối.

**Ai vào được.** Người có quyền xem hồ sơ nhập học.

**Các bước**

1. Vào **Student Services → Student Applications**.
2. Tìm hồ sơ bằng ô **Search name, email, code…**, hoặc lọc theo trạng thái (**Status**) và đợt tuyển sinh (**Intake**).
3. Bấm vào tên tệp trong hồ sơ để mở giấy tờ đính kèm.
4. Duyệt hồ sơ, hoặc từ chối.

**Khi từ chối**

1. Nhập lý do vào ô **Explain the reason for rejection**.
2. Bấm **Confirm rejection** để xác nhận.

**Lưu ý**

- Lý do từ chối được lưu lại và có thể gửi tới người nộp hồ sơ. Viết rõ ràng, đủ để người đọc hiểu cần bổ sung gì.
- Kiểm tra kỹ giấy tờ đính kèm trước khi duyệt. Duyệt rồi thì hồ sơ đi tiếp vào quy trình nhập học.
- Lọc theo **Intake** để không lẫn hồ sơ giữa các đợt tuyển sinh.

**Đi tiếp.** Students.

## Việc thường gặp

| Tình huống | Thứ tự làm |
| --- | --- |
| Sinh viên báo không đăng ký được môn | Enrollments & Holds → Finance Office → Academic Terms |
| Cần xem toàn bộ tình hình một sinh viên | Students → mở hồ sơ chi tiết |
| Duyệt hồ sơ đợt tuyển sinh mới | Student Applications (lọc theo Intake) |
| Xuất danh sách sinh viên cho báo cáo | Students → chọn định dạng và phạm vi |
