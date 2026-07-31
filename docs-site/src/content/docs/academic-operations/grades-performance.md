---
title: Grades & Performance
description: Chốt GPA, tra cứu lịch sử GPA và theo dõi sinh viên bị cảnh báo học vụ.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Admin/Academic/Gpa/Index.vue
  - resources/js/pages/Admin/Academic/Gpa/History.vue
  - resources/js/pages/Academic/Warnings/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (new "Doanh thu" revenue nav item under Finance Office) -- no update needed here. -->

**Grades & Performance** là giai đoạn chốt kết quả: tính GPA, tra cứu kết quả cũ, và phát hiện sinh viên có rủi ro học tập.

Các báo cáo tổng hợp toàn trường — Performance Dashboard, Academic Report, Course Ranking — nằm ở nhóm menu riêng **Reports & Audits**, không ở đây.

## GPA Management — Quản lý GPA

**Dùng để làm gì.** Chốt điểm trung bình của sinh viên cho một kỳ học.

**Ai vào được.** Người có quyền xem điểm.

**Các bước**

1. Vào **Academic Operations → Grades & Performance → GPA Management**.
2. Ở khung **Semester Finalization** (Chốt kỳ), chọn kỳ cần chốt.
3. Kiểm tra lại số liệu rồi xác nhận chốt.

**Lưu ý**

- Chốt GPA là mốc quan trọng: xét học bổng, cảnh báo học vụ, xét tốt nghiệp đều dựa vào kết quả này.
- Chốt trước khi điểm nhập đủ sẽ ra GPA sai. Xác nhận với các bộ phận nhập điểm đã xong trước khi bấm chốt.

**Đi tiếp.** GPA History, Warning Center.

## GPA History — Lịch sử GPA

**Dùng để làm gì.** Tra lại GPA đã chốt của các kỳ trước.

**Ai vào được.** Người có quyền xem điểm.

**Các bước**

1. Vào **Academic Operations → Grades & Performance → GPA History**.
2. Tìm sinh viên ở ô **Search student...**.
3. Lọc thêm theo **Semester** (kỳ học), **Program** (chương trình đào tạo) hoặc **Academic Standing** (xếp loại học vụ).

**Lưu ý.** Dùng màn hình này khi cần trả lời khiếu nại về điểm hoặc xác nhận kết quả các kỳ đã qua.

**Đi tiếp.** Warning Center, hồ sơ sinh viên.

## Warning Center — Trung tâm cảnh báo

**Dùng để làm gì.** Xem sinh viên đang bị cảnh báo, gồm hai loại:

- **Academic Standing Warnings** — cảnh báo do kết quả học tập.
- **Attendance Warnings** — cảnh báo do vắng học.

**Ai vào được.** Người có quyền xem điểm danh và xem điểm.

**Các bước**

1. Vào **Academic Operations → Grades & Performance → Warning Center**.
2. Xem hai khung cảnh báo, đối chiếu danh sách sinh viên cần xử lý.
3. Chuyển sang hồ sơ sinh viên để ghi nhận việc đã xử lý.

**Lưu ý**

- Cảnh báo cập nhật theo dữ liệu điểm và điểm danh, nên xem lại sau mỗi lần chốt GPA.
- Nên rà soát định kỳ, không đợi cuối kỳ mới xem.

**Đi tiếp.** Attendance Summary, Student Services.

## Luồng kiểm tra sau khi chốt GPA

```text
GPA Management
  -> GPA History
  -> Warning Center
```

Nếu phát hiện số liệu bất thường:

```text
GPA History
  -> hồ sơ học vụ của sinh viên
  -> Course Statistics
```
