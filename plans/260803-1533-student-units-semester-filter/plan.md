# Student Completed Units — semester filter, status column, per-semester export

Status: done (uncommitted on dev)
Branch: dev

Verification: 32/32 tests pass across the 3 report suites; eslint clean on `Index.vue`; Pint clean. Post-review fixes applied: soft-deleted `academic_records` now excluded from the derived-sort SQL, `units_by_semester` dropped from the index payload, archived `semester_id` rejected at validation.

## Outcome

`/academic/reports/student-units` gains:
1. Semester filter — khi chọn 1 kì, mọi cột unit/count/credit chỉ tính môn **đăng ký** trong kì đó; SV nhập học sau → cột trống.
2. Cột Status — academic lifecycle status hiện tại của SV.
3. Export theo filter; khi filter = All thì tách mỗi kì thành 1 cột Major Units riêng.

## Decisions (user-confirmed)

- **Nguồn dữ liệu**: liệt kê **môn đã đăng ký**, không lọc `is_passed`. Vẫn dùng `academic_records` (2940 rows, cover ~1:1 với `course_registrations` 2939, 1 orphan) — không đổi bảng, chỉ bỏ điều kiện `is_passed = 1`.
- **Retake**: hiện ở **mọi kì đã đăng ký** (bỏ dedupe theo attempt). Filter All → mỗi mã môn chỉ hiện 1 chip (unique theo `unit_id`).
- **Cột số liệu**: `Units` = số môn đăng ký (đúng bằng số chip hiển thị trong scope). `Credits Earned` vẫn chỉ cộng `credit_points_earned` của record `is_passed = 1`. `Credits Required` giữ nguyên (toàn chương trình).
- **Chip**: đồng nhất, không phân biệt pass/fail/in_progress.
- **Scope filter kì**: GC Units, Major Units, Units, Credits Earned đều scope theo kì.
- **Status source**: `StudentLifecycleStatusReader::statusesFor()` → `program_enrollments` (is_primary, enrollment_status+study_stage), fallback `students.status`. FE dùng lại `getStudentStatusLabel` / `getStudentStatusBadgeClass` từ `resources/js/types/student.ts`.
- **Export All**: giữ cột cố định (Mã SV, Họ tên, Ngành, **Status**, Môn GC, Số môn, TC đạt, TC toàn CT) + thay cột "Môn Major" gộp bằng 1 cột Major cho mỗi kì.

## Non-goals

- Không đổi phân trang / sort contract.
- Không đụng `academic_standings` (bảng 0 rows) hay `students.academic_status` (100% 'active').
- Không thêm filter kì cho GC/Major riêng biệt.

## Acceptance criteria

- [ ] Chọn kì → chỉ môn **đăng ký** thuộc kì đó hiện ở GC/Major; SV không có môn kì đó → 2 cột hiện `—`, Units=0, TC đạt=0.
- [ ] Môn chưa pass / đang học vẫn xuất hiện trong chip (bao gồm 199 record failed + 888 in_progress hiện có).
- [ ] Môn retake đăng ký 2 kì → hiện ở cột của **cả hai** kì trong export All; filter All (view) chỉ hiện 1 chip.
- [ ] `Credits Earned` chỉ cộng record `is_passed = 1` trong scope, không cộng môn đang học.
- [ ] Sort `units_count` / `credits_earned` tôn trọng filter kì, phân trang không lặp/rớt row.
- [ ] Cột Status hiển thị đúng label + badge cho cả 6 giá trị thực (intake_course, intake_pre_uni_gc, pending_course_opening, deferred, intake_major, dropout).
- [ ] Export khi chọn kì: 9 cột, dữ liệu = đúng kì, header ghi rõ kì.
- [ ] Export khi All: 8 cột cố định + N cột `Major <mã kì>`, mỗi ô là code các môn Major passed trong kì đó.
- [ ] `campus_id` vẫn chỉ lấy từ session, không nhận từ query string.

## Files (exact paths)

| Layer | Path | Change |
|---|---|---|
| FormRequest | `app/Modules/Academic/Http/Requests/ListStudentCompletedUnitsRequest.php` | thêm rule `semester_id` |
| Query | `app/Modules/Academic/Progression/Queries/Reporting/GetStudentCompletedUnitsQuery.php` | semester scope, status, per-semester payload |
| Contract | `app/Shared/Contracts/Academic/StudentCompletedUnitsReader.php` | không đổi signature (filters array) |
| Controller | `app/Modules/Academic/Http/Web/StudentCompletedUnitsController.php` | semester options + active filter + export label |
| Export | `app/Exports/StudentCompletedUnitsExport.php` | cột động |
| Page | `resources/js/pages/Academic/Report/StudentUnits/Index.vue` | select kì, cột Status |
| Route | `app/Modules/Academic/routes/web.php` | không đổi |
| Test | `tests/Feature/Academic/StudentCompletedUnitsReportTest.php` | mới/mở rộng |

