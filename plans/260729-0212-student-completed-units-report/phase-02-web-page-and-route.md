---
phase: 2
title: "Web page and route"
status: pending
priority: P1
effort: "0.5-1d"
dependencies: [1]
---

# Phase 2: Web page and route

## Overview

Controller Inertia + route + trang Vue hiển thị bảng sinh viên với chip mã môn tách 2 cột GC / Major, kèm filter và sort.

## Requirements

Functional:
- Route `GET /academic/reports/student-units`, tên `academic.reports.student-units.index`, gate `can:view_academic_report`.
- Filter bar: Program (select), Campus (select), Keyword (debounced input). Filter đẩy lên URL query string để share link được.
- Bảng: Mã SV · Họ tên · Ngành · Môn GC · Môn Major · Số môn · TC đạt.
- Chip = mã môn, tooltip hiện tên đầy đủ. Không badge attempt, không màu phân biệt pass/fail (mọi chip đều là pass).
- Cột sort được: Số môn, TC đạt, Họ tên, Mã SV.
- Phân trang giữ nguyên filter và sort.
- Sinh viên không có môn nào → 2 cell chip hiện placeholder mờ ("—"), không để trống bối rối.

Non-functional:
- Campus mặc định lấy từ `session('current_campus_id')`.
- Không tạo component dùng chung mới nếu bảng/select/pagination sẵn có đã đủ.

## Architecture

Theo đúng khuôn `AcademicReportController` hiện có: FormRequest validate filter → gọi contract → `Inertia::render` với `report` + `filters.active` + `filters.options`.

```
routes/web.php  (group prefix 'academic', name 'academic.')
  Route::prefix('reports/student-units')->name('reports.student-units.')
    -> GET '/'  StudentCompletedUnitsController@index   can:view_academic_report
```

Đặt trong group `Route::middleware(['auth','verified'])->prefix('academic')->name('academic.')`, cạnh các block `reports/*` đã có (dòng ~430-512).

Props gửi xuống Vue:

```php
Inertia::render('Academic/Report/StudentUnits/Index', [
    'report'  => $report,            // data + meta từ Phase 1
    'filters' => [
        'active'  => ['campus_id','program_id','keyword','sort','direction','per_page'],
        'options' => ['campuses' => [...], 'programs' => [...]],
    ],
]);
```

Dùng lại `CampusReferenceReader` và `ProgramReferenceReader` như controller cũ đang làm.

Cấu trúc trang Vue: bảng bọc trong `overflow-x: auto` để 2 cột chip không đẩy tràn ngang trên mobile. Cột trái (Mã SV, Họ tên) sticky khi cuộn ngang.

## Related Code Files

- Create: `app/Modules/Academic/Http/Web/StudentCompletedUnitsController.php`
- Create: `app/Modules/Academic/Http/Requests/ListStudentCompletedUnitsRequest.php`
- Create: `resources/js/pages/Academic/Report/StudentUnits/Index.vue`
- Modify: `app/Modules/Academic/routes/web.php`
- Create: `tests/Feature/Academic/StudentCompletedUnitsPageTest.php`

Đọc tham khảo:
- `app/Modules/Academic/Http/Web/AcademicReportController.php` — khuôn controller
- `app/Modules/Academic/Http/Requests/ListAcademicReportRequest.php` — khuôn FormRequest
- `resources/js/pages/Academic/Report/Index.vue` — khuôn filter bar + bảng
- `resources/js/pages/CourseRegistrations/Index.vue` — khuôn `DebouncedInput` + đẩy filter lên URL

## Implementation Steps

1. FormRequest: validate `campus_id`, `program_id` (nullable integer, exists), `keyword` (nullable string max 255), `sort` (in: student_id, full_name, units_count, credits_earned), `direction` (in: asc, desc), `per_page` (integer min 10 max 100).
2. Controller `index`: merge default (`campus_id` từ session, `per_page` 25, `sort` = student_id, `direction` = asc), gọi `StudentCompletedUnitsReader`, render Inertia kèm options.
3. Đăng ký route trong group `academic.`.
4. Vue page: filter bar → bảng → chip cell → pagination. Chip cell tách thành component con trong cùng thư mục trang nếu markup vượt ~40 dòng.
5. Feature test: gọi route với user có quyền, khẳng định Inertia component đúng và prop `report.data` có shape mong đợi; gọi bằng user không quyền → 403.

## Success Criteria

- [ ] Route trả 200 cho user có `view_academic_report`, 403 cho user không có
- [ ] Bảng hiện đúng dữ liệu 1 row/sinh viên, 2 cột chip tách đúng
- [ ] Filter và sort phản ánh vào URL, reload giữ nguyên trạng thái
- [ ] Không có horizontal scroll ở cấp body trang (chỉ trong container bảng)
- [ ] `eslint` sạch trên file Vue mới

## Risk Assessment

| Rủi ro | Giảm thiểu |
|---|---|
| Cell chip dài làm vỡ layout | Max 13 môn/SV theo dữ liệu thật → chấp nhận được. Bọc `flex-wrap`, container bảng `overflow-x: auto` |
| Filter campus đá nhau với campus session | Campus select chỉ đổi trong phạm vi campus user được phép; controller vẫn ép scope, không tin giá trị client gửi lên |
| Type-check toàn dự án OOM trong container dev | Lint theo từng file thay vì chạy `type-check` cả dự án |
| CSRF trong feature test | Test POST/PUT cần `_token`; ở đây chỉ có GET nên không vướng |
