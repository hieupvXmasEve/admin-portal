# 📄 REQUIREMENT – GPA MANAGEMENT (PHASE 2 - ADVANCED)

## 1. Mục tiêu Phase 2

Mở rộng chức năng GPA sau khi Phase 1 đã ổn định, tập trung vào các **nghiệp vụ xử lý phức tạp** và **tự động hóa cao cấp** phục vụ công tác xét duyệt, xếp hạng và khen thưởng.

## 2. Phạm vi nghiệp vụ (In scope Phase 2)

Những tính năng này nằm ngoài phạm vi Phase 1 và sẽ được triển khai trong giai đoạn này:

| Tính năng            | Mô tả                                                           |
| :------------------- | :-------------------------------------------------------------- |
| **Academic Ranking** | Xếp hạng sinh viên trong khóa/ngành (Top 10%, Top 100).         |
| **Scholarship**      | Xét học bổng khuyến khích học tập tự động dựa trên criteria.    |
| **Graduation Check** | Xét tốt nghiệp, kiểm tra điều kiện ra trường.                   |
| **Honors**           | Danh hiệu tốt nghiệp (Xuất sắc, Giỏi, Khá...).                  |
| **Dean's List**      | Danh sách vinh danh theo kỳ.                                    |
| **Complex Logic**    | Xử lý học lại, học cải thiện, chuyển đổi điểm phức tạp (Audit). |

## 3. Chi tiết nghiệp vụ

### 3.1 Ranking (Xếp hạng)

- **Logic:** Xếp hạng dựa trên GPA tích lũy hoặc GPA học kỳ.
- **Scope:** Theo Cohort (Khóa), Major (Ngành), hoặc toàn trường.
- **Output:** Report danh sách thứ hạng.

### 3.2 Scholarship Auto-Evaluation (Xét học bổng)

- **Configurable Criteria:** Cho phép định nghĩa tiêu chí học bổng động (VD: GPA > 3.6, ĐRL > 90, Không rớt môn nào).
- **Workflow:**
    1.  Admin cấu hình tiêu chí.
    2.  Hệ thống chạy batch job quét sinh viên đủ điều kiện.
    3.  Tạo danh sách đề cử.

### 3.3 Graduation Audit (Xét tốt nghiệp)

- **Credit Check:** Kiểm tra đủ số tín chỉ tích lũy theo Curriculum.
- **Subject Check:** Đã học đủ các môn bắt buộc.
- **GPA Check:** GPA tích lũy >= 2.0.
- **Special Rules:** Chứng chỉ ngoại ngữ, GDQP, etc.

### 3.4 Graduation Honors

- Phân loại tốt nghiệp dựa trên GPA tích lũy:
    - Top tier: Summa Cum Laude / Xuất sắc.
    - Middle tier: Magna Cum Laude / Giỏi.
    - Lower tier: Cum Laude / Khá.

## 4. Yêu cầu kỹ thuật dự kiến

- **Complex Queries:** Cần tối ưu SQL query hoặc sử dụng View/Materialized View cho việc ranking số lượng lớn.
- **Rule Engine:** Có thể cần thiết kế pattern Strategy hoặc Rule Engine để xử lý các điều kiện xét tốt nghiệp/học bổng đa dạng.
- **Background Jobs:** Các tác vụ này nặng và nên chạy qua Laravel Queue.

## 5. Dependency

- Phải hoàn thành **Phase 1 (Basic GPA)** trước.
- Dữ liệu lịch sử điểm phải chính xác.