## Phases

### P1 — Backend query (semester scope + status)

1. `ListStudentCompletedUnitsRequest`: `'semester_id' => ['nullable','integer','exists:semesters,id']`.
2. `GetStudentCompletedUnitsQuery`:
   - `passedRecords()` → đổi tên `registeredRecords()`: **bỏ** `where('is_passed', true)`, select thêm `academic_records.semester_id` + `academic_records.is_passed`, thêm `when(semester_id)` → `where('academic_records.semester_id', $id)`.
   - `dedupe()` → thay bằng `uniqueByUnit()`: chỉ unique theo `unit_id` (giữ record có attempt cao nhất để `credits_earned` không đếm trùng). Khi scope kì, unique chạy trong phạm vi kì → retake ở kì khác vẫn xuất hiện ở kì của nó.
   - `units_count` = số chip trong scope (GC + Major).
   - `credits_earned` = sum `credit_points_earned` của record `is_passed = 1` trong scope.
   - `applyDerivedSort()`: bỏ `is_passed = 1` khỏi nhánh `units_count` (đếm môn đăng ký), **giữ** ở nhánh `credits_earned`. Khi có `semester_id`, thêm `and ar.semester_id = {id}` vào **cả** correlated latest-attempt subquery lẫn outer aggregate — cast `(int)`, không nội suy raw từ input.
   - Inject `StudentLifecycleStatusReader` qua constructor; `attachCompletedUnits()` gọi `statusesFor($studentIds)` 1 lần/page, thêm key `status` vào row payload.
3. Không đổi shape payload hiện có (thêm key, không bỏ) → FE cũ vẫn chạy.

### P2 — Export All: cột theo kì

1. `attachCompletedUnits()` khi **không** filter kì → thêm key `major_by_semester: array<semesterId, list<unitPayload>>` (chỉ dùng cho export; index vẫn bỏ qua).
2. Query trả kèm danh sách kì xuất hiện trong result set (id, code, start_date) để export dựng header, sort `start_date asc`.
   - Chỉ kì có mặt trong data → tránh sinh hàng chục cột rỗng.
3. `StudentCompletedUnitsExport`:
   - Cột cố định: `['Mã SV','Họ tên','Ngành','Trạng thái','Môn GC','Số môn','TC đạt','TC toàn CT']`.
   - Nếu có filter kì → chèn `'Môn Major'` sau `'Môn GC'` (9 cột).
   - Nếu All → append `'Major <code>'` cho mỗi kì sau cột cố định.
   - `lastColumn` tính động thay vì hardcode `'H'`.
   - `filterLabels()` thêm `Semester`.

### P3 — Controller + FE

1. Controller: inject `GetSemesterFilterOptionsQuery`, đẩy `filters.options.semesters` + `filters.active.semester_id`; export label thêm tên kì.
2. `Index.vue`:
   - Select kì (`all` + list), `immediateFields: ['semester_id']`, default `''`.
   - Cột `Status` sau `Program`, render Badge dùng `getStudentStatusLabel` + `getStudentStatusBadgeClass`.
   - Subtitle đổi theo filter kì.
   - `exportUrl` tự động mang `semester_id` (đã dùng `Object.entries(filters)`).

### P4 — Tests + verify

- Feature test: filter kì scope đúng; SV nhập học sau → cột rỗng; sort derived + semester filter; status từ program_enrollments; export All có cột mỗi kì; campus vẫn từ session.
- `./scripts/dev.sh artisan test --filter=StudentCompletedUnits`
- eslint per-file cho `Index.vue` (whole-project vue-tsc OOM trong container).

## Risks

- **Derived sort + semester filter**: subquery phải scope cùng điều kiện, nếu lệch → thứ tự sai, pagination lặp row. Test riêng.
- **Export All rộng**: nhiều kì → nhiều cột; giới hạn theo kì có mặt trong data.
- **Retake qua 2 kì**: hiện ở cột của cả hai kì → tổng cột từng kì > `Units` (unique). Có chủ đích, ghi chú trong header export.
- **Đổi ngữ nghĩa trang**: "Completed Units" giờ là "Registered Units". Đổi tiêu đề + subtitle + tiêu đề sheet export cho khớp; tên class/route giữ nguyên để tránh vỡ contract.

## Rollback

Revert commit; không có migration, không đổi schema, không đổi public contract signature.
