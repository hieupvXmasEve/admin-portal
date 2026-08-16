# Yêu cầu bài toán: Tự động mở lớp, đăng ký sinh viên và tạo lịch học

## 1. Mục tiêu

Xây dựng tính năng hỗ trợ tự động:

1. Phân tích nhu cầu học của sinh viên trong từng kỳ.
2. Kiểm tra và tận dụng các lớp đã tồn tại.
3. Đề xuất mở hoàn toàn lớp mới khi cần thiết.
4. Phân công giảng viên phù hợp.
5. Tự động đăng ký sinh viên vào lớp.
6. Tự động tạo lịch học và phòng học.
7. Phát hiện và xử lý các xung đột về sinh viên, giảng viên, phòng học và thời gian.
8. Cho phép người dùng kiểm tra, điều chỉnh và xác nhận trước khi công bố.

Hệ thống chỉ tạo các phương án đề xuất ban đầu. Người dùng có quyền duyệt, chỉnh sửa, xóa hoặc chạy lại từng bước.

---

# 2. Phạm vi sinh viên

## Input chính

* Danh sách sinh viên có trạng thái `intake_course`.
* Kỳ học hiện tại hoặc kỳ học cần lập kế hoạch.
* Chương trình đào tạo của sinh viên.
* Danh sách course sinh viên cần học trong kỳ.
* Các course sinh viên phải học lại.
* Lịch sử học tập và kết quả pass/fail.
* Campus hoặc địa điểm học.
* Các điều kiện tiên quyết của course.

## Phân loại nhu cầu

Hệ thống cần phân biệt:

* Sinh viên học đúng tiến độ.
* Sinh viên học lại.
* Sinh viên học bù.
* Sinh viên chuyển kỳ hoặc khác intake.
* Sinh viên chưa đủ điều kiện học course.
* Sinh viên đã pass course và không được đăng ký lại.

---

# 3. Quy trình tổng thể

```text
Phân tích nhu cầu học của sinh viên
→ Kiểm tra lớp hiện có
→ Tính toán nhu cầu mở lớp mới
→ Người dùng duyệt kế hoạch mở lớp
→ Tạo lớp
→ Đề xuất giảng viên, lịch và phòng
→ Kiểm tra xung đột
→ Người dùng duyệt phương án
→ Tự động đăng ký sinh viên
→ Kiểm tra lại lịch sinh viên
→ Xử lý ngoại lệ
→ Công bố lớp và thời khóa biểu
```

Mỗi bước phải có thể chạy độc lập và chạy lại mà không bắt buộc thực hiện lại toàn bộ quy trình.

---

# 4. Bước 1: Phân tích nhu cầu học

Đối với từng sinh viên, hệ thống xác định:

* Course cần học trong kỳ.
* Course phải học lại.
* Course chưa đủ điều kiện tiên quyết.
* Course đã hoàn thành.
* Course bị hoãn hoặc được miễn.
* Loại lớp cần tham gia:

  * Lý thuyết.
  * Thực hành.
  * Phòng lab.
  * Tutorial.
  * Workshop.

Kết quả là danh sách nhu cầu học theo:

* Course.
* Semester.
* Campus.
* Chương trình.
* Intake.
* Loại lớp.
* Nhóm sinh viên.

---

# 5. Bước 2: Kiểm tra lớp hiện có

Đối với từng course, hệ thống tìm các lớp đã tồn tại trong kỳ.

## Điều kiện kiểm tra

* Đúng course.
* Đúng kỳ học.
* Đúng campus.
* Đúng loại lớp.
* Đúng chương trình hoặc cho phép học ghép.
* Lớp đang ở trạng thái phù hợp.
* Lớp còn chỗ.
* Không vượt sĩ số tối đa.
* Sinh viên đáp ứng điều kiện tiên quyết.
* Lịch lớp không xung đột với các môn bắt buộc khác của sinh viên.

## Trường hợp học lại

Sinh viên học lại có thể được đề xuất vào:

* Lớp đang mở cho intake hiện tại.
* Lớp của intake cũ.
* Lớp học ghép.
* Lớp mới được mở riêng cho nhóm học lại.

Việc học ghép phải tuân theo rule về:

* Campus.
* Chương trình.
* Phiên bản course.
* Loại lớp.
* Sĩ số.
* Khung giờ.
* Điều kiện học thuật.

---

