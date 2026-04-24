## Why

Hiện tại hệ thống không có workflow quản lý thi lại cho sinh viên fail — quá trình từ GV đề cử, Đào tạo phê duyệt, sinh viên đăng ký, HQ thu phí, đến tổ chức thi và chốt kết quả đang hoàn toàn thủ công và không audit được. Cần xây dựng một workflow end-to-end có state machine rõ ràng để thay thế quy trình thủ công hiện tại, đảm bảo traceability và tự động hóa các auto-transition theo deadline.

## What Changes

- Tạo entity `course_retake_cases` làm source of truth cho toàn bộ lifecycle thi lại (approval → payment → scheduling → result)
- Tạo entity `retake_batches` để gom các case cùng `unit + campus + term` vào một đợt thi lai chung
- Bổ sung eligibility engine với hard-rules có thể cấu hình (max attempts, min attendance) để lọc trước danh sách sinh viên trước khi GV nominate
- Bổ sung màn hình GV nominate sinh viên đủ điều kiện sau khi complete course
- Bổ sung màn hình Đào tạo phê duyệt/từ chối đề cử và mở cửa sổ đăng ký
- Bổ sung Student Portal endpoint cho sinh viên tự đăng ký thi lại
- Bổ sung màn hình HQ Finance review và tạo charge/DNG request cho từng case
- Bổ sung daily job auto-transition các case overdue (không đăng ký, không có charge, không thanh toán đúng hạn) sang trạng thái `repeat_course`
- Bổ sung màn hình Đào tạo xác nhận roster, tạo CourseOffering thi lại, phân session cho sinh viên
- Bổ sung Canvas sync + manual score fallback cho CourseOffering thi lại, Đào tạo review manual score
- Cập nhật GPA calculation: dùng điểm cao nhất giữa các attempt của cùng một unit (thay vì tính tất cả attempt)
- Cập nhật prerequisite/graduation rule: môn đạt nếu có ít nhất 1 attempt pass hợp lệ
- Observer auto-void charge và auto-close retake case khi attempt gốc được sửa thành pass

## Capabilities

### New Capabilities

- `retake-case-workflow`: Entity `course_retake_cases` với state machine đầy đủ, tất cả transitions, audit trail, và auto-close logic khi attempt gốc pass
- `retake-batch-management`: Entity `retake_batches` — system đề xuất batch theo `unit+campus+term`, Đào tạo xác nhận mở; quản lý registration window và batch close date
- `retake-eligibility-engine`: Hard-rule engine lọc sinh viên đủ điều kiện trước khi GV nominate; configurable qua `config/retake.php` (max_attempts, min_attendance); global config cho MVP — per-unit override là future enhancement
- `lecturer-nomination-flow`: UI và Action cho GV nominate/not-nominate sinh viên từ danh sách fail sau khi complete course
- `training-approval-flow`: UI và Action cho Đào tạo approve eligibility → open registration (2 bước tách biệt), reject nomination
- `student-retake-registration`: Student Portal endpoint đăng ký thi lại; chỉ hiện CTA khi case ở trạng thái `registration_opened` và trong registration window
- `retake-finance-flow`: HQ review case, tạo FinanceCharge + DNG request cho từng case; prefill từ `unit.retake_fee`; enforce charge chỉ tạo trong registration window; payment deadline không vượt batch close date
- `retake-scheduling-assessment`: Roster confirmation, tạo CourseOffering thi lại, phân student vào session; attendance tại exam session; Canvas sync primary, manual score fallback với Đào tạo approve
- `gpa-retake-rule`: Cập nhật `GPACalculationService` dùng highest-attempt per unit; cập nhật prerequisite/graduation check dùng "có ít nhất 1 attempt pass"

### Modified Capabilities

- `course-completion`: Sau complete course, GV truy cập màn hình nomination riêng (query-on-demand từ FailedStudentsService + eligibility filter); không thêm side effect vào finalizeCourse()

## Impact

**Database**: 2 bảng mới (`retake_batches`, `course_retake_cases`); thêm `is_retake` + `retake_batch_id` vào `course_offerings`; thêm `is_gpa_contributing` vào `academic_records`

**Backend**:
- `app/Modules/Academic/` — Actions, Queries, Controllers mới cho nomination + training approval + scheduling
- `app/Modules/Finance/` — Actions mới cho retake charge creation
- `app/Services/CourseCompletionService` — không thay đổi logic; GV tự truy cập màn hình nomination qua link từ completion success screen
- `app/Services/V1/Student/GPACalculationService` — highest-attempt rule
- `app/Console/Commands/` — `TransitionOverdueRetakeCasesCommand` (daily)
- `app/Observers/AcademicRecordObserver` — auto-close on override_pass

**Frontend**: Các trang Vue mới cho nomination, training approval, retake batch management, HQ finance review, scheduling/roster, score review

**API**: Student portal endpoint mới (`/api/v1/student/retake-cases`)

**Config**: `config/retake.php` mới (max_attempts, min_attendance_percentage)

**No breaking changes** — tất cả đều là additive; `FinanceCharge.source` morphable đã hỗ trợ; `VoidFinanceChargeAction` tái sử dụng trực tiếp
