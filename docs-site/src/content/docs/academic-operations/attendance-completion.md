---
title: Attendance
description: Theo dõi điểm danh và danh sách sinh viên rớt môn.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Attendance/Index.vue
  - resources/js/pages/FailedStudents/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Attendance** giúp kiểm tra tình hình đi học và các trường hợp cần xử lý tiếp. Thống kê từng môn (**Course Statistics**) đã chuyển sang nhóm **Course Delivery**.

## Attendance Summary — Tổng hợp điểm danh

**Dùng để làm gì.** Tra cứu bản ghi điểm danh của từng sinh viên theo từng buổi.

**Ai vào được.** Người có quyền xem điểm danh.

**Các bước**

1. Vào **Academic Operations → Attendance → Attendance Summary**.
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

1. Vào **Academic Operations → Attendance → Failed Students**.
2. Dùng khung **Filters** để lọc theo kỳ, chương trình hoặc môn học.
3. Đối chiếu danh sách rồi chuyển sang **Retake Registration** hoặc **Thi lại**.
4. Bấm **Clear Filters** để xem lại toàn bộ danh sách.

**Nội dung màn hình**

- Bốn thẻ đầu trang: **Total Failed Students** (số sinh viên rớt), **Total Failed Courses** (số lượt rớt môn), **Avg Attendance** (tỉ lệ chuyên cần trung bình), **Retake Eligible** (số lượt rớt ở lần 1 hoặc lần 2 — không phải danh sách đăng ký học lại).
- **Fail Reason Distribution** — tỉ lệ theo lý do rớt.
- **Top 10 Failed Units** — 10 môn có nhiều sinh viên rớt nhất.

**Lưu ý**

- Thẻ **Retake Eligible** không phải điều kiện mở học lại. Mở học lại hoặc thi lại từ **Retake Registration** và **Thi lại**: hai danh sách cùng hiện bản ghi rớt đã chốt, trừ khi bản ghi đó đã có đăng ký ở luồng kia chưa xong.
- **Top 10 Failed Units** là đầu mối rà soát chất lượng giảng dạy, nên xem lại vào cuối mỗi kỳ.

**Đi tiếp.** Retake Registration, Thi lại, Finance Office.

## Câu hỏi thường gặp

| Câu hỏi | Trang nên dùng |
| --- | --- |
| Lớp này có bao nhiêu sinh viên hoàn tất? | Course Statistics (nhóm **Course Delivery**) |
| Ai đang vắng học nhiều? | Attendance Summary |
| Sinh viên nào cần học lại? | Failed Students |
| Có cần gửi cảnh báo cho sinh viên không? | Warning Center |

## Lưu ý vận hành

- **Attendance Summary** hợp để theo dõi sớm trong lúc lớp đang diễn ra.
- **Failed Students** hợp khi đã có dữ liệu kết quả cuối kỳ.
- Cần soi một môn cụ thể thì dùng **Course Statistics** ở nhóm **Course Delivery**.