# 6. Bước 3: Đề xuất mở lớp mới

Nếu không có lớp phù hợp hoặc số lượng sinh viên vượt quá khả năng tiếp nhận của lớp hiện có, hệ thống phải đề xuất mở lớp mới hoàn toàn.

## Cách tính nhu cầu

Hệ thống gom nhóm sinh viên theo:

* Course.
* Semester.
* Campus.
* Chương trình.
* Intake.
* Loại lớp.
* Hình thức học.
* Các rule học ghép.

Sau đó tính:

* Tổng số sinh viên cần học.
* Số chỗ còn lại của lớp hiện có.
* Số sinh viên chưa được xếp lớp.
* Số lớp mới cần mở.
* Sĩ số dự kiến của từng lớp.

## Rule mở lớp

Cần cấu hình:

* Sĩ số tối thiểu để mở lớp.
* Sĩ số tối đa của lớp.
* Số lớp tối đa được mở cho một course.
* Có cho phép mở lớp dưới sĩ số tối thiểu hay không.
* Có cho phép ghép intake không.
* Có cho phép ghép chương trình không.
* Có cho phép ghép sinh viên học lại với sinh viên học lần đầu không.
* Có cho phép chia đều sinh viên giữa các lớp không.
* Có ưu tiên lấp đầy lớp hiện có trước không.

## Duyệt kế hoạch mở lớp

Hệ thống hiển thị danh sách lớp đề xuất, bao gồm:

* Course.
* Loại lớp.
* Campus.
* Số sinh viên cần học.
* Lớp hiện có.
* Số chỗ còn lại.
* Số lớp mới đề xuất.
* Sĩ số dự kiến.
* Danh sách sinh viên dự kiến.

Người dùng có thể:

* Tích chọn lớp cần mở.
* Bỏ chọn lớp không mở.
* Thay đổi sĩ số.
* Gộp hoặc tách lớp.
* Thêm lớp thủ công.
* Loại một số sinh viên khỏi đề xuất.
* Xác nhận tạo lớp.

---

# 7. Bước 4: Tạo lớp

Sau khi kế hoạch được duyệt, hệ thống tạo `Class Offering`.

## Thông tin lớp

* Course.
* Semester.
* Campus.
* Loại lớp.
* Capacity.
* Sĩ số tối thiểu.
* Sĩ số tối đa.
* Nhóm sinh viên dự kiến.
* Tổng số giờ học.
* Số giờ mỗi buổi.
* Số buổi mỗi tuần.
* Tổng số buổi.
* Khoảng thời gian diễn ra lớp.
* Yêu cầu loại phòng.
* Trạng thái lớp.

## Trạng thái lớp đề xuất

```text
Draft
Planned
Approved
Active
Completed
Cancelled
```

Khi lớp mới được tạo, chưa bắt buộc phải có giảng viên, lịch hoặc phòng.

Ví dụ:

```text
Class status: Planned
Lecturer: Unassigned
Schedule: Unscheduled
Room: Unassigned
```

---

# 8. Bước 5: Quản lý năng lực giảng viên

Hệ thống cần biết giảng viên nào được phép dạy course nào.

## Lecturer Course Qualification

Cần lưu:

* Giảng viên.
* Course.
* Loại lớp được phép dạy.
* Campus được phép giảng dạy.
* Trạng thái đủ điều kiện.
* Mức độ ưu tiên.
* Ngày hiệu lực.
* Ngày hết hiệu lực.
* Ghi chú hoặc điều kiện bổ sung.

Một giảng viên có thể dạy nhiều course và một course có thể có nhiều giảng viên đủ điều kiện.

## Trạng thái năng lực

```text
Eligible
Pending approval
Suspended
Expired
Not eligible
```

---

# 9. Bước 6: Quản lý thời gian của giảng viên

## Lịch khả dụng định kỳ

Giảng viên cần có lịch khả dụng theo kỳ:

* Ngày trong tuần.
* Thời gian bắt đầu.
* Thời gian kết thúc.
* Campus.
* Có thể dạy.
* Ưu tiên dạy.
* Không thể dạy.

## Ngoại lệ theo ngày

Hệ thống cần hỗ trợ:

* Nghỉ phép.
* Công tác.
* Họp.
* Lịch dạy bên ngoài.
* Thời gian bận đột xuất.
* Ngày có thể dạy bổ sung.

