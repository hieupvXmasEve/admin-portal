# 📄 Admin – Academic Report

## 1. Trang

```
/academic-report
```

## 2. Mục đích

- Xem **báo cáo học tập sinh viên**
- Hiển thị **theo dạng bảng ngang (pivot theo môn)**
- Export Excel/CSV **đúng cấu trúc bảng**

## 3. Bộ lọc (header)

- Campus
- Semester
- Program / Specialization
- Student status
- Keyword (MSV / tên)

## 4. Cấu trúc bảng (THEO ĐÚNG HÌNH)

### Cột cố định

| Cột |
| --- |
| Tên |
| MSV |

### Cột động theo môn (course/unit)

Với **mỗi môn học** → tạo **2 cột con**:

```
Môn X
- attendance
- total score
```

Ví dụ:

```
Môn 1 | attendance | total score
Môn 2 | attendance | total score
Môn 3 | attendance | total score
```

### Cột tổng

| Cột                 |
| ------------------- |
| Sum of % attendance |
| Sum of GPA          |

## 5. Mapping dữ liệu

### Theo từng môn

- attendance → `academic_records.attendance_percentage`
- total score → `academic_records.final_percentage`

### Tổng

- Sum of % attendance
  → `AVG(academic_records.attendance_percentage)` theo student
- Sum of GPA
  → `gpa_calculations.gpa (cumulative, is_current = 1)`

## 6. Quy tắc hiển thị

- 1 dòng = 1 sinh viên
- Nếu sinh viên **không học môn đó** → để trống
- Số môn hiển thị = **toàn bộ môn trong semester được chọn**

## 7. Export

- Button: `Export`
- Định dạng:
    - Excel (.xlsx)
    - CSV

- File export **GIỐNG Y HỆT TABLE UI**
    - Header 2 dòng (môn → attendance / total score)
    - Không pivot lại khi export

## 8. API (gợi ý)

```
GET /api/admin/academic-report
```

Response:

- Đã pivot sẵn theo môn
- FE chỉ render table, không xử lý logic

## 9. Không làm

- Không chỉnh sửa điểm
- Không drill-down chi tiết môn
- Không dùng bảng attendances raw

## 10. Deliverables cho dev

- API trả dữ liệu dạng pivot
- UI table dynamic column
- Export Excel/CSV đúng layout
- Pagination + filter
