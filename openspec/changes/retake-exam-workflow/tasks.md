## 1. Backbone — Database & Models

- [ ] 1.1 Tạo migration `create_retake_batches_table` (campus_id, unit_id, semester_id, status enum, registration_window_start/end, batch_close_date, course_offering_id nullable, opened/confirmed by/at)
- [ ] 1.2 Tạo migration `create_course_retake_cases_table` (đầy đủ fields theo design: student, unit, semester, campus, batch FK, status enum, tất cả *_at và *_by_user_id fields, charge_id, payment_deadline, fallback fields)
- [ ] 1.3 Tạo migration `add_is_retake_and_retake_batch_id_to_course_offerings_table`
- [ ] 1.4 Tạo migration `add_is_gpa_contributing_to_academic_records_table` (boolean, default true)
- [ ] 1.5 Tạo Model `RetakeBatch` với fillable, casts, relationships (hasMany RetakeCases, belongsTo CourseOffering)
- [ ] 1.6 Tạo Model `RetakeCase` với fillable, casts, relationships (belongsTo Student/Unit/Semester/RetakeBatch/FinanceCharge/AcademicRecord)
- [ ] 1.7 Thêm state machine methods vào `RetakeCase` (transition methods với validation, guard invalid transitions) — dùng canonical state list từ design.md D2
- [ ] 1.8 Tạo `config/retake.php` với `max_attempts` (default 2) và `min_attendance_percentage` (default 70.0)

## 2. Eligibility Engine

- [ ] 2.1 Tạo `app/Services/RetakeEligibilityEngine.php` — check attendance vs config, check attempt quota (chỉ tính final_pass/final_fail/no_show), return `EligibilityResult` (eligible bool + reason)
- [ ] 2.2 Viết unit tests cho `RetakeEligibilityEngine` (đủ/thiếu điểm danh, đủ/vượt quota, case đang mở không tính quota)

## 3. Observer & Auto-close

- [ ] 3.1 Tạo `app/Observers/AcademicRecordObserver` — observe `updated`, check `override_pass` changed to true
- [ ] 3.2 Implement `RetakeCaseAutoCloseAction` — void charge nếu chưa paid, set case `auto_closed` với reason_code
- [ ] 3.3 Register observer trong `AppServiceProvider`

## 4. B1 — Lecturer Nomination

- [ ] 4.1 Tạo `NominateRetakeCaseAction` (status = `training_eligibility_pending`) và `NotNominateRetakeCaseAction` (status = `not_nominated`, terminal) — cả hai đều tạo RetakeCase để audit; `NominateRetakeCaseAction` đề xuất RetakeBatch nếu chưa có
- [ ] 4.2 Tạo method mới trong `FailedStudentsService` (hoặc query riêng) scope theo `course_offering_id` cụ thể — KHÔNG dùng campus-wide query hiện tại; thêm eligibility data (eligible bool + reason) vào response
- [ ] 4.3 Tạo route + `RetakeNominationController` (GET list by `course_offering_id`, POST nominate, POST not-nominate); middleware kiểm tra GV là `lecture_id` của offering đó (403 nếu không phải)
- [ ] 4.4 Tạo Vue page `RetakeNomination/Index.vue` — hiển thị danh sách fail + eligibility status + form nominate với reason
- [ ] 4.5 Thêm link "Xem danh sách thi lại" vào completion success response/UI — GV tự navigate sang màn nomination (query-on-demand); KHÔNG sửa `CourseCompletionService::finalizeCourse()`

## 5. B2 — Training Approval

- [ ] 5.1 Tạo `ApproveRetakeEligibilityAction` và `RejectRetakeNominationAction`
- [ ] 5.2 Tạo `OpenRetakeBatchRegistrationAction` — validate có case approved, nhận registration_window_start/end + batch_close_date, transition batch → open, transition tất cả approved cases → registration_opened
- [ ] 5.3 Tạo route + `RetakeBatchController` (index, show, open-registration)
- [ ] 5.4 Tạo route + `RetakeCaseApprovalController` (approve, reject)
- [ ] 5.5 Tạo Vue page `RetakeBatch/Index.vue` — danh sách batches theo status + filter
- [ ] 5.6 Tạo Vue page `RetakeCase/PendingApproval.vue` — danh sách cases pending eligibility + approve/reject actions
- [ ] 5.7 Gửi notification tới sinh viên khi case chuyển sang `registration_opened`

## 6. B3 — Student Registration

- [ ] 6.1 Tạo Student Portal API route `GET /api/v1/student/retake-cases` — danh sách cases + status + charge info
- [ ] 6.2 Tạo Student Portal API route `POST /api/v1/student/retake-cases/{id}/register` — đăng ký, validate window, transition case
- [ ] 6.3 Tạo `StudentRetakeController` với actor middleware (student only)
- [ ] 6.4 Cập nhật Student Portal frontend — hiển thị CTA đăng ký, trạng thái, charge/payment info

## 7. B3 — Finance Flow

