---
title: Curriculum Setup
description: Dựng khung đào tạo — kỳ học, chương trình, phiên bản chương trình, môn học, học phần, mẫu đề cương.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Semesters/Index.vue
  - resources/js/pages/Programs/Index.vue
  - resources/js/pages/CurriculumVersions/Index.vue
  - resources/js/pages/Units/Index.vue
  - resources/js/pages/Admin/Modules/Index.vue
  - resources/js/pages/Syllabus/TemplatesIndex.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Curriculum Setup** là nhóm trang chuẩn bị dữ liệu nền. Làm **một lần rồi dùng lại nhiều kỳ**, không phải việc hằng ngày.

Nếu dữ liệu ở nhóm này sai hoặc thiếu, các bước mở lớp, đăng ký môn, tính GPA và báo cáo học vụ đều bị ảnh hưởng.

## Academic Terms — Kỳ học

**Dùng để làm gì.** Khai báo các kỳ học: mã kỳ, ngày bắt đầu và kết thúc, thời gian mở đăng ký môn. Mọi hoạt động khác đều gắn vào một kỳ.

**Ai vào được.** Người có quyền xem kỳ học — thường là phòng đào tạo.

**Các bước tạo kỳ mới**

1. Vào **Academic Operations → Curriculum Setup → Academic Terms**.
2. Bấm **Add New Semester** (Thêm kỳ học).
3. Điền mã kỳ, tên kỳ, ngày bắt đầu, ngày kết thúc, thời gian mở và đóng đăng ký môn.
4. Lưu lại.

**Các bước khai báo lịch riêng cho cơ sở**

1. Mở kỳ học vừa tạo.
2. Ở phần **Campus schedules** (Lịch theo cơ sở), bấm **Add campus schedule**.
3. Chọn cơ sở và nhập mốc thời gian riêng của cơ sở đó.

**Lưu ý**

- Kỳ học dùng chung cho toàn trường; lịch từng cơ sở có thể lệch nhau, khai ở **Campus schedules**.
- Thời gian mở đăng ký quyết định sinh viên đăng ký môn được hay không. Sai mốc này là nguyên nhân phổ biến nhất của việc "sinh viên không đăng ký được".
- Sửa ngày của kỳ đang chạy ảnh hưởng dây chuyền tới đăng ký môn và học phí. Cân nhắc trước khi sửa.

**Đi tiếp.** Course Offering List, GPA Management.

## Programs — Chương trình đào tạo

**Dùng để làm gì.** Khai báo các ngành, chương trình nhà trường đang đào tạo.

**Ai vào được.** Người có quyền xem chương trình đào tạo.

**Các bước**

1. Vào **Academic Operations → Curriculum Setup → Programs**.
2. Bấm **Add Program** (Thêm chương trình) để tạo mới.
3. Điền thông tin chương trình rồi lưu.
4. Với chương trình đã có, dùng biểu tượng cuối dòng: **View program** (xem), **Edit program** (sửa), **Delete program** (xóa).

**Lưu ý.** Không xóa chương trình đã có sinh viên theo học. Nếu ngừng tuyển sinh, hãy ngừng dùng chứ đừng xóa.

**Đi tiếp.** Curriculum Versions, Units.

## Curriculum Versions — Phiên bản chương trình

**Dùng để làm gì.** Mỗi khóa tuyển sinh có thể học khung chương trình khác nhau. Mỗi khung như vậy là một phiên bản.

**Ai vào được.** Người có quyền xem phiên bản chương trình.

**Các bước**

1. Vào **Academic Operations → Curriculum Setup → Curriculum Versions**.
2. Bấm **Add Curriculum Version** để tạo phiên bản mới.
3. Nếu chỉ chỉnh sửa nhẹ so với phiên bản cũ, dùng biểu tượng **Duplicate curriculum version** (Nhân bản) rồi sửa trên bản sao.

**Nội dung màn hình.** Ba thẻ đầu trang: tổng số phiên bản, số phiên bản **đang áp dụng** (Active), số **đã ngừng** (Inactive).

**Lưu ý.** Sửa trực tiếp phiên bản đang áp dụng sẽ ảnh hưởng sinh viên đang học. Cách an toàn: nhân bản, sửa trên bản mới, rồi mới chuyển sang áp dụng.

**Đi tiếp.** Units, Course Registration.

## Units — Môn học

**Dùng để làm gì.** Danh mục môn học: mã môn, tên môn, số tín chỉ, môn học trước (điều kiện tiên quyết) và môn tương đương.

**Ai vào được.** Người có quyền xem môn học.

**Các bước**

1. Vào **Academic Operations → Curriculum Setup → Units**.
2. Bấm **Add Unit** để thêm môn.
3. Khai báo môn tiên quyết và môn tương đương nếu có.
4. Dùng biểu tượng cuối dòng để xem, sửa, xóa từng môn.

**Nội dung màn hình.** Ba thẻ đầu trang: tổng số môn, số môn **có điều kiện tiên quyết**, số môn **có môn tương đương**.

**Lưu ý**

- Khai báo môn tiên quyết ảnh hưởng trực tiếp tới việc sinh viên được phép đăng ký môn hay không.
- Môn tương đương dùng khi sinh viên chuyển chương trình, hoặc học lại bằng một môn khác đã được công nhận.
- Màn hình có chức năng **xóa hàng loạt**. Kiểm tra kỹ danh sách đã chọn trước khi xác nhận — thao tác này không hoàn tác được.

**Đi tiếp.** Syllabus Templates, Course Offering List.

## Modules — Học phần

**Dùng để làm gì.** Nhóm các môn học thành khối kiến thức trong chương trình.

**Ai vào được.** Người có quyền xem học phần.

**Các bước.** Vào **Academic Operations → Curriculum Setup → Modules** để xem và quản lý danh sách học phần.

**Đi tiếp.** Programs, Units.

## Syllabus Templates — Mẫu đề cương

**Dùng để làm gì.** Tạo sẵn mẫu đề cương môn học để dùng lại, khỏi soạn từ đầu mỗi kỳ.

**Ai vào được.** Người có quyền xem đề cương.

**Các bước**

1. Vào **Academic Operations → Curriculum Setup → Syllabus Templates**.
2. Bấm **New Template** (Mẫu mới).
3. Soạn nội dung mẫu rồi lưu.
4. Khi danh sách không hiện đúng, bấm **Clear filters** (Xóa bộ lọc).

**Đi tiếp.** Course Offering List, Course Statistics.

## Kiểm tra trước khi mở lớp

- Kỳ học đã tồn tại và đúng kỳ cần mở.
- Chương trình đào tạo và phiên bản chương trình đúng với khóa tuyển sinh.
- Môn học đã có đủ thông tin: tín chỉ, môn tiên quyết.
- Mẫu đề cương đã sẵn sàng cho môn cần mở.
- Nếu có sinh viên học lại, kiểm tra phần học phí học lại ở khu vực Finance.
