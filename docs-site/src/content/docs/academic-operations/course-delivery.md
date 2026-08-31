---
title: Course Delivery
description: Mở lớp, xếp lịch, đăng ký môn, học lại, thi lại, thống kê môn học và liên kết Canvas.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/CourseOfferings/Index.vue
  - resources/js/pages/ClassSchedule/Index.vue
  - resources/js/pages/CourseRegistrations/Index.vue
  - resources/js/pages/Academic/RetakeCourse/Index.vue
  - resources/js/pages/Academic/ExamResit/Index.vue
  - resources/js/pages/Academic/ExamResit/Schedule/Index.vue
  - resources/js/pages/CourseStatistics/Index.vue
  - resources/js/pages/Admin/Canvas/Courses/Index.vue

<!-- docs-freshness: reviewed against Retake/ExamResit pages (retake pricing from Finance catalog, paid cancel with balance release, resit no-show + two paid-cancel outcomes, plans/260831-1213-retake-resit-business-rule-alignment/). -->

**Course Delivery** là phần việc chính đầu mỗi kỳ: mở lớp, xếp lịch, đăng ký sinh viên, xử lý học lại và thi lại.

## Course Offering List — Danh sách lớp môn

**Dùng để làm gì.** Mở lớp môn cho một kỳ cụ thể: môn nào, kỳ nào, hình thức học nào, do ai dạy.

**Ai vào được.** Người có quyền xem lớp môn.

**Các bước**

1. Vào **Academic Operations → Course Delivery → Course Offering List**.
2. Bấm **Create Course Offering** (Tạo lớp môn).
3. Chọn môn học, kỳ học, hình thức và các thông tin còn lại rồi lưu.
4. Tìm lớp đã có: gõ vào ô **Search courses...**, hoặc dùng bộ lọc trong khung **Filters** — theo học phần (**All Modules**), bậc (**All Levels**), loại (**All Types**), trạng thái (**All Statuses**), hình thức học (**All Modes**).

**Nội dung màn hình.** Hai thẻ đầu trang: **Total Offerings** (tổng số lớp môn) và **Active Offerings** (số lớp đang hoạt động).

**Lưu ý**

- Lớp môn phải được tạo **trước khi** mở đăng ký cho sinh viên.
- Danh sách mặc định lọc theo cơ sở đang chọn. Không thấy lớp mong đợi thì kiểm tra dòng `Campus:` ở góc trên bên trái.

**Đi tiếp.** Course Statistics, Class Schedule, Course Registration, Attendance Summary.

## Course Statistics — Thống kê môn học

**Dùng để làm gì.** Xem tình hình từng môn trong một kỳ: sĩ số, tỉ lệ chuyên cần, kết quả chung.

**Ai vào được.** Người có quyền xem điểm danh.

**Các bước**

1. Vào **Academic Operations → Course Delivery → Course Statistics**.
2. Chọn kỳ ở ô **Select semester**.
3. Tìm môn cần xem bằng ô **Unit code or name...** (mã hoặc tên môn).

**Lưu ý.** Phải chọn kỳ trước, nếu không màn hình sẽ không có dữ liệu.

**Đi tiếp.** Failed Students (nhóm **Attendance**).

## Class Schedule — Lịch học

**Dùng để làm gì.** Xếp buổi học cho các lớp môn: ngày, giờ, phòng.

**Ai vào được.** Người có quyền xem lớp môn.

**Các bước**

1. Vào **Academic Operations → Course Delivery → Class Schedule**.
2. Dùng khung lọc bên cạnh để chọn kỳ học, lớp môn hoặc khoảng thời gian cần xem.
3. Xem lịch ở dạng lịch hoặc dạng lưới.
4. Bấm vào một buổi học để mở khung chỉnh sửa bên phải, sửa xong thì lưu.

**Lưu ý**

- Kiểm tra trùng phòng và trùng giờ giảng viên trước khi lưu.
- Đổi lịch buổi học đã diễn ra sẽ làm lệch dữ liệu điểm danh.

**Đi tiếp.** Attendance Summary.

## Course Registration — Đăng ký môn học

**Dùng để làm gì.** Ghi nhận sinh viên nào học lớp môn nào trong kỳ.

**Ai vào được.** Người có quyền xem đăng ký môn học.

**Các bước**

1. Vào **Academic Operations → Course Delivery → Course Registration**.
2. Dùng khung **Filters** để lọc theo kỳ, lớp môn hoặc sinh viên.
3. Bấm **View** để xem chi tiết, **Edit** để sửa, **Delete** để hủy đăng ký.
4. Khi danh sách còn trống, bấm **Register First Student** (Đăng ký sinh viên đầu tiên) để thêm.

**Nội dung màn hình.** Ba thẻ đầu trang: **Total Registrations** (tổng lượt đăng ký), **Active Registrations** (đang hiệu lực), **Pending Registrations** (chờ xử lý).

**Lưu ý**

- Hủy đăng ký ảnh hưởng tới học phí của sinh viên. Xử lý phần tiền ở khu vực **Finance Office**.
- Số ở thẻ **Pending** là việc còn tồn đọng, nên xử lý dứt điểm trước khi kỳ học bắt đầu.

**Đi tiếp.** Student List, Attendance Summary.

## Retake Registration — Đăng ký học lại

**Dùng để làm gì.** Ghi nhận sinh viên đăng ký học lại môn đã rớt.

**Ai vào được.** Người có quyền xem đăng ký học lại.

**Các bước**

