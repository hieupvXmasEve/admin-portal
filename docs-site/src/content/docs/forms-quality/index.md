---
title: Forms & Quality
description: Thư viện biểu mẫu, đợt phát biểu mẫu, kết quả khảo sát và hộp thư yêu cầu.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Forms/Admin/Index.vue
  - resources/js/pages/Forms/Runs/Index.vue
  - resources/js/pages/Forms/Admin/results/Index.vue
  - resources/js/pages/Forms/Queries/Inbox.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (new "Doanh thu" revenue nav item under Finance Office) -- no update needed here. -->

**Forms & Quality** dùng để thu thập thông tin từ sinh viên: khảo sát chất lượng giảng dạy, phiếu đăng ký, đơn từ.

Ba khái niệm cần phân biệt:

| Khái niệm | Nghĩa |
| --- | --- |
| **Form** (biểu mẫu) | Bản thiết kế câu hỏi. Tạo một lần, dùng nhiều lần. |
| **Run** (đợt phát) | Một lần đem biểu mẫu đó phát cho một nhóm người, trong một khoảng thời gian. |
| **Result** (kết quả) | Câu trả lời thu về từ một đợt phát. |

Không tạo biểu mẫu mới cho mỗi kỳ. Tạo **đợt phát mới** từ biểu mẫu đã có.

## Forms Library — Thư viện biểu mẫu

**Dùng để làm gì.** Tạo và quản lý các biểu mẫu. Màn hình tên **Forms Management**.

**Ai vào được.** Người có quyền xem biểu mẫu.

**Các bước**

1. Vào **Forms & Quality → Forms Library**.
2. Tạo biểu mẫu mới, hoặc mở biểu mẫu có sẵn để sửa.
3. Bấm **Clear** để xóa điều kiện lọc.

**Lưu ý**

- Sửa biểu mẫu đang có đợt phát chạy dở sẽ làm câu trả lời không đồng nhất. Nếu cần đổi câu hỏi, tạo bản mới.
- Đặt tên biểu mẫu đủ rõ để người khác biết dùng vào việc gì.

## Runs — Đợt phát

### Runs List

**Dùng để làm gì.** Xem các đợt phát đang chạy và đã kết thúc. Màn hình tên **Form Runs**.

**Các bước**

1. Vào **Forms & Quality → Runs → Runs List**.
2. Dùng khung **Filters** để lọc.
3. Bấm **Clear** để xem lại toàn bộ.
4. Mở một đợt để xem tiến độ trả lời.

### Create Run

**Dùng để làm gì.** Mở một đợt phát mới.

**Các bước**

1. Vào **Forms & Quality → Runs → Create Run**.
2. Chọn biểu mẫu cần phát.
3. Chọn nhóm người nhận và khoảng thời gian mở.
4. Lưu để bắt đầu đợt.

**Lưu ý**

- Kiểm tra kỹ nhóm người nhận trước khi lưu. Phát nhầm nhóm thì không thu hồi được thông báo đã gửi.
- Khoảng thời gian quá ngắn sẽ khiến tỉ lệ trả lời thấp. Với khảo sát cuối kỳ, mở trước khi sinh viên thi xong.

## Surveys — Kết quả khảo sát

### Survey Results

**Dùng để làm gì.** Xem câu trả lời thu được từ các đợt phát.

**Ai vào được.** Người có quyền xem kết quả khảo sát tổng hợp.

**Các bước.** Vào **Forms & Quality → Surveys → Survey Results**, chọn đợt phát cần xem.

**Lưu ý.** Kết quả khảo sát giảng dạy là dữ liệu nhạy cảm. Chỉ chia sẻ trong phạm vi được phép.

### Program Stats

**Dùng để làm gì.** Số liệu tổng hợp theo chương trình đào tạo, thay vì theo từng đợt phát.

**Đi tiếp.** Lecturer GPA, Course Ranking.

## Queries — Hộp thư yêu cầu

### Staff Inbox

**Dùng để làm gì.** Nhận và xử lý yêu cầu, thắc mắc do sinh viên gửi qua biểu mẫu.

**Ai vào được.** Người có quyền duyệt biểu mẫu.

**Các bước**

1. Vào **Forms & Quality → Queries → Staff Inbox**.
2. Mở từng yêu cầu, đọc nội dung.
3. Trả lời hoặc chuyển tiếp cho bộ phận phụ trách.

**Lưu ý.** Đây là hộp thư chung, không phải hộp thư cá nhân. Xử lý xong nên đánh dấu để đồng nghiệp không làm trùng.

## Việc thường gặp

| Tình huống | Thứ tự làm |
| --- | --- |
| Khảo sát chất lượng giảng dạy cuối kỳ | Forms Library (chọn mẫu) → Create Run → Survey Results |
| Xem tỉ lệ trả lời của đợt đang chạy | Runs List → mở đợt |
| Tổng hợp phản hồi theo ngành | Program Stats |
| Xử lý thắc mắc sinh viên gửi lên | Staff Inbox |
