---
title: Faculty
description: Hồ sơ giảng viên, giờ giảng và kết quả lớp do giảng viên phụ trách.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Lectures/Index.vue
  - resources/js/pages/Lectures/TeachingHours.vue
  - resources/js/pages/Lectures/LecturerGpa.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Faculty** (trong **Academic Operations**) quản lý đội ngũ giảng dạy: ai đang dạy, dạy bao nhiêu giờ, lớp do họ phụ trách có kết quả thế nào.

## Lecturer List — Danh sách giảng viên

**Dùng để làm gì.** Tra cứu và quản lý hồ sơ giảng viên.

**Ai vào được.** Người có quyền xem giảng viên.

**Các bước**

1. Vào **Academic Operations → Faculty → Lecturer List**. Màn hình tên **Lecturers**.
2. Lọc theo kỳ (**All Semesters**), chương trình (**All Programs**), trạng thái (**All Statuses**) hoặc loại hợp đồng (**All Types**).
3. Bấm vào dòng để mở hồ sơ giảng viên.

**Lưu ý**

- Loại hợp đồng (cơ hữu, thỉnh giảng) ảnh hưởng cách tính giờ giảng ở màn hình kế tiếp.
- Giảng viên phải có trong danh sách này trước khi phân công dạy lớp môn.

**Đi tiếp.** Lecturer Hours, Course Offering List.

## Lecturer Hours — Giờ giảng

**Dùng để làm gì.** Thống kê số giờ giảng của từng giảng viên trong một kỳ hoặc một khoảng thời gian. Màn hình tên **Lecturer Teaching Hours Report**.

**Ai vào được.** Người có quyền xem giảng viên.

**Các bước**

1. Vào **Academic Operations → Faculty → Lecturer Hours**.
2. Chọn kỳ ở ô **Select semester**, hoặc nhập khoảng thời gian ở **From date** và **To date**.
3. Xem bảng kết quả, mở từng giảng viên để xem chi tiết theo lớp.

**Lưu ý**

- Số giờ tính từ lịch học đã xếp. Buổi học chưa có trong **Class Schedule** thì không được tính.
- Đây là số liệu thường dùng để tính thù lao. Đối chiếu với lịch dạy thực tế trước khi chốt.
- Đổi lịch buổi học sau khi đã chốt giờ sẽ làm lệch số liệu kỳ đó.

**Đi tiếp.** Class Schedule.

## Lecturer GPA — Kết quả lớp theo giảng viên

**Dùng để làm gì.** Xem điểm trung bình của các lớp do từng giảng viên phụ trách.

**Ai vào được.** Người có quyền xem giảng viên **và** quyền xem kết quả khảo sát tổng hợp.

**Các bước**

1. Vào **Academic Operations → Faculty → Lecturer GPA**.
2. Chọn kỳ ở ô **Select semester**.
3. Xem kết quả theo từng giảng viên.

**Lưu ý**

- Số liệu chỉ có nghĩa sau khi đã chốt GPA của kỳ.
- Điểm lớp thấp không đồng nghĩa giảng viên dạy kém: môn khó, lớp yếu đầu vào, sĩ số lớn đều ảnh hưởng. Đọc cùng **Course Ranking** và **Course Statistics** trước khi kết luận.
- Đây là dữ liệu nhạy cảm về con người. Chỉ dùng trong phạm vi được phép.

**Đi tiếp.** Course Ranking, Survey Results.

## Việc thường gặp

| Tình huống | Thứ tự làm |
| --- | --- |
| Tính thù lao giảng dạy cuối kỳ | Class Schedule → Lecturer Hours |
| Chuẩn bị phân công dạy kỳ mới | Lecturer List → Course Offering List |
| Đánh giá chất lượng giảng dạy | Lecturer GPA → Course Ranking → Survey Results |
