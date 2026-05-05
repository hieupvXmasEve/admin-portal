## Why

Sinh viên `intake_course` trượt môn trong curriculum version hiện tại không có workflow chính thức để đăng ký học lại. Hiện tại quy trình hoàn toàn thủ công: Đào tạo xác định SV fail → liên hệ HQ thu phí → enroll thủ công vào lớp — không có traceability, không audit được, dễ sót case. Cần một workflow có state machine rõ ràng để track từ lúc đăng ký đến khi SV chính thức enroll vào lớp, đảm bảo phí được thu đúng trước khi enroll.

Khác với retake-exam-workflow (thi lại — tạo lớp riêng, GV nominate, state machine 15+ states), feature này là **học lại** — SV enroll vào CourseOffering đang mở bình thường, workflow đơn giản hơn nhiều.

## What Changes

- Tạo entity `course_retake_registrations` với state machine 5 states để track lifecycle đăng ký học lại: approved → payment_pending → paid → enrolled
- Tạo màn hình Admin cho Đào tạo: xem danh sách SV fail đủ điều kiện, chọn CourseOffering đang mở, đăng ký + confirm 1 bước
- Tạo màn hình Admin cho HQ Finance: review case đã approved, tạo FinanceCharge + DNG payment request
- Auto-enroll: khi DNG webhook xác nhận thanh toán, hệ thống tự động tạo `CourseRegistration` (is_retake = true) và chuyển case sang `enrolled`
- Tạo eligibility query: lọc SV `intake_course` có AcademicRecord fail cho unit trong curriculum version, và có CourseOffering đang mở cho unit đó
- Tái sử dụng hoàn toàn: `FinanceCharge` (TYPE_RETAKE_FEE, morphable source), `DngPaymentService`, `VoidFinanceChargeAction`, `CourseRegistration`, toàn bộ downstream (attendance, grading, Canvas sync)

## Capabilities

### New Capabilities

- `retake-course-entity`: Entity `course_retake_registrations` với state machine 5 states (approved, payment_pending, paid, enrolled, cancelled), transition methods, audit trail trên entity, unique constraint student+unit+semester
- `retake-course-eligibility`: Query lọc SV `intake_course` có AcademicRecord fail + chưa có retake registration đang mở + có CourseOffering đang mở cho unit đó
- `retake-course-admin-registration`: Màn hình Đào tạo đăng ký học lại — list SV fail, chọn CourseOffering, tạo record approved 1 bước; list/filter các retake registrations theo status
- `retake-course-finance-flow`: Màn hình HQ review case approved, tạo FinanceCharge (TYPE_RETAKE_FEE) + DNG request; prefill từ `unit.retake_fee`; payment deadline trên từng record
- `retake-course-auto-enroll`: Khi DNG webhook confirm thanh toán → tự động tạo CourseRegistration (is_retake = true, attempt_number +1) → case chuyển sang enrolled

### Modified Capabilities

- Không thay đổi logic existing — toàn bộ additive

## Impact

**Database**: 1 bảng mới (`course_retake_registrations`)

**Backend**:
- `app/Modules/Academic/` — Model, Actions, Queries, Controllers mới cho retake course registration
- `app/Modules/Finance/` — Action mới cho tạo retake course charge (tái sử dụng FinanceCharge + DngPaymentService)
- `app/Modules/Finance/Dng/Services/DngWebhookService` — hook thêm logic auto-enroll khi payment confirmed cho retake course case

**Frontend**: Trang Vue mới cho Đào tạo (đăng ký học lại + list management) và HQ Finance (review + tạo charge)

**API**: Không có API sinh viên trong phase 1 (Đào tạo đăng ký giúp trên admin). Phase 2 mới thêm Student Portal endpoint.

**No breaking changes** — toàn bộ additive; `FinanceCharge.source` morphable đã hỗ trợ; `DngPaymentService` tái sử dụng trực tiếp; `CourseRegistration` có sẵn `is_retake`, `attempt_number`
