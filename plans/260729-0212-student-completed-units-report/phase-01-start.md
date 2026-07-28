---
phase: 1
title: "Query and contract"
status: pending
priority: P1
effort: "0.5d"
dependencies: []
---

# Phase 1: Query and contract

## Overview

Query object đọc `academic_records` + `units`, trả về mỗi sinh viên kèm danh sách môn đã đạt tách theo `unit_type`, số môn và tổng tín chỉ. Toàn bộ logic lọc và dedupe nằm ở đây; controller và export chỉ tiêu thụ payload.

## Requirements

Functional:
- Trả paginator của `Student` với payload môn đã đạt cho từng sinh viên.
- Chỉ lấy `academic_records.is_passed = 1`. Loại `is_passed = 0` và `is_passed IS NULL`.
- Dedupe theo `(student_id, unit_id)`: giữ `attempt_number` cao nhất, tie → `id` cao nhất. Không lộ `attempt_number` ra payload.
- Tách môn thành 2 nhóm: `gc` (`units.unit_type = 'egc'`) và `major` (`units.unit_type = 'general'`).
- Mỗi môn trả `code`, `name`, `credit_points_earned`.
- Tổng số môn và tổng tín chỉ đạt tính **sau** dedupe.
- Sinh viên không có môn đạt nào vẫn xuất hiện với 2 mảng rỗng và số 0.
- Hỗ trợ filter: `campus_id`, `program_id`, `keyword` (mã SV hoặc họ tên), `per_page`.
- Hỗ trợ sort: `credits_earned`, `units_count`, `full_name`, `student_id` — mỗi cái asc/desc.
- Chế độ export: trả toàn bộ collection thay vì paginate.

Non-functional:
- Tối đa ~4 query cho 1 trang. Không N+1.
- Campus scope là bắt buộc, không optional.
- `declare(strict_types=1)`, khớp style các query object cùng thư mục.

## Architecture

**Contract** — `app/Shared/Contracts/Academic/StudentCompletedUnitsReader.php`

Theo đúng khuôn `AcademicReportReader` hiện có (`extends ReadSurfaceMetadata`, method `handle(array $filters): array`). Thêm `handleExport(array $filters): Collection` cho Phase 3 (xem `GetStudentStatusBySemesterQuery` đã tách `handle` / `handleExport` theo cách này).

**Implementation** — `app/Modules/Academic/Progression/Queries/Reporting/GetStudentCompletedUnitsQuery.php`

**Binding** — `AcademicServiceProvider::register()`, cạnh dòng bind `AcademicReportReader` (dòng ~115).

Luồng dữ liệu:

```
1. Student::query()
     ->where('campus_id', $campusId)
     ->when(program_id) ->when(keyword)
     ->with('program:id,name')
     ->orderBy(...)          // sort theo cột students khi có thể
     ->paginate($perPage)

2. academic_records
     ->whereIn('student_id', $idsOfPage)
     ->where('is_passed', 1)
     ->join('units', ...)
     ->select(student_id, unit_id, u.code, u.name, u.unit_type,
              credit_points_earned, attempt_number, ar.id)
     ->get()

3. Dedupe trong PHP:
     groupBy(student_id . '-' . unit_id)
       -> sortByDesc([attempt_number, id])->first()

4. Group theo student_id, rồi partition theo unit_type
     'egc'     -> gc[]
     'general' -> major[]
     (unit_type khác -> major, kèm comment)

5. units_count  = gc.count + major.count
   credits_earned = sum(credit_points_earned) trên tập đã dedupe
```

**Sort theo cột dẫn xuất.** `credits_earned` và `units_count` không nằm trên bảng `students`, nên không sort được ở bước 1 nếu tính ở bước 3. Xử lý: khi sort theo 2 cột này, dùng subquery aggregate ngay trên query students:

```sql
select students.*,
  (select coalesce(sum(x.credit_points_earned), 0)
     from (select ar.unit_id, max(ar.id) as id
             from academic_records ar
            where ar.student_id = students.id and ar.is_passed = 1
            group by ar.unit_id) d
     join academic_records x on x.id = d.id) as credits_earned
from students
```

