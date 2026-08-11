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
