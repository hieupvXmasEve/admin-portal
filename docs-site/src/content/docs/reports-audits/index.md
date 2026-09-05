---
title: Reports & Audits
description: Báo cáo tổng hợp và đối chiếu dữ liệu học vụ toàn trường.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Admin/Reports/StudentActionsAudit.vue
  - resources/js/pages/Admin/Reports/StudentDecisions/Index.vue
  - resources/js/pages/Admin/Reports/AcademicProgressionAudit/Index.vue
  - resources/js/pages/Admin/Reports/AcademicProgressionAudit/MissingDocuments.vue
  - resources/js/pages/Admin/Reports/AcademicProgressionAudit/MissingDecisions.vue
  - resources/js/pages/Admin/Academic/Performance/Dashboard.vue
  - resources/js/pages/Academic/CourseRanking/Index.vue
  - resources/js/pages/Academic/Report/Index.vue
  - resources/js/pages/Admin/Reports/StudentLifecycleYearlyAnalysis/Index.vue
  - resources/js/pages/Admin/Reports/StudentLifecycleYearlyAnalysis/StudentStatusTable.vue
  - resources/js/pages/Academic/Report/StudentUnits/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Reports & Audits** dành cho cấp quản lý: nhìn số liệu toàn trường và phát hiện chỗ dữ liệu bị thiếu.

Khác với **Students** — bên đó xử lý từng sinh viên. Ở đây mỗi dòng báo cáo dẫn **một chiều** vào hồ sơ sinh viên liên quan; từ hồ sơ không quay ngược lại báo cáo được.

Hai nhóm: **Lifecycle & Decisions** (vòng đời và quyết định) và **Academic Performance** (kết quả học tập).

## Lifecycle & Decisions

### Student Actions Audit — Nhật ký tác động lên sinh viên

**Dùng để làm gì.** Xem lại mọi thao tác đã thực hiện trên hồ sơ sinh viên: ai làm, làm gì, khi nào.

**Ai vào được.** Người có quyền xem tác động sinh viên.

**Các bước**

1. Vào **Reports & Audits → Lifecycle & Decisions → Student Actions Audit**.
2. Xem bảng **Action Logs**.
3. Lọc theo thời gian, loại thao tác hoặc người thực hiện.
4. Bấm **Reset all** để xóa toàn bộ bộ lọc.

**Lưu ý.** Đây là màn hình để trả lời câu hỏi "ai đã đổi cái này". Dùng khi có tranh chấp hoặc khi rà soát nội bộ.

### Lifecycle Yearly Analysis — Phân tích vòng đời theo năm

**Dùng để làm gì.** Xem diễn biến của các khóa sinh viên qua từng năm: bao nhiêu người học tiếp, bảo lưu, thôi học, tốt nghiệp. Bấm thẳng vào một con số trong bảng để xem danh sách sinh viên đứng sau con số đó.

**Ai vào được.** Người có quyền xem tác động sinh viên.

**Nội dung màn hình**

- **Sinh viên theo khoá và kỳ** — một bảng ma trận cho mỗi khoá tuyển sinh: hàng là trạng thái (đang học, bảo lưu, thôi học, tốt nghiệp...), cột là kỳ học. Ô nào có số > 0 bấm được để xem danh sách; ô đang chọn tô nổi bật.
- **Biến động theo kỳ** — mỗi kỳ có bao nhiêu sinh viên nhập học mới và bao nhiêu người đổi trạng thái.
- **Hiện trạng từng khoá** — sĩ số, số NE (chưa xác định), tỷ lệ bảo lưu / thôi học / tốt nghiệp của từng khoá tính đến kỳ hiện tại.
- **Danh sách sinh viên theo kỳ** — bảng chi tiết, lọc theo kỳ học và trạng thái; đồng bộ với ô vừa bấm ở ma trận.

**Các bước**

1. Vào **Reports & Audits → Lifecycle & Decisions → Lifecycle Yearly Analysis**.
2. Bấm vào một ô số trong bảng ma trận (ví dụ: số sinh viên bảo lưu của khoá X tại kỳ Y). Hộp thoại **Danh sách sinh viên** mở ra đúng danh sách đó.
3. Hoặc chọn thủ công **Kỳ học** và **Trạng thái** ở khung **Danh sách sinh viên theo kỳ** phía dưới nếu không cần lọc theo khoá.
4. Bấm **Bỏ lọc khoá** để xem lại toàn bộ sinh viên trong kỳ, không giới hạn theo khoá đã chọn.
5. Bấm **Xuất Excel** để tải danh sách đang xem (đã áp dụng bộ lọc kỳ / trạng thái / khoá hiện tại).

**Lưu ý.** Ma trận chỉ tính một khoá vào cột kỳ học kể từ kỳ khoá đó thực sự nhập học; các kỳ trước đó hiển thị `-`, không phải 0.

### Academic Progression — Đối chiếu tiến độ học tập

**Dùng để làm gì.** Kiểm tra sinh viên có được xếp đúng giai đoạn học hay không.

**Ai vào được.** Người có quyền xem tác động sinh viên.

**Nội dung màn hình.** Các thẻ tổng hợp: **Pre-Uni GC Placements** (xếp lớp dự bị), **Intake Course Placements** (xếp lớp đầu vào), **Stage Changes** (chuyển giai đoạn), **IELTS Recorded** (đã ghi nhận điểm IELTS).

