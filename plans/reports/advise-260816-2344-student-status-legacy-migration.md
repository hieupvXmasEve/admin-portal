# Advise: bỏ đọc `students.status` cũ → status từ `program_enrollments`

**TL;DR:** Cột `students.status` chết-khi-ghi (chỉ ghi `active`/`inactive` lúc tạo student, không lifecycle nào sync lại). Cần đổi **1 chỗ gốc + ~10 file lẻ**, còn lại giữ nguyên. 5 gate đăng ký bên Delivery tự đúng sau khi sửa chỗ gốc — không cần sửa từng gate.

## Cần đổi gì (inventory)

### (a) Chỗ gốc — sửa 1 file, ~20 consumer đúng theo

| File | Hiện tại | Đổi thành |
|---|---|---|
| `app/Modules/StudentRegistry/Support/EloquentStudentRegistryStore.php` — `reference()` L286/289, `joinedReference()` L313/316 | `StudentReference.status` = `students.status`; `statusLabel` = accessor cũ | Resolve qua `StudentLifecycleStatusReader::statusesFor()` (batch cho `joinedReference`); label qua `Student::statusLabelFor()`. Reader đã tự fallback cột cũ khi chưa có primary enrollment — không cần code fallback thêm |

Ăn theo tự động (KHÔNG sửa file): 5 gate Delivery (`CheckCourseRegistrationEligibilityQuery`, `RegisterStudentForActiveSemesterUnitsAction`, `CreateRetakeCourseRegistrationAction`, `BulkRegisterCourseOfferingStudentsAction`, `SearchCourseOfferingStudentsAction`), `GetStudentStatusBySemesterQuery` (pending/admission_deferred chưa có enrollment → fallback giữ nguyên hành vi), và mọi consumer display của `StudentReference`.

### (b) Reader lẻ còn lại — ~10 file

| File | Hiện tại | Đổi thành |
|---|---|---|
| `Finance/.../LifecycleDueItemPredicate` L15/24 | `$student->status` vs `FINANCIAL_STATUSES` (PHP check) | Nhận status đã resolve từ `StudentLifecycleStatusReader` (SQL scope L78/100 là nhánh fallback — GIỮ) |
| `Finance/.../GetLifecycleDueExceptionSummaryQuery` L37-44 | `whereIn(students.status, ...)` | Copy SQL scope enrollment-first đã migrate trong `LifecycleDueItemPredicate` (DRY: extract scope dùng chung nếu lặp lần 3) |
| `Finance/.../ListLifecycleDueExceptionsQuery` L36/152/156 | eager-load + `whereIn(students.status, ...)` | Như trên |
| `Finance/.../EloquentStudentCollectionEligibilityReader` L40 | `whereIn(students.status, ...)` | XÁC MINH trước: nếu đã là nhánh fallback enrollment-first thì giữ; nếu không, chuyển như trên |
| `app/Models/CourseRegistration` L154-155 | `CLASS_ROSTER_INACTIVE_STATUSES` vs `students.status` → roster status | Resolve status qua reader tại call-site (batch), truyền vào; value-set constant giữ nguyên |
| `DashboardChartsService` L58-59/267-268, `DashboardStatsService` L69-71 | `COUNT CASE` / `groupBy` trên `students.status` | `LEFT JOIN program_enrollments (is_primary=1)` + `CASE`: null→`students.status`; active→`COALESCE(study_stage,'active')`; withdrawn→`'dropout'`; else pass-through — cùng mapping với reader |
| Display đọc thẳng model: `Api/StudentController` L133, `Api/AuthController` L107/217, `EventParticipantResource` L47, `LecturerAttendanceService` L110, `PreviewStudentDecisionBulkLinkQuery` L58, `EloquentStudentPortalProfileReader` L99, `EloquentStudentImpersonationTokenIssuer` L22/33, `AiAcademicEntitySearchReader` L55, `AiAcademicStudentProfileReader` L79 | `$student->status` trực tiếp | Resolve qua `StudentLifecycleStatusReader` (batch cho danh sách). Self-registered `inactive` chưa có enrollment → fallback giữ nguyên hành vi auth |

### (c) Giữ nguyên CÓ CHỦ ĐÍCH (không đụng)

