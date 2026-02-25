---
title: "Student Actions Excel Import Page With Shared Decision File"
description: "Add dedicated Excel import page for student actions with template download, conditional defer column rules, and mandatory shared decision document."
status: pending
priority: P1
effort: 18h
branch: dev
tags: [feature, backend, frontend, api, academic]
created: 2026-02-25
---

## Goal
Thêm trang import Excel riêng cho `reports/student-actions` để tạo hàng loạt administrative actions theo đúng contract của `Record Administrative Action`, có template download để tránh nhầm cột, có kiểm tra cột defer theo điều kiện, và bắt buộc 1 file quyết định dùng chung cho toàn batch.

## Scope
In scope:
- Trang import riêng `Admin/Reports/StudentActionsImport`
- Excel template (`.xlsx`) + preview validation + execute import
- Mapping dữ liệu vào `RecordStudentActionAction::run()`
- Rule điều kiện: chỉ bắt buộc check cột bảo lưu học phí khi action là defer
- Bắt buộc có hồ sơ: yêu cầu file quyết định chung trước khi import
- Gắn file quyết định chung vào tất cả action logs import
- Nếu action defer: gắn file quyết định chung vào `defer_cases.upload_record_id`
- Kết quả import: summary thành công/thất bại theo dòng

Out of scope:
- Import update action đã tồn tại
- Upload nhiều file quyết định cho 1 batch
- Background queue/batch job (làm sync ở phase này)

## Current-State Findings (Codebase)
- Trang audit hiện có index/export, chưa có import: `app/Modules/Academic/Http/Web/Admin/StudentActionAuditController.php`.
- Action ghi nhận chính hiện tại: `app/Modules/Academic/Actions/RecordStudentActionAction.php`.
- Validation action hiện tại ở form request: `app/Modules/Academic/Http/Requests/StoreStudentActionRequest.php`.
- `student_action_logs` có `missing_documents` + relation attachment pivot (`student_action_attachments`).
- `defer_cases` có `upload_record_id`, phù hợp lưu file quyết định cho defer.
- UI "Record Administrative Action" hiện chưa upload file trực tiếp, chỉ có `attachment_ids` nếu truyền từ client.

## Target Behavior
1. User vào `reports/student-actions/import` (trang riêng).
2. User upload file quyết định chung (bắt buộc) -> nhận `shared_upload_record_id`.
3. User download template Excel chuẩn từ trang import.
4. User upload file Excel đúng template.
5. Hệ thống preview:
- Parse từng dòng.
- Validate chi tiết theo từng cột và theo `action_type`.
- Với `ACADEMIC_DEFER`: bắt buộc cột `defer_preserve_tuition` (yes/no).
- Với action khác: cột `defer_preserve_tuition` bỏ qua.
6. User xác nhận import.
7. Hệ thống tạo action logs qua `RecordStudentActionAction::run()` cho từng dòng hợp lệ.
8. Sau khi tạo action:
- Gắn `shared_upload_record_id` vào attachments của action log.
- Set `missing_documents=false`.
- Nếu action defer: update `defer_cases.upload_record_id = shared_upload_record_id`.
9. Trả summary: total/success/failed + lỗi theo row.

## Excel Contract (v1)
Required columns:
- `student_code` (map -> `students.student_id`)
- `action_type` (enum: `ACADEMIC_DEFER`, `ACADEMIC_RESUME`, `ACADEMIC_DROPOUT`, `CAMPUS_TRANSFER`)
- `reason`

Conditional columns by action type:
- `ACADEMIC_DEFER`: `from_semester_code`, `return_semester_code`, `defer_preserve_tuition` (yes/no)
- `ACADEMIC_RESUME`: `return_semester_code`
- `ACADEMIC_DROPOUT`: `dropout_semester_code`
- `CAMPUS_TRANSFER`: `from_campus_code`, `to_campus_code`, `effective_at` (Y-m-d H:i:s)

Optional columns:
- `signed_at` (Y-m-d)
- `notes`

