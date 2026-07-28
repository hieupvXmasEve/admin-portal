---
phase: 3
title: "Excel export"
status: pending
priority: P2
effort: "0.5d"
dependencies: [2]
---

# Phase 3: Excel export

## Overview

Xuất Excel **giữ nguyên hình dạng UI**: 1 dòng = 1 sinh viên, cột chip đổi thành chuỗi mã môn ngăn dấu phẩy. Không xuất flat student × unit.

## Requirements

Functional:
- Route `GET /academic/reports/student-units/export`, gate `can:view_academic_report`.
- Tôn trọng đúng filter và sort đang áp trên UI (nhận cùng query string).
- Xuất **toàn bộ** kết quả khớp filter, không giới hạn theo trang → dùng `handleExport()` của contract.
- Cột trùng khớp UI: Mã SV · Họ tên · Ngành · Môn GC · Môn Major · Số môn · TC đạt.
- Ô "Môn GC" / "Môn Major" = chuỗi mã ngăn `, `, thứ tự giống UI (theo `code` asc).
- Header block đầu file: tên report, các filter đang áp, thời điểm xuất — theo khuôn `AcademicReportExport` hiện có.
- Tên file: `student-completed-units-{Y-m-d-His}.xlsx`.

Non-functional:
- Sinh viên không có môn nào → ô chuỗi rỗng, count 0, credits 0. Không bỏ dòng.

## Architecture

Tái dùng khuôn `app/Exports/AcademicReportExport.php`: `implements FromArray, WithStyles, WithTitle`, dựng mảng row thủ công (khối filter → dòng trống → header → data), style bằng `WithStyles`.

```
StudentCompletedUnitsController@export
  -> $reader->handleExport($filters)          // Collection, không paginate
  -> new StudentCompletedUnitsExport($rows, $filters)
  -> Excel::download($export, $filename)
```

Không thêm sheet thứ hai. Không thêm concern mới (`FromQuery`, `ShouldQueue`) — 234 sinh viên, xuất đồng bộ trong request là đủ.

## Related Code Files

- Create: `app/Exports/StudentCompletedUnitsExport.php`
- Modify: `app/Modules/Academic/Http/Web/StudentCompletedUnitsController.php` (thêm method `export`)
- Modify: `app/Modules/Academic/routes/web.php` (thêm route export)
- Modify: `resources/js/pages/Academic/Report/StudentUnits/Index.vue` (nút Export giữ nguyên query string hiện tại)
- Create: `tests/Feature/Academic/StudentCompletedUnitsExportTest.php`

Đọc tham khảo:
- `app/Exports/AcademicReportExport.php` — khuôn header block + styles
- `app/Modules/Academic/Http/Api/AcademicReportController.php` — khuôn gọi `Excel::download`

## Implementation Steps

1. Viết `StudentCompletedUnitsExport`: constructor nhận rows + filters, `array()` dựng khối filter rồi header rồi data, `styles()` bold header + border vùng data, `title()` = "Completed Units".
2. Thêm method `export` vào controller, dùng lại cùng FormRequest của Phase 2.
3. Đăng ký route export cạnh route index.
4. Thêm nút Export trên UI, link kèm query string filter/sort hiện tại.
5. Feature test: gọi export với filter, khẳng định status 200 và `Content-Type` là spreadsheet; khẳng định số dòng data khớp số sinh viên khớp filter.

## Success Criteria

- [ ] File tải về mở được, layout khớp UI
- [ ] Filter/sort trên UI phản ánh đúng vào file
- [ ] Xuất toàn bộ kết quả, không chỉ trang hiện tại
- [ ] User không có quyền → 403

## Risk Assessment

| Rủi ro | Giảm thiểu |
|---|---|
| Export phình bộ nhớ | 234 SV / ~2000 record — không đáng lo. Nếu sau này vượt vài nghìn SV thì chuyển sang queue export, ghi chú lại nhưng chưa làm |
| Chuỗi mã môn quá dài trong 1 ô | Max 13 môn → dưới giới hạn ô Excel rất xa |
| Logic filter lệch giữa index và export | Cùng một FormRequest, cùng một contract — không nhân bản logic filter |
