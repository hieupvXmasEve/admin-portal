# Current Story Pack — Non-Academic Charge Generation

**Feature slug:** non-academic-charge-generation
**Mode:** small
**Pack ID:** NAC-1 (single pack)
**Date:** 2026-05-18
**Reads:** `CONTEXT.md`, `discovery.md`, `approach.md`, `history/learnings/critical-patterns.md`
**Approved gate:** work_shape ✓ (2026-05-18)

This is the bead-equivalent unit per critical-pattern #8 (pack-as-bead, no `.beads/` repo). Validating uses this to feasibility-check the work; swarming consumes it directly.

## Entry State

- DB: `finance_charges.charge_type` ENUM does NOT include `bhyt` and 4 other non-academic values.
- BE: No `NonAcademicChargeTypeEnum`. No `GenerateNonAcademicChargesAction`. No `GenerateNonAcademicChargesRequest`. No `NonAcademicChargesTemplateExport`.
- BE: `BillingOperationsController` (web + API) carries bulk-EGC + voucher/scholarship logic.
- FE: `Operations/GenerateCharges.vue` is 920 lines, bulk multi-EGC + scope_type + options.
- Routes: `GET /finance/operations/generate-charges` exists. No `POST .../generate-non-academic-charges`.
- Permissions: `view_finance_operations_generate_charges` exists.
- Tests: zero coverage on non-academic charge path.

## Exit State

- DB: `finance_charges.charge_type` enum has **1 new value**: `bhyt` (per user Q1 decision — narrower than initial proposal of 5). `php artisan migrate` + `migrate:rollback` both clean. Additional non-academic types deferred to future migration.
- BE: New `NonAcademicChargeTypeEnum`, `GenerateNonAcademicChargesAction`, `GenerateNonAcademicChargesRequest`, `NonAcademicChargesTemplateExport` exist and lint clean (`pint`).
- BE: `BillingOperationsController` web `showGenerateCharges` passes only `feeTypes`, `semesters`, `currentCampus` props. API method `generateNonAcademic` is wired. Old bulk-EGC API methods (`previewCharges`, `exportPreviewCharges`, `runGenerate`) marked `@deprecated` in PHPDoc + route comments; NOT deleted in this pack (Q9 decision — defer to follow-up cleanup).
- FE: `Operations/GenerateCharges.vue` ≤ 320 lines, no `scope_type`, no `useForm`, uses `useApi` for multipart POST. Renders fee_type dropdown + semester dropdown + amount + due_date + note + file input + summary card.
- Routes: New `POST /api/admin/finance/operations/generate-non-academic-charges`. Old API routes removed if no consumer.
- Permissions: `view_finance_operations_generate_charges` unchanged.
- Tests: 6 new Pest test files, all green. `php artisan test --filter=NonAcademic` green; coverage ≥80% on new BE files.
- Docs: `docs/` reviewed; rule files updated if a contract changed (controller props are a contract — likely note).

## File Ops (11 total)

> 11 file ops slightly over the 10-op pack recommendation. Validating may push split to 2 packs (backend / frontend+tests). Acknowledged.

| # | Op | Path |
|---|---|---|
| 1 | ADD | `database/migrations/2026_05_18_HHMMSS_add_non_academic_types_to_finance_charges_charge_type.php` |
| 2 | ADD | `app/Modules/Finance/Enums/NonAcademicChargeTypeEnum.php` |
| 3 | ADD | `app/Modules/Finance/Actions/Operations/GenerateNonAcademicChargesAction.php` |
| 4 | ADD | `app/Modules/Finance/Http/Requests/GenerateNonAcademicChargesRequest.php` |
| 5 | ADD | `app/Modules/Finance/Exports/NonAcademicChargesTemplateExport.php` |
| 6 | EDIT | `app/Modules/Finance/Http/Web/Admin/BillingOperationsController.php` (rewrite `showGenerateCharges` props) |
| 7 | EDIT | `app/Modules/Finance/Http/Api/Admin/BillingOperationsController.php` (add `generateNonAcademic`; mark 3 old methods `@deprecated` per Q9) |
| 8 | EDIT | `app/Modules/Finance/routes/api.php` (add new POST `/generate-non-academic-charges` route + name `generate-non-academic-charges`; keep old routes with deprecation comment) |
| 9 | REWRITE | `resources/js/pages/Finance/Operations/GenerateCharges.vue` (920 → ~280) |
| 10 | ADD | `tests/Feature/Finance/Operations/GenerateNonAcademicChargesActionTest.php` |
| 11 | ADD | 5 more Pest test files (FormRequest, enum/DB parity, API endpoint, web render) — bundled as one sub-row |

## Verification Commands

Run after worker completes; all must pass:

```bash
# 1. Migration roundtrip
./scripts/dev.sh artisan migrate
./scripts/dev.sh artisan migrate:rollback --step=1
./scripts/dev.sh artisan migrate

# 2. Pest (new tests + finance regression)
./scripts/dev.sh artisan test --filter=NonAcademic
./scripts/dev.sh artisan test tests/Feature/Finance

# 3. Lint
./scripts/dev.sh artisan pint app/Modules/Finance app/Modules/Finance/Enums
./scripts/dev.sh npm run lint -- resources/js/pages/Finance/Operations/GenerateCharges.vue

# 4. Type check
./scripts/dev.sh npm run type-check

# 5. Grep guards (run before any DELETE of old API methods)
rg "preview-charges|generate-charges-api|previewCharges|generateCharges\b" \
   resources/js app --type ts --type vue --type php

# 6. Enum/DB parity
./scripts/dev.sh artisan test --filter=NonAcademicChargeTypeEnumParity

# 7. Build verification
./scripts/dev.sh npm run build
```

