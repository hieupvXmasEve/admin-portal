---
title: Attendance & Completion
description: Theo dõi điểm danh, thống kê môn học và danh sách sinh viên rớt môn.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/CourseStatistics/Index.vue
  - resources/js/pages/Attendance/Index.vue
  - resources/js/pages/FailedStudents/Index.vue
---

**Attendance & Completion** giúp kiểm tra tình hình đi học, kết quả hoàn tất lớp và các trường hợp cần xử lý tiếp.

## Course Statistics — Thống kê môn học

**Dùng để làm gì.** Xem tình hình từng môn trong một kỳ: sĩ số, tỉ lệ chuyên cần, kết quả chung.

**Ai vào được.** Người có quyền xem điểm danh.

**Các bước**

1. Vào **Academic Operations → Attendance & Completion → Course Statistics**.
2. Chọn kỳ ở ô **Select semester**.
3. Tìm môn cần xem bằng ô **Unit code or name...** (mã hoặc tên môn).

**Lưu ý.** Phải chọn kỳ trước, nếu không màn hình sẽ không có dữ liệu.

**Đi tiếp.** Failed Students.

## Attendance Summary — Tổng hợp điểm danh

**Dùng để làm gì.** Tra cứu bản ghi điểm danh của từng sinh viên theo từng buổi.

**Ai vào được.** Người có quyền xem điểm danh.

**Các bước**

1. Vào **Academic Operations → Attendance & Completion → Attendance Summary**.
2. Gõ vào ô **Search students or sessions...** để tìm theo sinh viên hoặc buổi học.
3. Lọc thêm theo trạng thái (**All Statuses**) hoặc cách điểm danh (**All Methods**).

**Lưu ý**

- Đây là màn hình tra cứu và đối chiếu. Việc điểm danh hằng ngày do giảng viên thực hiện trên cổng riêng.
- Khi sinh viên khiếu nại vắng học, tra ở đây trước khi ra quyết định.

**Đi tiếp.** Warning Center, Failed Students.

## Failed Students — Sinh viên rớt môn

**Dùng để làm gì.** Lọc ra sinh viên không đạt môn và nắm lý do rớt.

**Ai vào được.** Người có quyền xem điểm danh.

**Các bước**

1. Vào **Academic Operations → Attendance & Completion → Failed Students**.
2. Dùng khung **Filters** để lọc theo kỳ, chương trình hoặc môn học.
3. Đối chiếu danh sách rồi chuyển sang **Retake Registration** để mở học lại.
4. Bấm **Clear Filters** để xem lại toàn bộ danh sách.

**Nội dung màn hình**

- Bốn thẻ đầu trang: **Total Failed Students** (số sinh viên rớt), **Total Failed Courses** (số lượt rớt môn), **Avg Attendance** (tỉ lệ chuyên cần trung bình), **Retake Eligible** (số sinh viên đủ điều kiện học lại).
- **Fail Reason Distribution** — tỉ lệ theo lý do rớt.
- **Top 10 Failed Units** — 10 môn có nhiều sinh viên rớt nhất.

**Lưu ý**

- Chỉ sinh viên thuộc nhóm **Retake Eligible** mới nên mở đăng ký học lại. Các trường hợp còn lại cần xử lý riêng theo quy chế.
- **Top 10 Failed Units** là đầu mối rà soát chất lượng giảng dạy, nên xem lại vào cuối mỗi kỳ.

**Đi tiếp.** Retake Registration, Finance Office.

## Câu hỏi thường gặp

| Câu hỏi | Trang nên dùng |
| --- | --- |
| Lớp này có bao nhiêu sinh viên hoàn tất? | Course Statistics |
| Ai đang vắng học nhiều? | Attendance Summary |
| Sinh viên nào cần học lại? | Failed Students |
| Có cần gửi cảnh báo cho sinh viên không? | Warning Center |

## Lưu ý vận hành

- **Attendance Summary** hợp để theo dõi sớm trong lúc lớp đang diễn ra.
- **Failed Students** hợp khi đã có dữ liệu kết quả cuối kỳ.
- **Course Statistics** là điểm bắt đầu tốt khi cần soi một môn cụ thể.
