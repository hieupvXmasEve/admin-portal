## 1. Backend — Query class

- [x] 1.1 Tạo folder `app/Queries/Form/`
- [x] 1.2 Tạo `app/Queries/Form/GetSurveyProgramStatsQuery.php` với method `handle(array $filters): array`
  - Nhận `filters['semester_id']` (nullable / 'all')
  - Lấy `campus_id` từ `session('current_campus_id')` để scope
  - Thực thi raw SQL aggregate (subquery avg per response, outer GROUP BY program)
  - Trả về `['rows' => [...], 'totals' => [...]]`
  - `rows` shape: `[program_code, program_name, submissions, high_rated_count, percent]`
  - `totals` shape: `[submissions, high_rated_count, percent]`

## 2. Backend — Controller & Route

- [x] 2.1 Thêm method `stats(Request $request, GetSurveyProgramStatsQuery $query): Response` vào `SurveyResultController`
  - Validate `semester_id` nullable string
  - Authorize `view_survey_results_aggregate`
  - Trả về `Inertia::render('Forms/Admin/results/Stats', [stats, semesters, filters])`
- [x] 2.2 Thêm route trong `routes/web/forms.php` trong prefix `results`:
  ```php
  Route::get('/stats', [SurveyResultController::class, 'stats'])
      ->name('stats')
      ->middleware('can:view_survey_results_aggregate');
  ```

## 3. Frontend — Stats.vue page

- [x] 3.1 Tạo `resources/js/pages/Forms/Admin/results/Stats.vue`
  - `<script setup lang="ts">` — props: `stats`, `semesters`, `filters`
  - Semester filter dropdown + submit (GET request via `router.visit`)
  - Table: columns Program / Submissions / KQ TBC ≥ 4 / %
  - TOTAL row ở cuối với styling khác (font-semibold, border-top)
  - Empty state nếu `stats.rows` rỗng
  - `<Head title="Survey Program Stats" />`

## 4. Frontend — Sidebar

- [x] 4.1 Thêm menu item vào `resources/js/constants/menu-sidebar.ts` trong children của "Surveys":
  ```ts
  {
      title: 'Program Stats',
      href: '/forms/admin/results/stats',
      icon: BarChart3,
      requiredPermissions: ['view_survey_results_aggregate'],
  },
  ```
  — đặt ngay bên dưới "Survey Results"
