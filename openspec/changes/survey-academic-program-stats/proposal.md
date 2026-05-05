## Why

Admin staff cần xem tổng quan thống kê kết quả survey theo từng ngành học (program) để đánh giá chất lượng phản hồi môn học. Hiện tại chỉ có trang list (per survey run) và aggregate (per run detail) — không có view tổng hợp cross-program.

## What Changes

Tạo trang mới `/forms/admin/results/stats` hiển thị 3 chỉ số survey theo từng `programs.code`:

1. **Submissions** — tổng sinh viên đã bấm gửi (có `response_id`)
2. **KQ TBC ≥ 4** — số response mà điểm trung bình toàn bộ rating questions ≥ 4
3. **% ≥ 4** — tỉ lệ KQ TBC ≥ 4 trên tổng submissions

Filter: All semesters hoặc chọn 1 semester cụ thể.

Thêm menu item "Program Stats" bên dưới "Survey Results" trong sidebar Surveys.

## Capabilities

### New Capabilities

- `survey-program-stats`: Trang thống kê survey theo program — query, controller action, Inertia page, sidebar entry.

### Modified Capabilities

- (none)

## Impact

- **New**: `app/Queries/Form/GetSurveyProgramStatsQuery.php`
- **New**: `resources/js/pages/Forms/Admin/results/Stats.vue`
- **Modified**: `app/Http/Controllers/Web/Admin/SurveyResultController.php` — thêm method `stats()`
- **Modified**: `routes/web/forms.php` — thêm route `GET /forms/admin/results/stats`
- **Modified**: `resources/js/constants/menu-sidebar.ts` — thêm menu item
