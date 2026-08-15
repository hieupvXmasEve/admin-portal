# Legacy Code Inventory — 2026-08-10

Audit read-only, grep-evidence. Chi tiết models: `plans/reports/Explore-260810-legacy-models-audit.md`.

## Tổng kết

| Nhóm | Tổng | DEAD | STALE-DUPLICATE | ALIVE-LEGACY (thuộc module) | ALIVE-CORE |
| --- | --- | --- | --- | --- | --- |
| app/Models | 110 | 1 | 30 (class_alias shims) | 70 | 9 |
| app/Services | 58 | 1 | — | 20 misplaced | còn lại alive |
| app/Http/Controllers global | — | 2 | — | — | — |
| routes/web/*, routes/api/* | 14 file | 2 | — | — | — |
| Jobs/Exports/Imports (16) | 16 | 0 | — | — | — |
| Repositories/Queries/Actions global | 0 | — | — | — | — |

## DEAD — xoá ngay, zero risk (mỗi nhóm 1 PR)

1. `app/Models/GraduationApplication.php` — 0 refs.
2. `app/Services/ProgramMappingService.php` — 0 refs (no string-ref trong config/schedule/listeners).
3. `app/Http/Controllers/Web/StudentApplicationController` — route file chứa nó không được include.
4. `app/Http/Controllers/Api/StudentApplicationController` — không bind route nào; Admissions module own.
5. `routes/web/student-application.php` — duplicate Admissions module web.php, không include trong routes/web.php.
6. `routes/api/v1/admissions.php` — duplicate Admissions module api.php, không include.

## STALE-DUPLICATE — 30 model đã có twin trong module + class_alias shim

Engagement 16, Merchandise 7, Facilities 4, Upload 3. Shim đang hoạt động → bước tiếp: grep từng alias, chuyển caller sang namespace module, xoá shim (mỗi domain 1 PR).

**Split-brain nguy hiểm:** `EgcRetakeDiscountLink` tồn tại CẢ `app/Models` và `Modules/Finance` **không có alias** — 2 class sống song song. Finance là authoritative. Fix trước tiên: alias hoặc xoá bản legacy.

## ALIVE-LEGACY — 70 model dùng thật, chờ migrate namespace

| Module đích | Số model |
| --- | --- |
| Academic | 28 |
| StudentRegistry | 16 |
| Finance | 8 |
| Admissions | 4 |
| Khác | 17 |

Cross-module import trong app/Models: một số model legacy import Finance models (BillingAccount, StudentInvoice, FinanceCharge, InvoiceDiscount) — cắt từng relation trước khi move.

## ALIVE-CORE — giữ nguyên (cross-cutting)

User (1037 refs), Campus (1036), Semester (1136), roles/permissions, UserEmailPreference — 9 model.

## Misplaced services (20) — move, không xoá

`app/Services/{Admissions/, V1/Student/, Examples/}` — logic thuộc module (V1/Student phần lớn thuộc Academic, không phải Student API layer). Lưu ý: `BackfillApplicationsCommand` import trực tiếp Admissions services — move phải sửa import.

## Thứ tự thực thi đề xuất

1. PR-1: xoá 6 mục DEAD.
2. PR-2: fix split-brain `EgcRetakeDiscountLink` (alias→Finance).
3. PR-3..n: mỗi domain 1 PR — chuyển caller khỏi 30 alias shim rồi xoá shim (Facilities nhỏ nhất, làm trước).
4. Sau đó: move 20 misplaced services vào module.
5. Cuối: migrate 70 ALIVE-LEGACY theo strangler (Academic 28 model để cuối).

## Câu hỏi chưa giải quyết

- `Option` (6 refs), `AnswerOption` (2 refs): orphan hay form-logic hợp lệ?
- `BillingCycle` (4 refs): billing deprecated hay scholarship cycle đang dùng?
- Low-ref: `StudentSetting` (1), `StudentActionAttachment` (1), `ProgramChangeRequest` (3) — archive?
- Bảng `notifications` / `email_*`: model agent xếp vào nhóm alive/duplicate nào chưa rõ ràng — cần verify read-path riêng trước khi drop bảng.

---

## Update 2026-08-14 (sau commit 912697cbe "sweep and delete 20 unblocked deprecated model shims")

- app/Models: 110 → **90 file**; shims: 30 → **10** (20 shim unblocked đã xoá).
- 10 shim còn lại — bị block bởi caller còn dùng namespace cũ (số file refer):
  Room 27, ApplicationDocumentType 14, ApplicationDocument 13, GoldTransaction 10, FormTarget 9, UploadRecord 9, FormResponse 8, Building 6, QueryTicket 5, QueryReply 4.
- **6 mục DEAD vẫn chưa xoá** (GraduationApplication, ProgramMappingService, 2 StudentApplicationController, 2 route file duplicate).
- **EgcRetakeDiscountLink split-brain vẫn chưa fix** (cả 2 file tồn tại, không alias).
- app/Services: 58 file, chưa move.

## Update 2026-08-15 (plan `260815-1320-close-remaining-legacy-shims-and-final-dead-cleanup`, Phase 1)

- DEAD 6/6 đã xoá: 5 mục đợt trước + `routes/api/v1/admissions.php` (đợt này). Route dead kéo theo dead stack (red-team phát hiện, không nằm trong 6 mục gốc):
  `app/Http/Controllers/Api/V1/Admissions/IngestionController.php`, `app/Http/Requests/Admissions/IngestApplicationRequest.php`,
  `app/Services/Admissions/ApplicationIngestionService.php`, `app/Services/ApplicationDocumentService.php` — cả 4 unhardened duplicate của live path
  `app/Modules/Admissions/Http/Api/IngestionController.php`. `route:list --json` hash không đổi sau khi xoá; `tests/Feature/Admissions/` 182 pass.
- Split-brain `EgcRetakeDiscountLink` **đã fix từ trước** (legacy file đã mất, zero importer) — dòng "chưa fix" ở trên đã stale.
- app/Models = **84 file**; shim còn lại = **6**: ApplicationDocument, FormResponse, GoldTransaction, QueryReply, QueryTicket, UploadRecord.
- **Morph-FQCN pre-flight** (chạy trên DB local đã sync từ prod, theo yêu cầu user — không cần SSH prod):
  - `activity_log.subject_type` LIKE `App\Models\%`: 35 distinct FQCN, **0 hit** cho cả 6 shim (`ApplicationDocument`/`FormResponse`/`GoldTransaction`/`QueryReply`/`QueryTicket`/`UploadRecord`), trên 191,397 dòng.
  - Quét toàn bộ 64 cột `*_type` khác trong schema (grep theo 6 tên model) — **0 hit** ở mọi bảng.
  - Kết luận: không cần backfill migration thứ 3 trước khi mở Phase 2-5; morph pre-flight KHÔNG block.
- `config/migration_debt_paths.php`: xoá 3 entry stale (`frozen_services` × 2, `frozen_controllers` × 1) khớp 4 file đã xoá (route file nằm ở `frozen_routes`, cũng xoá).
- `DeprecatedModelShimArchTest::SHIMMED_MODEL_IMPORT_BASELINE`: xoá 3 entry (`IngestionController.php`, `ApplicationIngestionService.php`, `ApplicationDocumentService.php`).
- `tests/Feature/Architecture` sau đổi (đo lại tại checkpoint Phase 1, trước khi mở Phase 2): 138 pass / 6 fail — **cả 6 fail đều pre-existing**, verify bằng `git stash` chạy lại baseline y hệt trước khi đổi. Danh sách 6 fail chính xác (tái verify sau Phase 2, không đổi): `MigrationDebtInventoryTest` × 2 (`shared_model_imports` baseline 289 drift, `legacy_filter_stacks` baseline 22 drift), `AcademicPeriodBoundaryArchTest`, `CourseDeliveryAssessmentBoundaryArchTest`, `CourseRosterDeliveryBoundaryArchTest`, `TeachingEligibilityAssignmentBoundaryTest` — tất cả không liên quan phase này.
- app/Services: 55 file tại thời điểm này (baseline "58" ghi ngày 2026-08-14 phía trên sai — số đúng lúc đó là 57; trừ `ApplicationIngestionService.php` + `ApplicationDocumentService.php` = 55), chưa move.

## Update 2026-08-15 (plan `260815-1320-close-remaining-legacy-shims-and-final-dead-cleanup`, Phase 2)

- 3 shim Engagement đã xoá: `FormResponse`, `QueryReply`, `QueryTicket`. `SHIMMED_MODELS` còn lại: ApplicationDocument, GoldTransaction, UploadRecord.
- Step-1 model-anchored inventory: `UploadRecord::queryReply()/response()/ticket()` (inverse relation về Engagement) **0 consumer thật** — mọi hit `->response(`/`->ticket(` tìm được đều là name-collision trên model khác (`QueryTicket::response()`, Sanctum callback, Storage disk response, API Resource collection), verify bằng cách đọc receiver type từng call site. 3 relation + 3 import xoá khỏi `UploadRecord.php`.
- 4 file `app/Models` có bare `FormResponse::class` (không import) — qua cả `namespace App\Models` nên resolve về shim: `Student.php`, `StudentFormAssignment.php`, `StudentFormSurvey.php`, `Answer.php`. Thêm `use App\Modules\Engagement\Models\FormResponse;` từng file.
- 7 test file namespace-swap: `LecturerGpaReportTest`, `QueryTicketWorkflowTest`, `SurveyAggregateConfigTest`, `SurveyResultDownloadTest`, `AdminQueryInboxTest`, `EngagementFormsApiTest`, `QueryTicketApiTest`. `UploadRecord`/`FormTarget`/... import khác (chưa sweep) giữ nguyên.
- Cả 2 detector (string grep `App\Models\(FormResponse|QueryTicket|QueryReply)` + bare-name grep trong `namespace App\Models`) xác nhận 0 importer còn lại trước khi xoá shim.
- 2 placement arch test flip: `EngagementFormModelPlacementArchTest` (FormResponse chuyển từ "blocked" sang "swept", 7 model), `EngagementQueryTicketModelPlacementArchTest` (QueryReply+QueryTicket chuyển sang "swept", 4 model — xoá luôn test negative-reference cũ vì đã redundant với `DeprecatedModelShimArchTest` repo-wide 1 khi cả 4 tên trong list đó đều swept).
- `SHIMMED_MODEL_IMPORT_BASELINE`: xoá 6 entry stale (`UploadRecord.php`, `EngagementFormsApiTest.php`, `EngagementQueryTicketModelPlacementArchTest.php`, `SurveyAggregateConfigTest.php`, `SurveyResultDownloadTest.php`, `LecturerGpaReportTest.php`) — lần đầu tự tay xoá làm rớt mất 2 entry hợp lệ (`app/Modules/Engagement/Models/FormResponse.php`, `QueryReply.php` — cả 2 vẫn import `App\Models\UploadRecord`, chưa sweep), phát hiện qua chính assertion của test, thêm lại ngay.
- `tests/Feature/Architecture` full run sau Phase 2: **135 pass / 6 fail** (giảm từ 138 vì 3 test case bị xoá cùng lúc với 2 shim placement test ở trên) — vẫn đúng 6 fail pre-existing y hệt Phase 1 (đã re-verify từng test bằng `git stash`, kể cả `AcademicPeriodBoundaryArchTest` — bị `code-reviewer` agent bắt là thiếu trong lần liệt kê đầu).
- `tests/Feature/Engagement`, `tests/Feature/Upload`, `tests/Feature/Form`, `tests/Feature/Api/V1/Student`, `tests/Feature/Lecture`: 132 pass / 1 fail — fail duy nhất (`UploadPlatformTest` message locale `validation.required` vs `Vui lòng nhập files.`) pre-existing, verify bằng `git stash`, không liên quan.
- Số liệu tham chiếu sau Phase 2: app/Services = 55 (không đổi), cột `*_type` = 64, `shared_model_imports` baseline drift 289→401 (giảm từ 404 đo ở Phase 1 vì `UploadRecord.php` mất 3 dòng `use App\Models\{FormResponse,QueryReply,QueryTicket}`).
