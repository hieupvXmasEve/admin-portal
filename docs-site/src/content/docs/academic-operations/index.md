---
title: Academic Operations
description: Tổng quan khu vực học vụ — các nhóm trang, ai dùng, và bắt đầu từ đâu.
source:
  - resources/js/constants/menu-sidebar.ts
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Academic Operations** là khu vực vận hành học vụ: dựng khung chương trình, mở lớp, quản lý đăng ký môn, theo dõi điểm danh, chốt GPA và phát hiện sinh viên có rủi ro học tập.

Sáu nhóm trang; bốn nhóm đầu xếp theo đúng trình tự một kỳ học:

1. **Curriculum Setup** — dựng khung: kỳ học, chương trình đào tạo, môn học.
2. **Course Delivery** — mở lớp, xếp lịch, đăng ký môn, học lại, thi lại, thống kê môn học, liên kết Canvas.
3. **Attendance** — theo dõi điểm danh, lọc sinh viên rớt môn.
4. **Grades & Performance** — chốt điểm, GPA, cảnh báo học vụ, điều chỉnh học bổng do trượt môn.

Một nhóm còn lại đứng độc lập với trình tự kỳ học:

- **Faculty** — hồ sơ giảng viên, giờ giảng, kết quả lớp theo giảng viên.

Làm sai thứ tự bốn nhóm trên sẽ bị kẹt: chưa có kỳ học thì không mở được lớp môn, chưa có lớp môn thì không đăng ký được sinh viên.

## Ai dùng khu vực này

| Vai trò | Dùng để làm gì |
| --- | --- |
| Phòng đào tạo | Khai kỳ học, chương trình, môn học, mẫu đề cương |
| Cán bộ học vụ | Mở lớp, theo dõi đăng ký, điểm danh, sinh viên rớt môn, học lại |
| Trưởng phòng, giám đốc đào tạo | Chốt GPA, xem cảnh báo, đối chiếu kết quả toàn kỳ |
| Bộ phận hỗ trợ | Tra cứu nhanh tình trạng lớp hoặc kết quả khi sinh viên hỏi |

## Bắt đầu từ đâu

| Nhóm | Bắt đầu từ đây khi |
| --- | --- |
| [Curriculum Setup](/academic-operations/curriculum-setup/) | Chuẩn bị dữ liệu nền trước kỳ mới, hoặc cập nhật chương trình |
| [Course Delivery](/academic-operations/course-delivery/) | Mở lớp, xếp lịch, đăng ký sinh viên, xử lý học lại, thi lại, hoặc soi thống kê một môn |
| [Attendance](/academic-operations/attendance-completion/) | Kiểm tra điểm danh, sinh viên rớt môn |
| [Grades & Performance](/academic-operations/grades-performance/) | Chốt GPA, tra kết quả cũ, xem sinh viên bị cảnh báo, xét điều chỉnh học bổng |
| [Faculty](/academic-operations/faculty/) | Tra cứu hồ sơ, giờ giảng, hoặc kết quả lớp của giảng viên |

Chưa quen hệ thống thì xem [Bản đồ luồng trang](/academic-operations/flow-map/) để hiểu các trang nối với nhau thế nào.

## Toàn bộ trang trong khu vực

| Nhóm | Trang | Dùng để làm gì |
| --- | --- | --- |
| Curriculum Setup | Academic Terms | Khai báo kỳ học và mốc đăng ký môn |
| Curriculum Setup | Programs | Khai báo ngành, chương trình đào tạo |
| Curriculum Setup | Curriculum Versions | Khung chương trình theo từng khóa tuyển sinh |
| Curriculum Setup | Units | Danh mục môn học, môn tiên quyết, môn tương đương |
| Curriculum Setup | Modules | Nhóm môn học thành khối kiến thức |
| Curriculum Setup | Syllabus Templates | Mẫu đề cương dùng lại nhiều kỳ |
| Course Delivery | Course Offering List | Mở lớp môn cho một kỳ |
| Course Delivery | Class Schedule | Xếp ngày, giờ, phòng cho buổi học |
| Course Delivery | Course Registration | Ghi nhận sinh viên học lớp môn nào |
| Course Delivery | Retake Registration | Đăng ký học lại môn đã rớt |
| Course Delivery | Thi lại | Lập danh sách và theo dõi kết quả thi lại |
| Course Delivery | Lịch thi lại | Xếp ca thi, phòng thi, phân công coi thi |
| Course Delivery | Canvas Courses | Ghép lớp môn với lớp học trực tuyến |
| Course Delivery | Course Statistics | Tình hình từng môn trong kỳ |
| Course Delivery | Canvas Settings | Cấu hình kết nối tới hệ thống Canvas |
| Attendance | Attendance Summary | Tra cứu điểm danh theo sinh viên, theo buổi |
| Attendance | Failed Students | Sinh viên rớt môn và lý do rớt |
| Grades & Performance | GPA Management | Chốt GPA theo kỳ |
| Grades & Performance | GPA History | Tra GPA đã chốt các kỳ trước |
| Grades & Performance | Warning Center | Sinh viên bị cảnh báo học tập hoặc vắng học |
| Grades & Performance | Scholarship Adjustments | Xét giảm học bổng cho sinh viên trượt môn |
| Faculty | Lecturer List | Danh sách và hồ sơ giảng viên |
| Faculty | Lecturer Hours | Giờ giảng theo kỳ |
| Faculty | Lecturer GPA | Điểm trung bình lớp theo giảng viên phụ trách |

Các báo cáo tổng hợp toàn trường — Performance Dashboard, Academic Report, Course Ranking — nằm ở nhóm menu riêng **Reports & Audits**.

## Luồng phổ biến

| Luồng | Thứ tự trang |
| --- | --- |
| Chuẩn bị kỳ học mới | Academic Terms → Programs → Curriculum Versions → Units → Syllabus Templates |
| Mở và vận hành lớp | Course Offering List → Class Schedule → Course Registration → Canvas Courses |
| Theo dõi rủi ro học tập | Attendance Summary → Failed Students → Warning Center → Retake Registration |
| Tổ chức thi lại | Thi lại → Lịch thi lại |
| Chốt kết quả cuối kỳ | Course Statistics → GPA Management → GPA History → Warning Center |
