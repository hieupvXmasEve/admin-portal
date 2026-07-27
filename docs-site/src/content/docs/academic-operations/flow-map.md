---
title: Bản đồ luồng trang
description: Các trang trong khu vực học vụ nối với nhau thế nào theo quy trình vận hành.
source:
  - resources/js/constants/menu-sidebar.ts
---

Trang này mô tả đường đi giữa các màn hình theo công việc thực tế. Dùng khi bạn biết mình cần làm gì nhưng chưa biết bắt đầu ở đâu.

## Luồng tổng quát

```text
Curriculum Setup
  -> Course Delivery
  -> Attendance & Completion
  -> Grades & Performance
  -> Student Services / Finance Office khi cần xử lý hồ sơ hoặc học phí
```

## Chuẩn bị dữ liệu học vụ

| Bước | Trang | Kết quả mong đợi |
| --- | --- | --- |
| 1 | Academic Terms | Có kỳ học để mở lớp và chốt GPA |
| 2 | Programs | Có chương trình đào tạo để gắn khung chương trình |
| 3 | Curriculum Versions | Có phiên bản chương trình đang áp dụng |
| 4 | Units | Có môn học với đủ thông tin tín chỉ, môn tiên quyết |
| 5 | Syllabus Templates | Có mẫu đề cương dùng cho lớp cụ thể |

Xong bước này thì sang **Course Offering List** để mở lớp.

## Vận hành lớp học

| Bước | Trang | Kết quả mong đợi |
| --- | --- | --- |
| 1 | Course Offering List | Lớp môn đã được mở theo kỳ |
| 2 | Class Schedule | Buổi học đã có ngày, giờ, phòng |
| 3 | Course Registration | Sinh viên đã được ghi vào lớp |
| 4 | Canvas Courses | Lớp đã ghép với lớp học trực tuyến, nếu cần |
| 5 | Attendance Summary | Theo dõi được đi học trong lúc lớp đang chạy |

Nếu lớp có sinh viên rớt môn, sang **Failed Students** rồi **Retake Registration**.

## Kiểm soát rủi ro

| Dấu hiệu | Trang kiểm tra | Trang xử lý tiếp |
| --- | --- | --- |
| Vắng học nhiều | Attendance Summary | Warning Center |
| Kết quả lớp thấp | Course Statistics | Failed Students |
| GPA dưới ngưỡng | GPA History | Warning Center |
| Cần học lại | Failed Students | Retake Registration |
| Cần thi lại | Thi lại | Lịch thi lại |

## Chốt kết quả cuối kỳ

| Bước | Trang | Kết quả mong đợi |
| --- | --- | --- |
| 1 | Course Statistics | Đã kiểm tra dữ liệu lớp và kết quả |
| 2 | GPA Management | GPA của kỳ đã được chốt |
| 3 | GPA History | Tra cứu lại được kết quả đã chốt |
| 4 | Warning Center | Đã lọc ra sinh viên cần xử lý |

Báo cáo tổng hợp toàn trường nằm ở nhóm menu **Reports & Audits**.

## Khi nào rời khỏi khu vực học vụ

| Từ trang | Sang đâu | Vì sao |
| --- | --- | --- |
| Course Registration | Student Services | Kiểm tra hồ sơ sinh viên trước khi đăng ký |
| Failed Students | Student Services | Xem chi tiết điểm, điểm danh, GPA của một sinh viên |
| Retake Registration | Finance Office | Xử lý học phí học lại |
| Lịch thi lại | Campus Operations | Đặt phòng thi để tránh trùng lịch |
| Warning Center | Student Services | Ghi nhận quyết định xử lý sinh viên |
| Canvas Courses | Faculty & Teaching | Đối chiếu giảng viên phụ trách lớp |

## Chọn điểm bắt đầu theo câu hỏi

| Câu hỏi của bạn | Vào trang |
| --- | --- |
| "Tôi cần mở lớp cho kỳ mới" | Course Offering List |
| "Sinh viên đã đăng ký môn chưa?" | Course Registration |
| "Ai đang vắng học quá nhiều?" | Attendance Summary |
| "Sinh viên nào rớt môn?" | Failed Students |
| "Tôi cần chốt GPA kỳ này" | GPA Management |
| "Ai đang bị cảnh báo học vụ?" | Warning Center |
| "Tôi cần xếp ca thi lại" | Lịch thi lại |