1. Vào **Academic Operations → Course Delivery → Retake Registration** (Đăng ký học lại).
2. Lọc để tìm sinh viên hoặc môn cần xử lý.
3. Ghi nhận đăng ký học lại. Hệ thống tự tính học phí học lại theo bảng giá hiện hành; nếu môn chưa có giá trong bảng giá, hệ thống sẽ chặn và yêu cầu cấu hình trước.
4. Bấm **Xóa bộ lọc** nếu danh sách không hiện như mong đợi.

**Lưu ý**

- Danh sách sinh viên đủ điều kiện học lại lấy từ màn hình **Failed Students**.
- Học phí học lại luôn theo bảng giá hiện hành của hệ thống, không theo số tiền cũ ghi trên môn học.
- Đơn đã thanh toán mà **chưa gắn lớp** vẫn có thể hủy: tiền đã thu chuyển thành dư nợ dùng cho các khoản phí phát sinh sau của sinh viên (không hoàn về tài khoản). Đơn đã gắn lớp không hủy được.

**Đi tiếp.** Failed Students, Finance Office.

## Thi lại

**Dùng để làm gì.** Lập danh sách sinh viên thi lại, theo dõi kết quả và ghi nhận sinh viên vắng thi.

**Ai vào được.** Người có quyền xem thi lại.

**Các bước**

1. Vào **Academic Operations → Course Delivery → Thi lại**.
2. Lọc theo kỳ học, môn hoặc sinh viên.
3. Tạo đợt thi lại và chọn sinh viên tham gia. Mỗi bản ghi điểm chỉ được thi lại theo số lần quy định của môn học (mặc định 1 lần); tăng số lần trong syllabus sẽ mở lại lượt mà không cần chỉnh gì khác.
4. Sau khi thi xong, cập nhật kết quả để hoàn tất đợt. Nếu sinh viên có lịch thi nhưng không đến, bấm **Vắng thi** và xác nhận — lượt thi bị tiêu, điểm gốc giữ nguyên.

**Lưu ý**

- **Vắng thi** khác **Hủy thi**: vắng thi là đã có lịch thi mà không dự thi, phí bị mất; hủy là chưa thi, xử lý phí theo lựa chọn bên dưới.
- Khi hủy một đơn thi lại **đã thanh toán**, chọn một trong hai hệ quả: **mất phí** (phí đã thu giữ làm doanh thu) hoặc **lưu phí dùng sau** (phí chuyển thành dư nợ cho các khoản phí sau của sinh viên). Không có hoàn tiền về tài khoản trong cả hai trường hợp.

**Đi tiếp.** Lịch thi lại.

## Lịch thi lại

**Dùng để làm gì.** Xếp ca thi, phòng thi và phân công cán bộ coi thi cho đợt thi lại.

**Ai vào được.** Người có quyền quản lý lịch thi lại.

**Các bước**

1. Vào **Academic Operations → Course Delivery → Lịch thi lại**.
2. Bấm **Tạo ca phòng thi** để mở khung **Ca phòng thi mới**.
3. Nhập thời gian, phòng thi rồi bấm **Tạo ca**.
4. Bấm **Thêm ca thi** nếu cần thêm ca cho cùng đợt.
5. Bấm **Phân công coi thi** để chọn cán bộ coi thi cho từng ca.

**Lưu ý.** Đặt phòng thi trước ở khu vực **Campus** để tránh trùng lịch với hoạt động khác.

## Canvas Courses — Liên kết Canvas

**Dùng để làm gì.** Ghép lớp môn trong Swinx với khóa học tương ứng trên hệ thống học trực tuyến Canvas.

**Ai vào được.** Người có quyền xem tích hợp Canvas.

**Các bước**

1. Vào **Academic Operations → Course Delivery → Canvas Courses**.
2. Xem khung **Integration Status** (Trạng thái kết nối) để chắc chắn kết nối đang hoạt động.
3. Chọn lớp môn cần ghép rồi bấm **Map Course** (Ghép khóa học).
4. Ghép nhiều lớp cùng lúc bằng **Select All** (Chọn tất cả) và **Deselect All** (Bỏ chọn tất cả).
5. Nếu kết nối có vấn đề, bấm **Manage Integrations** hoặc **Go to Integrations** để mở **Canvas Settings**.

**Lưu ý.** Ghép sai lớp sẽ khiến sinh viên vào nhầm lớp học trực tuyến. Kiểm tra mã lớp và kỳ học trước khi xác nhận.

**Đi tiếp.** Canvas Settings.

## Canvas Settings — Cấu hình kết nối Canvas

**Dùng để làm gì.** Cấu hình kết nối tới hệ thống Canvas (khóa API, endpoint). Trước đây nằm ở nhóm Administration; nay chuyển về cạnh Canvas Courses vì cùng dùng chung một quyền (`view_canvas_integration`) và cùng phục vụ đội học vụ, không phải đội quản trị hệ thống.

**Ai vào được.** Người có quyền xem tích hợp Canvas.

**Các bước.** Vào **Academic Operations → Course Delivery → Canvas Settings**.

**Đi tiếp.** Canvas Courses.

## Luồng đề xuất

```text
Course Offering List
  -> Class Schedule
  -> Course Registration
  -> Canvas Courses
  -> Attendance Summary
```

Nếu lớp có sinh viên rớt môn:

```text
Course Statistics
  -> Failed Students
  -> Retake Registration
  -> Finance Office nếu cần xử lý học phí học lại
```