## Định mức giảng dạy

Cần cấu hình:

* Số giờ tối đa mỗi ngày.
* Số giờ tối đa mỗi tuần.
* Số lớp tối đa trong kỳ.
* Số ca liên tiếp tối đa.
* Khoảng nghỉ tối thiểu giữa hai ca.
* Thời gian di chuyển giữa các campus.
* Có cho phép dạy nhiều campus trong ngày hay không.
* Có cho phép vượt định mức hay không.
* Trường hợp nào cần phê duyệt khi vượt định mức.

---

# 10. Bước 7: Phân công giảng viên

Phân công giảng viên nên được tách riêng khỏi lớp và lịch về mặt dữ liệu.

## Entity đề xuất

```text
Class Offering
Lecturer Assignment
Class Schedule
```

## Điều kiện lọc giảng viên

Giảng viên được đề xuất khi:

* Được phép dạy course.
* Phù hợp với loại lớp.
* Phù hợp với campus.
* Năng lực còn hiệu lực.
* Chưa vượt định mức.
* Có thời gian khả dụng.
* Không có lịch dạy trùng.
* Không có lịch công tác hoặc lịch bận.
* Đủ thời gian di chuyển giữa các campus.

## Trạng thái phân công

```text
Proposed
Assigned
Confirmed
Rejected
Replaced
Cancelled
```

Một lớp có thể có nhiều giảng viên:

* Giảng viên chính.
* Giảng viên lý thuyết.
* Giảng viên thực hành.
* Trợ giảng.
* Giảng viên thay thế.

Người dùng có thể khóa giảng viên trước khi chạy tạo lịch.

---

# 11. Bước 8: Tự động tạo lịch học

Hệ thống tạo lịch dựa trên:

* Danh sách lớp cần xếp.
* Giảng viên được đề xuất hoặc đã được khóa.
* Phòng học.
* Lịch của sinh viên.
* Quy tắc thời lượng của course.
* Thời gian khả dụng.
* Các ngày và giờ bị loại trừ.

## Rule thời lượng course

Đối với từng course hoặc loại lớp cần cấu hình:

* Tổng số giờ học.
* Số giờ mỗi session.
* Số session mỗi tuần.
* Tổng số session.
* Số tuần học.
* Khoảng cách giữa các session.
* Có được học nhiều session trong một ngày không.
* Có được xếp session bù không.
* Có được xếp lịch cách tuần không.
* Thời lượng nghỉ giữa các session.

Ví dụ:

```text
Course: Programming Fundamentals
Tổng thời lượng: 45 giờ
Mỗi session: 3 giờ
Số session: 15
Tần suất: 1 session/tuần
```

## Khung giờ học

Cần quản lý các ca học, ví dụ:

```text
Ca 1: 07:30–10:30
Ca 2: 10:45–13:45
Ca 3: 14:00–17:00
Ca 4: 18:00–21:00
```

Mỗi course có thể được phép sử dụng một số ca nhất định.

## Thời gian loại trừ

Có thể cấu hình theo:

* Toàn trường.
* Campus.
* Semester.
* Course.
* Giảng viên.
* Phòng.
* Nhóm sinh viên.

Ví dụ:

* Không học Chủ nhật.
* Không học ngày lễ.
* Không học trong thời gian thi.
* Không học sau 21:00.
* Không học trong giờ nghỉ trưa.
* Course chỉ được học vào buổi tối.
* Giảng viên không dạy sáng thứ Hai.
* Phòng lab không hoạt động vào thứ Bảy.

---

# 12. Bước 9: Quản lý phòng học

Phòng học cần có các thông tin:

* Campus.
* Loại phòng.
* Sức chứa.
* Thiết bị.
* Thời gian hoạt động.
* Lịch bảo trì.
* Các course hoặc loại lớp được phép sử dụng.
* Khả năng hỗ trợ học trực tuyến hoặc hybrid.

Phòng được đề xuất khi:

* Đủ sức chứa.
* Đúng loại phòng.
* Có thiết bị phù hợp.
* Không bị trùng lịch.
* Không nằm trong thời gian bảo trì hoặc bị khóa.

---

# 13. Bước 10: Kiểm tra xung đột

Hệ thống không được tạo phương án vi phạm các ràng buộc bắt buộc.

## Xung đột giảng viên