Mapping `defer_preserve_tuition`:
- `yes` -> `defer_fee_policy = PRESERVE`
- `no` -> `defer_fee_policy = FORFEIT`
- Không hỗ trợ `PARTIAL` trong import v1 (KISS)

Shared decision file policy:
- Không có `shared_upload_record_id` => chặn preview/execute.

Header validation policy (strict):
- Header phải khớp tuyệt đối template (đúng tên cột, đúng thứ tự cột).
- Thiếu cột hoặc dư cột -> reject file trước khi parse row.

Column-level validation matrix:
- `action_type` là cột điều hướng bắt buộc; chỉ chấp nhận 4 action type trong scope import.
- `ACADEMIC_DEFER`:
  - Bắt buộc `from_semester_code`, `return_semester_code`, `defer_preserve_tuition`.
  - `defer_preserve_tuition` chỉ nhận `yes|no`.
- `ACADEMIC_RESUME`:
  - Bắt buộc `return_semester_code`.
- `ACADEMIC_DROPOUT`:
  - Bắt buộc `dropout_semester_code`.
- `CAMPUS_TRANSFER`:
  - Bắt buộc `from_campus_code`, `to_campus_code`, `effective_at`.
- Cột không liên quan action_type hiện tại:
  - Cho phép rỗng.
  - Nếu có giá trị sai format thì vẫn báo lỗi (để tránh dữ liệu bẩn khó truy vết).

## Architecture Changes
### Backend
- Add import endpoints under `reports.student-actions.*`.
- Add dedicated request classes for preview/execute import payload.
- Add import service/action để:
  - parse Excel,
  - normalize row data,
  - resolve ids từ code (student/semester/campus),
  - validate row,
  - run create action.
- Reuse `RecordStudentActionAction` to giữ DRY business logic.
- Add template generator/export for fixed column order.

### Frontend
- Add import entry button/link từ `StudentActionsAudit.vue` sang page import.
- Build dedicated page `StudentActionsImport.vue` gồm:
  - template download rõ thứ tự cột,
  - file picker Excel,
  - shared decision file uploader,
  - preview table lỗi,
  - execute import action.

## File-Level Plan
Update:
- `app/Modules/Academic/routes/web.php`
  - Add routes: import page, template download, preview import, execute import.
- `app/Modules/Academic/Http/Web/Admin/StudentActionAuditController.php`
  - Add methods for import page + template/preview/execute.
- `resources/js/pages/Admin/Reports/StudentActionsAudit.vue`
  - Add link/button sang import page.
- `resources/js/pages/Admin/Reports/StudentActionsImport.vue`
  - New dedicated import page UI.
- `resources/js/utils/routes.ts`
  - Add route helpers for import endpoints.

Add:
- `app/Modules/Academic/Http/Requests/PreviewStudentActionsImportRequest.php`
- `app/Modules/Academic/Http/Requests/ExecuteStudentActionsImportRequest.php`
- `app/Modules/Academic/Actions/ImportStudentActionsFromExcelAction.php`
- `app/Modules/Academic/Support/StudentActionExcelRowMapper.php`
- `app/Modules/Academic/Support/StudentActionImportTemplateBuilder.php`
- `tests/Feature/Modules/Academic/Web/StudentActionImportTest.php`
- `tests/Unit/Modules/Academic/Support/StudentActionExcelRowMapperTest.php`

## Implementation Phases

### Phase 1 - Route + API Skeleton (2h)
Tasks:
1. Add 3 endpoints: `template`, `import.preview`, `import.execute`.
2. Wire controller methods + base permission gate (`can:change_student_status` for preview/execute).
3. Add route helpers in frontend utils.

Acceptance:
- Endpoints callable via named routes.
- Permission separation đúng: view page != import rights.