Giữ subquery này **chỉ** khi sort yêu cầu. Sort mặc định (`student_id`) không cần → đường nhanh giữ nguyên.

**Payload shape:**

```php
[
  'data' => [
    [
      'id' => 12,
      'student_id' => 'SW21001',
      'full_name' => 'Nguyễn Văn A',
      'program' => 'BIT',              // nullable
      'gc' => [
        ['code' => 'EGCF', 'name' => '...', 'credits' => '3.00'],
      ],
      'major' => [
        ['code' => 'SE001', 'name' => '...', 'credits' => '3.00'],
      ],
      'units_count' => 9,
      'credits_earned' => '24.00',
    ],
  ],
  'meta' => [ /* paginator meta */ ],
]
```

Chip sort theo `code` asc trong từng nhóm.

## Related Code Files

- Create: `app/Shared/Contracts/Academic/StudentCompletedUnitsReader.php`
- Create: `app/Modules/Academic/Progression/Queries/Reporting/GetStudentCompletedUnitsQuery.php`
- Create: `tests/Feature/Academic/GetStudentCompletedUnitsQueryTest.php`
- Modify: `app/Modules/Academic/Providers/AcademicServiceProvider.php` (thêm import + bind)

Đọc tham khảo trước khi viết:
- `app/Shared/Contracts/Academic/AcademicReportReader.php` — khuôn contract
- `app/Modules/Academic/Progression/Queries/Reporting/GetAcademicReportQuery.php` — khuôn filter/paginate/export flag
- `app/Modules/Academic/Progression/Queries/Reporting/GetStudentStatusBySemesterQuery.php` — khuôn tách `handle` / `handleExport`

## Implementation Steps

1. Viết test trước (`tests/Feature/Academic/GetStudentCompletedUnitsQueryTest.php`), phủ:
   - `is_passed = 0` không xuất hiện trong `gc`/`major`/`units_count`/`credits_earned`
   - `is_passed IS NULL` cũng không xuất hiện
   - Cùng `(student, unit)` 2 record pass → 1 chip, tín chỉ cộng 1 lần
   - `unit_type = 'egc'` vào `gc`, `general` vào `major`
   - Sinh viên không có môn pass → row vẫn có, mảng rỗng, count 0, credits 0
   - Sinh viên campus khác không lọt vào kết quả
   - Filter `program_id` và `keyword` (khớp cả `student_id` lẫn `full_name`)
   - Sort `credits_earned` desc trả đúng thứ tự
2. Tạo contract `StudentCompletedUnitsReader` với `handle()` + `handleExport()`.
3. Viết `GetStudentCompletedUnitsQuery` theo luồng ở trên.
4. Bind contract → implementation trong `AcademicServiceProvider`.
5. Chạy test file; đếm query bằng `DB::listen` trong 1 test để chốt không N+1.

## Success Criteria

- [ ] Test file xanh toàn bộ
- [ ] Một trang 25 sinh viên tiêu tốn ≤ 4 query
- [ ] Payload không chứa `attempt_number` hay bất kỳ dấu vết attempt nào
- [ ] Contract được bind, resolve qua container chạy được

## Risk Assessment

| Rủi ro | Giảm thiểu |
|---|---|
| Dedupe sai → tín chỉ cộng đôi | Test dựng thẳng ca 2 record pass cùng unit. DB thật có 4 cặp, dùng làm sanity check thủ công sau khi code xong |
| `unit_type` xuất hiện giá trị thứ ba trong tương lai | Nhóm mặc định là `major` (`!= 'egc'`), kèm comment nêu rõ quy ước. Không hardcode `== 'general'` |
| Subquery sort chậm khi dữ liệu lớn | Hiện 234 SV / 2939 record — không đáng lo. Chỉ gắn subquery khi sort yêu cầu |
| Test chạy nhầm DB `asia` | Repo không có `.env.testing`; **không** chạy artisan kèm `--env=testing`. Dùng đúng lệnh test hiện hành của repo |
| Chạy cả `tests/Feature/Academic` bị abort (lỗi có sẵn ở `GetStudentAttendanceQueryTest`) | Chạy riêng file test mới, không chạy cả thư mục |