**Lưu ý.** Số liệu ở đây lệch so với thực tế thường có nghĩa hồ sơ chưa được cập nhật, không phải sinh viên xếp sai. Kiểm tra hai màn hình dưới trước khi kết luận.

### Missing Documents — Thiếu giấy tờ

**Dùng để làm gì.** Lọc ra sinh viên còn thiếu chứng chỉ IELTS trong hồ sơ. Màn hình tên đầy đủ là **Missing IELTS Documents**.

**Các bước**

1. Vào **Reports & Audits → Lifecycle & Decisions → Missing Documents**.
2. Xem bảng **Students with Missing Documents**.
3. Liên hệ sinh viên bổ sung, hoặc cập nhật nếu giấy tờ đã nộp nhưng chưa nhập.

**Lưu ý.** Danh sách này nên về 0 trước mỗi mốc xét tiến độ. Để tồn đọng sẽ kẹt ở bước xét chuyển giai đoạn.

### Missing Decisions — Thiếu quyết định

**Dùng để làm gì.** Lọc ra các lần chuyển giai đoạn chưa có quyết định kèm theo.

**Các bước**

1. Vào **Reports & Audits → Lifecycle & Decisions → Missing Decisions**.
2. Xem bảng **Transitions Missing a Decision**.
3. Bổ sung quyết định cho từng trường hợp.

**Lưu ý.** Thiếu quyết định là lỗi hồ sơ pháp lý, không chỉ là dữ liệu thiếu. Ưu tiên xử lý. **Bảo lưu (Defer)** không nằm trong danh sách này — không cần quyết định kèm theo.

### Student Decisions — Quyết định sinh viên

**Dùng để làm gì.** Tra cứu toàn bộ quyết định đã ban hành cho sinh viên.

**Các bước**

1. Vào **Reports & Audits → Lifecycle & Decisions → Student Decisions**.
2. Dùng khung **Filter** để thu hẹp.
3. Xem bảng **Decision List**, mở từng quyết định để xem chi tiết.

## Academic Performance

### Performance Dashboard — Bảng theo dõi kết quả

**Dùng để làm gì.** Nhìn tổng quan kết quả học tập toàn trường. Màn hình tên **Academic Performance**.

**Ai vào được.** Người có quyền xem điểm.

**Nội dung màn hình.** Bốn thẻ chính: **Avg Semester GPA** (GPA trung bình kỳ), **Avg Cumulative GPA** (GPA tích lũy), **At-Risk Students** (sinh viên có rủi ro), **Completion Rate** (tỉ lệ hoàn tất).

**Lưu ý.** Số liệu chỉ đúng sau khi đã chốt GPA. Xem trước khi chốt sẽ ra kết quả dở dang.

### Course Ranking — Xếp hạng môn học

**Dùng để làm gì.** So sánh kết quả giữa các môn, tìm môn có kết quả cao hoặc thấp bất thường.

**Ai vào được.** Người có quyền xem điểm.

**Lưu ý.** Dùng cùng **Top 10 Failed Units** ở màn hình Failed Students để rà soát chất lượng giảng dạy cuối kỳ.

### Academic Report — Báo cáo học vụ

**Dùng để làm gì.** Báo cáo dạng bảng và biểu đồ để đưa vào tài liệu nội bộ.

**Nội dung màn hình.** **Grade Distribution** (phân bố điểm) và **Grade Statistics** (thống kê điểm).

**Các bước**

1. Vào **Reports & Audits → Academic Performance → Academic Report**.
2. Lọc theo kỳ, chương trình hoặc môn.
3. Bấm **Clear Filters** khi cần xem lại toàn bộ.

### Student Completed Units — Môn đã hoàn thành

**Dùng để làm gì.** Xem toàn bộ sinh viên đã qua môn, tách riêng nhóm môn **GC** (đại cương) và **Major** (chuyên ngành), kèm số môn và số tín chỉ đã tích lũy.

**Ai vào được.** Người có quyền xem báo cáo học vụ.

**Các bước**

1. Vào **Reports & Audits → Academic Performance → Student Completed Units**.
2. Tìm theo **Student ID / Name** hoặc lọc theo **Program** (chương trình đào tạo).
3. Xem cột **GC Units** và **Major Units** — mỗi môn hiển thị dạng thẻ mã môn; `—` nghĩa là chưa có môn nào ở nhóm đó.
4. Bấm **Export Excel** để tải danh sách đang xem (đã áp dụng bộ lọc hiện tại).

**Lưu ý.** Danh sách chỉ tính môn đã **qua** (passed); môn đang học hoặc đã rớt không xuất hiện ở đây.

## Việc thường gặp

| Tình huống | Trang dùng |
| --- | --- |
| "Ai đã sửa hồ sơ sinh viên này?" | Student Actions Audit |
| Chuẩn bị họp tổng kết kỳ | Performance Dashboard → Academic Report → Course Ranking |
| Rà soát hồ sơ trước mốc xét tiến độ | Missing Documents → Missing Decisions → Academic Progression |
| Thống kê tỉ lệ bỏ học theo khóa | Lifecycle Yearly Analysis |
| Xem sinh viên đã qua môn nào, GC hay Major | Student Completed Units |