### Phase 2 - Excel Parsing + Validation Engine (6h)
Tasks:
1. Build parser/mapper cho Excel rows -> `RecordStudentActionAction` payload.
2. Resolve `student_code`, `semester_code`, `campus_code`.
3. Implement strict header validation (name + order).
4. Implement conditional validation:
- defer -> bắt buộc `defer_preserve_tuition`.
- non-defer -> ignore column.
- remove `ADMISSION_DEFERRAL` khỏi danh sách action_type import.
5. Enforce `shared_upload_record_id` required cho toàn batch.
6. Build preview response schema: `row_number`, `status`, `errors[]`, `normalized_payload`.

Acceptance:
- Preview detect lỗi chính xác từng dòng.
- Rule defer conditional pass đúng theo yêu cầu.
- Không shared file -> reject ngay.
- File sai header template -> reject ngay.
- `ADMISSION_DEFERRAL` trong file -> reject row với message rõ ràng.

### Phase 3 - Execute Import + Attachment Propagation (5h)
Tasks:
1. Execute import from previewed dataset.
2. For mỗi row hợp lệ:
- call `RecordStudentActionAction::run()`
- attach `shared_upload_record_id` to action log
- force `missing_documents=false`
- if defer, update `defer_case.upload_record_id`
3. Return summary report + failed rows.
4. Transaction strategy:
- transaction per row (không rollback toàn batch để dễ vận hành).

Acceptance:
- Action log tạo thành công theo contract hiện hành.
- Tất cả row success có attachment chung.
- Defer rows có `defer_cases.upload_record_id`.

### Phase 4 - Frontend Import UX (3h)
Tasks:
1. Add import button tại `StudentActionsAudit.vue` để điều hướng sang trang import.
2. Tạo trang `StudentActionsImport.vue` gồm:
- upload Excel,
- upload file quyết định chung,
- preview result,
- execute import.
3. Add template download button nổi bật + error display theo row.

Acceptance:
- User flow import tách riêng, rõ ràng, không lẫn với trang audit.
- Error messaging rõ theo dòng Excel.

### Phase 5 - Tests + Regression Guard (2h)
Tasks:
1. Feature tests:
- require shared file,
- strict header validation (wrong name/order/missing/extra),
- defer requires `defer_preserve_tuition`,
- non-defer ignores defer column,
- reject `ADMISSION_DEFERRAL`,
- attachment propagation for all rows,
- defer_case file linkage.
2. Unit tests mapper/parser edge cases.
3. Smoke check existing export/index unaffected.

Acceptance:
- Tests xanh cho behavior mới.
- Không regression ở trang audit hiện tại.

## Risks & Mitigations
- Risk: Excel dữ liệu bẩn (sai code semester/campus/student).
  - Mitigation: preview bắt buộc trước execute + row-level errors.
- Risk: Người dùng dùng file tự tạo, sai thứ tự hoặc tên cột.
  - Mitigation: template download bắt buộc và validate header strict.
- Risk: bypass business rules nếu import tự ghi DB.
  - Mitigation: luôn gọi `RecordStudentActionAction::run()`.
- Risk: attachment thiếu đồng nhất giữa action/defer_case.
  - Mitigation: hậu xử lý bắt buộc sau mỗi create row.

## Security & Data Integrity
- Validate MIME + extension Excel (`xlsx`, `xls`) tại request level.
- Check `shared_upload_record_id` tồn tại trong `upload_records`.
- Permission import tách riêng khỏi quyền view report.
- Log audit import: actor, total rows, success/fail counts.

## Definition of Done
- Có trang import Excel riêng tại `reports/student-actions/import`.
- Có download template để người dùng không bị nhầm cột.
- Defer-only column được kiểm tra đúng điều kiện.
- Mọi row import bắt buộc có hồ sơ thông qua file quyết định chung.
- Defer rows lưu được thông tin hồ sơ vào `defer_cases.upload_record_id`.
- `ADMISSION_DEFERRAL` không được hỗ trợ trong import; hệ thống trả lỗi rõ ràng nếu xuất hiện.
- Test coverage đủ cho business rules chính.
- Transaction mode chốt là `per-row transaction` (row lỗi không rollback toàn batch).

## Unresolved Questions
None.