Exit gate: every command exit 0, parity test green, no leftover bulk-EGC API consumers found.

## Critical-Path DAG (single-pack)

```
NAC-1 (this pack)
  └── (no successors; feature complete)
```

For dependency clarity:
- DDL (op #1) blocks tests that hit the enum (ops #10–11 last two).
- Enum (op #2) blocks Action (#3) and FormRequest (#4).
- Action + FormRequest block Controller API (#7) and Action test.
- Controller web (#6) blocks Vue rewrite (#9) (props contract).
- Routes (#8) block API endpoint test.

Implementation order recommended: 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10/11.

## Risk Encoding (worker-facing, per critical-pattern #8)

Workers see these directly in their prompt — no re-reading validation report:

- **R2 (rejected hack):** Do NOT propose `manual_fee + description='BHYT'`. Use real enum values.
- **R3 (loop pairing):** Action test fixture MUST have ≥2 students in `created`, ≥2 in `skipped` (1 not-found + 1 wrong-campus + 1 duplicate minimum). Assert `charge.student_id` matches per row, not just counts.
- **R5 (delete guard):** Before deleting old API methods, run grep from Verification §5. If hits exist, leave the method in place and add `@deprecated` comment.
- **C-P #2 (form helper pairing):** Vue submit MUST use `useApi.post(...)` returning `ApiResponse`. Never `useForm.post()` to an `ApiResponse` route.
- **C-P #3 (storage = lifecycle):** Non-academic charges have NO voucher/scholarship application (D3). Action MUST NOT call `VoucherDiscountAmountResolver` or any scholarship resolver. Cross-check after wiring.
- **Campus invariant (D8):** Action resolves campus via `app('campus')->id` (canonical pattern per `GenerateBatchChargesAction.php:55-61` — `app()->bound('campus') && app('campus')->id`). NOT `session('current_campus_id')` directly. No campus_id input parameter.
- **Duplicate semantics (D7):** Skip if there exists `FinanceCharge` with same `(student_id, charge_type, semester_id, status='active')`. Use Eloquent existence check inside the transaction.
- **Permission gate (F2):** API routes have `['web','auth']` only — no `can:` route middleware. Permission gate MUST be in `GenerateNonAcademicChargesRequest::authorize()` returning `auth()->user()?->can('view_finance_operations_generate_charges') ?? false`.
- **Transaction (F3):** Use `DB::beginTransaction()` + outer try/catch + `DB::commit()` (mirror `GenerateBatchChargesAction:86-onwards`). Whole batch in one transaction; no chunking. Inner per-student try/catch records skipped rows without aborting.
- **ApiResponse shape (F4):** `ApiResponse::success($data)` returns `{ success: true, timestamp, data: {created:[], skipped:[], summary:{}} }`. Vue must read `response.value.data.data` to access the result payload.
- **Old method retention (Q9):** Old bulk-EGC API methods (`previewCharges`, `exportPreviewCharges`, `runGenerate`) get a `@deprecated` PHPDoc + a route comment. NO deletion in this pack. Route entries unchanged. Vue rewrite simply stops calling them.

## Validating — All Questions Resolved (2026-05-18)

| Q | Status | Decision |
|---|---|---|
| Q1 enum shortlist | RESOLVED | Only `bhyt` in this phase. Others defer. |
| Q2 semester_id semantics | RESOLVED | BHYT attaches to semester picked on form. No schema nullable change. |
| Q3 invoice integration | RESOLVED via probe | `CreateFinanceChargeAction.php:39-50` auto-applies scholarship ONLY for `TYPE_TUITION_TERM`. Non-academic types correctly skip scholarship. Matches D3. |
| Q4 old API removal | RESOLVED via Q9 | NOT deleted in this pack. `@deprecated` only. |
| Q5 CSV mechanic | RESOLVED via probe | Maatwebsite supports CSV (`config/excel.php:45`). `useApiRequest.ts:15-20` auto-handles FormData. Real file upload confirmed. |
| Q6 permission name | RESOLVED | Keep `view_finance_operations_generate_charges`. Gate via `FormRequest::authorize()` (F2). |
| Q7 pack split | RESOLVED | Single pack (cross-pack contract dependency on controller props makes split worse). |
| Q8 spike for R4 | NOT NEEDED | Action body probed; behavior deterministic. R4 downgraded medium → low. |
| Q9 old method retention | RESOLVED | `@deprecated` + keep 1 cycle. |

## Out-of-Scope Reminders

Workers must NOT add:
- Batch history page / batch table (D9).
- Filter-based target selection (D5).
- Multi-campus batch (D8).
- Voucher/scholarship for non-academic (D3).
- Per-row CSV override of amount/due_date (D6).
- Touching `EgcOperations/GenerateCharges.vue` or `GenerateBatchChargesAction.php` (D4).

## Handoff Note

This pack is the bead unit. Validating reads entry/exit/file-ops/verification/risks/validating-questions, runs feasibility checks, and either:
- approves → swarming consumes this pack directly, or
- rejects → returns to planning (likely Q1–Q3 conflicts or pack split decision).