* Một giảng viên dạy hai lớp cùng thời gian.
* Dạy trong thời gian không khả dụng.
* Vượt định mức.
* Không đủ thời gian di chuyển giữa campus.
* Không đủ điều kiện dạy course.

## Xung đột sinh viên

* Một sinh viên học hai course cùng thời gian.
* Học course chưa đủ prerequisite.
* Đăng ký lại course đã pass.
* Vượt số course hoặc số tín chỉ cho phép.
* Có quá nhiều ca học liên tiếp.
* Lịch học vượt khung thời gian được phép.

## Xung đột phòng

* Một phòng được dùng cho hai lớp cùng thời gian.
* Phòng không đủ capacity.
* Sai loại phòng.
* Thiếu thiết bị.
* Phòng đang bảo trì.

## Xung đột lớp

* Lớp không đủ số session.
* Tổng thời lượng không đúng.
* Session nằm ngoài kỳ học.
* Trùng ngày nghỉ.
* Vi phạm khoảng nghỉ giữa các session.

---

# 14. Bước 11: Tối ưu phương án

Sau khi loại bỏ các phương án không hợp lệ, hệ thống chấm điểm các phương án còn lại.

## Tiêu chí ưu tiên

* Tận dụng lớp hiện có trước.
* Hạn chế mở lớp mới không cần thiết.
* Cân bằng sĩ số giữa các lớp.
* Ưu tiên giảng viên phù hợp nhất.
* Cân bằng khối lượng giảng dạy.
* Hạn chế khoảng trống trong lịch sinh viên.
* Hạn chế khoảng trống trong lịch giảng viên.
* Hạn chế học buổi tối hoặc cuối tuần.
* Hạn chế thay đổi campus trong ngày.
* Ưu tiên phòng phù hợp nhất.
* Hạn chế thay đổi lịch đã được xác nhận.

Các tiêu chí nên có trọng số cấu hình được.

---

# 15. Bước 12: Tự động đăng ký sinh viên

Sau khi lớp, giảng viên và lịch đã có phương án phù hợp, hệ thống tự động đăng ký sinh viên.

## Quy tắc ưu tiên đăng ký

1. Lớp hiện có phù hợp.
2. Lớp còn chỗ và không xung đột.
3. Lớp học ghép.
4. Lớp dành cho sinh viên học lại.
5. Lớp mới được mở.
6. Lớp có lịch phù hợp nhất với toàn bộ các course của sinh viên.

## Trạng thái đăng ký

```text
Proposed
Draft
Confirmed
Removed
Rejected
Waitlisted
```

Đăng ký tự động ban đầu nên ở trạng thái `Draft` hoặc `Proposed`.

Người dùng có thể:

* Xóa sinh viên khỏi đăng ký.
* Chuyển sang lớp khác.
* Thêm sinh viên thủ công.
* Khóa đăng ký.
* Đưa sinh viên vào danh sách chờ.
* Chạy lại việc phân bổ.

Hệ thống phải lưu lý do sinh viên được gán vào lớp nào và rule nào đã được áp dụng.

---

# 16. Thứ tự giữa đăng ký và tạo lịch

Hệ thống nên hỗ trợ hai giai đoạn:

## Giai đoạn lập kế hoạch

Dùng nhóm sinh viên dự kiến để tạo lớp, phân công giảng viên và tạo lịch.

## Giai đoạn đăng ký chính thức

Sau khi lịch được tạo, hệ thống tự động gán từng sinh viên vào lớp và kiểm tra chính xác xung đột.

Nếu phát hiện xung đột, hệ thống xử lý theo thứ tự:

1. Chuyển sinh viên sang lớp khác còn chỗ.
2. Đổi phương án lịch chưa được khóa.
3. Đề xuất mở thêm lớp.
4. Đưa sinh viên vào danh sách chờ.
5. Đánh dấu cần xử lý thủ công.

---

# 17. Tách module và khả năng chạy lại

Nên chia hệ thống thành các module:

1. Course Demand Analysis.
2. Existing Class Matching.
3. New Class Planning.
4. Class Creation.
5. Lecturer Assignment.
6. Timetable Generation.
7. Room Assignment.
8. Student Auto Enrollment.
9. Conflict Detection.
10. Review and Publishing.

Người dùng có thể chạy lại từng module độc lập.

Ví dụ:

* Tạo lại lịch nhưng giữ nguyên lớp và giảng viên.
* Đổi giảng viên nhưng giữ lịch nếu giảng viên mới phù hợp.
* Tạo lại đăng ký nhưng không thay đổi lớp.
* Thêm lớp mới mà không ảnh hưởng các lớp đã khóa.
* Chạy lại phần chưa được xác nhận.

---

# 18. Cơ chế khóa dữ liệu

Người dùng cần có khả năng khóa:

* Lớp.
* Giảng viên.
* Phòng.
* Khung giờ.
* Session.
* Danh sách sinh viên.
* Đăng ký của từng sinh viên.

Khi chạy lại tự động, hệ thống không được thay đổi dữ liệu đã khóa.

---

# 19. Duyệt và công bố

## Trạng thái lịch

```text
Draft
Generated
Conflict
Approved
Published
Cancelled
```

## Trước khi công bố

Hệ thống phải kiểm tra:

* Tất cả lớp có đủ session.
* Không có xung đột bắt buộc.
* Có giảng viên hợp lệ.
* Có phòng phù hợp.
* Sinh viên được đăng ký hợp lệ.
* Các trường hợp ngoại lệ đã được duyệt.
* Các lớp dưới sĩ số tối thiểu đã được phê duyệt.

Sau khi công bố, mọi thay đổi phải:

* Có lý do.
* Lưu lịch sử.
* Xác định người thực hiện.
* Kiểm tra lại toàn bộ xung đột liên quan.
* Thông báo cho sinh viên và giảng viên bị ảnh hưởng.

---

# 20. Yêu cầu audit và giải thích kết quả

Hệ thống cần lưu:

* Rule được áp dụng.
* Lý do mở lớp.
* Lý do không mở lớp.
* Lý do chọn giảng viên.
* Lý do chọn lịch.
* Lý do gán sinh viên vào lớp.
* Các phương án đã bị loại.
* Các xung đột đã phát hiện.
* Người duyệt.
* Thời điểm thay đổi.
* Phiên bản rule.

Người dùng phải có thể xem giải thích, ví dụ:

```text
Sinh viên A được gán vào lớp CS101-02 vì:

- Lớp CS101-01 đã đủ capacity.
- Lớp CS101-02 còn chỗ.
- Không trùng với các course khác.
- Đúng campus.
- Đúng chương trình.
```

---

# 21. Kết quả đầu ra

Hệ thống cần trả về:

## Kế hoạch lớp

* Danh sách lớp hiện có được sử dụng.
* Danh sách lớp mới đề xuất.
* Danh sách lớp đã được duyệt mở.
* Sĩ số dự kiến và thực tế.

## Phân công giảng viên

* Giảng viên đề xuất.
* Giảng viên được gán.
* Giảng viên đã xác nhận.
* Các trường hợp chưa có giảng viên.

## Thời khóa biểu

* Session của từng lớp.
* Giảng viên.
* Phòng.
* Campus.
* Ngày và giờ.
* Trạng thái xung đột.

## Đăng ký sinh viên

* Sinh viên được đăng ký thành công.
* Sinh viên bị xung đột.
* Sinh viên ở danh sách chờ.
* Sinh viên chưa có lớp phù hợp.
* Sinh viên bị loại do không đủ điều kiện.

## Báo cáo ngoại lệ

* Lớp dưới sĩ số tối thiểu.
* Lớp vượt capacity.
* Giảng viên vượt định mức.
* Lớp chưa có phòng.
* Lớp chưa có giảng viên.
* Sinh viên chưa được xếp.
* Xung đột chưa được xử lý.

---

# 22. Nguyên tắc thiết kế chính

* Tách riêng lớp, phân công giảng viên, lịch và đăng ký sinh viên về mặt dữ liệu.
* Cho phép hệ thống tối ưu giảng viên, lịch và phòng trong cùng một lần chạy.
* Không tự động công bố kết quả khi chưa có người duyệt.
* Không thay đổi dữ liệu đã khóa.
* Mọi kết quả tự động phải giải thích được.
* Mọi thay đổi phải có lịch sử.
* Các rule phải cấu hình được theo kỳ, campus, chương trình và course.
* Ưu tiên tận dụng lớp hiện có trước khi mở lớp mới.
* Sinh viên học lại có thể đăng ký vào lớp mới nếu đáp ứng điều kiện.
* Người dùng có thể xóa hoặc thay đổi đăng ký tự động trước khi xác nhận.