- Fallback trong `StudentLifecycleStatusReader` L32 — chính là cơ chế fallback đã chốt.
- Intake-stage seed: `EloquentProgramEnrollmentReader` L178, `MaterializeProgramEnrollmentAction` L99 — đọc cột cũ để bootstrap enrollment, đúng vai.
- Writers create-time (`'active'`/`'inactive'`): AuthController tự đăng ký, RegisterAdmittedStudentAction, StudentService, StudentSeeder.
- Surface đã dùng rich reader: `ListStudentsQuery`, registry `StudentController`, `ListFeeMonitorQuery`, `ListCollectionProgressQuery`, `ListDngLifecycleQuery`, `StudentExport`.
- Đã migrate phiên này: `ListDueItemsQuery`, `AcademicFinanceChargeSourceGateway`, các mapper lifecycle-due.
- 288 file test + `StudentFactory` + seeders + `StudentStatusLabelTest` — không đổi (test student không có enrollment → fallback cho kết quả cũ).
- Constants value-set trên `Student` (`FINANCIAL_STATUSES`, `BLOCKED_STATUSES`, `CLASS_ROSTER_INACTIVE_STATUSES`) — giữ làm allow-list, chỉ đổi NGUỒN status đem so.

## Verdict & trade-off (đã chốt qua interview)

Đúng hướng, phạm vi hợp lý: sửa nguồn 1 chỗ thay vì vá 20 consumer; giữ fallback nên không cần backfill/đổi schema/sửa test. Trade-off user đã chấp nhận: (1) 11 student drift (cột cũ `deferred`, enrollment `active`) được đăng ký lại — hành vi đúng nhưng là thay đổi thật trên prod; (2) UI mất phân biệt `dropout_transfer` vs `dropout` ở surface dùng cheap reader (6 enrollment withdrawn hiện tại). Đừng làm: drop cột, backfill enrollment, thêm field DTO thứ hai, sync ngược cột cũ — đều là scope creep so với mục tiêu.

## Work checklist

- [ ] Flip `EloquentStudentRegistryStore::reference()/joinedReference()` sang `StudentLifecycleStatusReader` + `Student::statusLabelFor()`; chạy suite Delivery registration/retake/roster + StudentRegistry
- [ ] `LifecycleDueItemPredicate` L15/24: nhận status resolve sẵn (giữ SQL fallback L78/100)
- [ ] `GetLifecycleDueExceptionSummaryQuery` + `ListLifecycleDueExceptionsQuery`: SQL scope enrollment-first (tái dùng scope đã migrate)
- [ ] Xác minh `EloquentStudentCollectionEligibilityReader` L40 — chuyển nếu chưa phải nhánh fallback
- [ ] `CourseRegistration` L154-155: roster status từ status resolve tại call-site
- [ ] `DashboardChartsService` + `DashboardStatsService`: JOIN primary enrollment + CASE mapping
- [ ] Display trực tiếp model (9 chỗ nhóm b, hàng cuối): resolve qua reader, batch cho list
- [ ] Guard/arch test: cấm đọc `students.status` mới ngoài whitelist (reader fallback, 2 intake seed, writers create-time) — hàng rào duy nhất vì repo không có CI
- [ ] Tinker drift probe trước/sau trên dev DB: so status cũ vs resolve mới cho toàn bộ student có primary enrollment, xuất diff
- [ ] Chạy suite theo file (tránh run lớn flaky): Delivery, StudentRegistry, Finance lifecycle-due/reporting, 2 test mới phiên trước

## Success metrics

- `./scripts/dev.sh artisan test tests/Feature/Academic/Delivery/... tests/Feature/StudentRegistry/... tests/Feature/Finance/Operations/DueCalendarLifecycleStatusTest.php tests/Feature/Finance/Reporting/LifecycleStatusLabelConsistencyTest.php` (chạy theo từng path): 0 failure mới so với baseline (Finance baseline có 1-2 fail sẵn)
- Guard test xanh, và grep `rg -n "students\.status|whereIn\('status'" app/` chỉ còn hit nằm trong whitelist (nhánh fallback + 2 intake seed + writers)
- Tinker probe (`./scripts/dev.sh artisan tinker --execute=...` + json_encode): 100% student CÓ primary enrollment trả status = mapping enrollment (11 row drift `deferred`→`active` hiện diện trong diff, đúng kỳ vọng); 100% student KHÔNG có enrollment trả status = cột cũ (fallback nguyên vẹn)
- Dashboard counts: tổng số student mỗi status từ SQL mới khớp tinker probe theo reader (so bằng số, cùng dev DB)
- Số nơi đọc `students.status` ngoài whitelist: 0 (đo bằng guard test, không phải vibe)

## Unresolved questions

1. `EloquentStudentCollectionEligibilityReader` L40 đã enrollment-first hay chưa — xác minh khi implement (checklist đã ghi).
2. `Api/AuthController`/`ImpersonationTokenIssuer` check `inactive` là account-state hay lifecycle? Fallback giữ hành vi hiện tại cho student tự đăng ký (chưa có enrollment), nhưng nếu sau này có student `inactive` account + enrollment active thì cần tách account-state khỏi lifecycle-status.
3. 1 enrollment active có `study_stage=NULL` trên dev DB → reader trả `active`; xác nhận đó là data đúng hay thiếu backfill study_stage.
