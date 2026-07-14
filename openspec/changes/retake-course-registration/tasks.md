## Tasks

### Phase 1: Entity + Backend

- [x] **T1: Migration** — Tạo migration `create_course_retake_registrations_table` với schema đã thiết kế (xem design.md D2). Partial unique index `(student_id, unit_id, semester_id)` cho non-terminal states.

- [x] **T2: Model** — Tạo `App\Models\CourseRetakeRegistration` với: fillable, casts, relationships (student, unit, academicRecord, courseOffering, semester, campus, courseRegistration, financeCharge), state constants, transition methods (`transitionToPaymentPending()`, `transitionToPaid()`, `transitionToEnrolled()`, `cancel()`), scope `nonTerminal()`.

- [x] **T3: Eligibility Query** — Tạo `App\Modules\Academic\Queries\ListRetakeCourseEligibleStudentsQuery`. Input: campus_id, semester_id (optional). Output: danh sách SV intake_course + unit fail + CourseOffering mở + chưa có active retake registration.

- [x] **T4: Create Action** — Tạo `App\Modules\Academic\Actions\CreateRetakeCourseRegistrationAction`. Validate eligibility, tính attempt_number, snapshot retake_fee, tạo record với status approved. Enforce unique constraint (soft check cho cancelled records).

- [x] **T5: Cancel Action** — Tạo `App\Modules\Academic\Actions\CancelRetakeCourseRegistrationAction`. Check status cancellable (approved/payment_pending), void charge nếu có, cancel DNG nếu có, set cancelled.

- [x] **T6: Finance Charge Action** — Historical implementation created `App\Modules\Finance\Actions\CreateRetakeCourseChargeAction`. Retired on 2026-07-15 and superseded by the Finance Intake Contract plus guarded DNG reservation flow.

- [x] **T7: Auto-Enroll on Payment** — Hook vào `DngWebhookService`: khi payment confirmed cho charge có source_type = CourseRetakeRegistration → tạo CourseRegistration (is_retake=true), increment enrollment, transition case paid → enrolled.

### Phase 2: Frontend Admin

- [x] **T8: Routes + Controller (Đào tạo)** — Tạo controller `App\Modules\Academic\Http\Web\Admin\RetakeCourseRegistrationController` với: index (list + filter), create (form đăng ký), store (call Action), cancel.

- [x] **T9: Routes + Controller (HQ Finance)** — Tạo controller hoặc thêm actions vào existing finance controller cho: list approved cases, create charge form, store charge (call Action).

- [x] **T10: Vue Page — List** — Trang list retake registrations cho Đào tạo. useDataTable composable, filter (status, semester, campus, unit), search (SV name, MSSV, unit code). Badge cho status.

- [x] **T11: Vue Page — Create** — Form đăng ký học lại: chọn SV (search), chọn unit (từ fail list), chọn CourseOffering (dropdown lớp mở), dates, notes. useForm pattern.

- [x] **T12: Vue Page — Finance Review** — Trang cho HQ: list cases approved, form tạo charge (prefill amount từ retake_fee, payment_deadline). useForm pattern.

### Phase 3: Polish

- [x] **T13: Permissions** — Thêm permissions cho retake-course CRUD + finance charge creation. Gate/Policy checks.

- [x] **T14: Tests** — Unit tests cho: transition methods, eligibility query, create action, cancel action, finance action, auto-enroll webhook handler.