- [ ] 7.1 Tạo `CreateRetakeChargeAction` — wrap `CreateFinanceChargeAction`, validate registration window, validate payment_deadline ≤ batch_close_date, prefill từ `unit.retake_fee`
- [ ] 7.2 Tạo route + `RetakeChargeController` (index pending review, store charge)
- [ ] 7.3 Tạo Vue page `RetakeCase/FinanceReview.vue` — danh sách cases `finance_review_pending` + form tạo charge
- [ ] 7.4 Hook DNG payment webhook — khi payment confirmed cho retake charge, transition case `payment_pending` → `paid` → `waiting_listed`
- [ ] 7.5 Gửi notification tới sinh viên khi charge được tạo (include amount + deadline)
- [ ] 7.6 Gửi notification tới sinh viên khi sắp hết payment deadline (T-3 days)

## 8. Auto-transition Daily Job

- [ ] 8.1 Tạo `app/Console/Commands/TransitionOverdueRetakeCasesCommand` — xử lý 3 loại overdue: registration window hết/SV chưa đăng ký, registration window hết/chưa có charge, payment deadline hết/chưa paid
- [ ] 8.2 Đăng ký command trong `routes/console.php` (không phải Kernel.php — repo này schedule tại đây) — daily 1:00 AM với `withoutOverlapping()->onOneServer()`
- [ ] 8.3 Thêm nut "Chuyển Học lại" thủ công cho staff với form reason_code + comment

## 9. B4 — Scheduling

- [ ] 9.1 Tạo `CreateRetakeCourseOfferingAction` — tạo CourseOffering (`is_retake=true`, `retake_batch_id`), **bắt buộc tạo `course_registrations` cho tất cả sinh viên có case `roster_confirmed`** (CanvasGradeSyncService đọc từ đây), cập nhật batch.course_offering_id, transition cases → `scheduled`
- [ ] 9.2 Tạo `ConfirmRetakeRosterAction` — xác nhận danh sách từ `waiting_listed`, transition sang `roster_confirmed`
- [ ] 9.3 Tạo route + `RetakeRosterController` (show roster, confirm roster, create offering)
- [ ] 9.4 Tạo Vue page `RetakeRoster/Index.vue` — danh sách waiting_listed + confirm roster
- [ ] 9.5 Tạo Vue page `RetakeOffering/Create.vue` — form tạo CourseOffering thi lại (GV, delivery mode, sessions)
- [ ] 9.6 Implement session assignment — system đề xuất phân student vào sessions theo capacity
- [ ] 9.7 Cập nhật Student Portal calendar API — thêm retake exam sessions vào calendar
- [ ] 9.8 Gửi notification tới sinh viên khi session được xếp

## 10. B5 — Score & Result

- [ ] 10.1 Extend `CanvasGradeSyncService` — hỗ trợ sync cho CourseOffering có `is_retake=true`, tạo AcademicRecord attempt mới
- [ ] 10.2 Tạo `FinalizeRetakeCaseAction` — tạo AcademicRecord mới (attempt_number+1, original_record_id), update `is_gpa_contributing` flags (highest percentage wins), **mark `gpa_calculations` của student đó thành `is_current = false`** để invalidate snapshot (GPACalculationService fallback về live calc), transition case → final_pass/final_fail/no_show
- [ ] 10.3 Tạo manual score fallback: route + controller cho GV nhập `final_retake_score`, transition case → `manual_score_pending_review`
- [ ] 10.4 Tạo Đào tạo review manual score: approve (apply score, finalize) / reject (return to GV)
- [ ] 10.5 Tạo Vue page `RetakeScore/ManualInput.vue` cho GV
- [ ] 10.6 Tạo Vue page `RetakeScore/Review.vue` cho Đào tạo (chỉ approve/reject, không sửa điểm)
- [ ] 10.7 Gửi notification kết quả cuối tới sinh viên (final_pass/final_fail/no_show)

## 11. GPA & Academic Rules

- [ ] 11.1 Cập nhật `GPACalculationService` — filter `WHERE is_gpa_contributing = true`
- [ ] 11.2 Cập nhật `PrerequisiteValidationService` — check "ít nhất 1 attempt is_passed = true" thay vì chỉ check record mới nhất
- [ ] 11.3 Cập nhật graduation check logic (nếu có) — dùng same rule "ít nhất 1 attempt pass"
- [ ] 11.4 Viết migration backfill `is_gpa_contributing` cho data hiện tại — KHÔNG set all = true; phải group by `student_id + unit_id`, chọn record có `final_percentage` cao nhất → `is_gpa_contributing = true`, còn lại = false (repo đã có retake records cũ từ `FixAcademicRecordRetakeData` và `CourseOfferingController`)

## 12. Permissions & Routing

- [ ] 12.1 Định nghĩa permissions mới: `retake.nominate`, `retake.approve`, `retake.open-registration`, `retake.manage-roster`, `retake.create-charge`, `retake.review-score`
- [ ] 12.2 Gán permissions vào roles tương ứng (GV, Đào tạo, HQ Finance)
- [ ] 12.3 Thêm routes vào `app/Modules/Academic/routes/web.php` và Finance module routes
- [ ] 12.4 Thêm navigation links vào sidebar cho từng role
