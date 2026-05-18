# Approach — Non-Academic Charge Generation

**Feature slug:** non-academic-charge-generation
**Date:** 2026-05-18
**Mode:** **small** (one sprint, one story pack, ≤8 file ops)
**Reads:** `CONTEXT.md` (D1–D9), `discovery.md`, `history/learnings/critical-patterns.md`

This file proposes the smallest believable path. The shape artifact (`current-story-pack.md`) is derived from it. Validating consumes the validating questions at the bottom.

## Mode Justification

Why **small**, not standard/high-risk:

- Decisions are locked (9 of them). No fuzzy product surface.
- All architectural primitives exist: `CreateFinanceChargeAction`, `InvoiceGenerationService`, `BillingScopeHelper`, Maatwebsite import pattern, `useApi` composable, campus session resolver.
- One DDL (ALTER ENUM) with strong precedent (exam_resit_fee migration, same SQL flavor).
- Single Vue page rewrite. Single new Action. Single new FormRequest. Single new API method.
- No cross-module contract changes. No queue/job. No invoice schema changes.
- Risk surface is small + bounded: enum migration, duplicate-detection correctness, per-student loop semantics. Each has a critical-pattern guardrail.

Not direct: >2 file ops, requires migration. Not spike: no unknown blocking the plan. Not standard: doesn't need multiple phases/epics — one coherent vertical slice.

## Proposed Path

### 1. Schema — minimal DDL

Add `bhyt` and a shortlist of non-academic types via `ALTER TABLE ... MODIFY COLUMN charge_type ENUM(...)` migration, modeled exactly on `2026_05_05_061055_add_exam_resit_fee_to_finance_charges_charge_type.php`.

**Proposed enum additions (Agent's Discretion — user confirms in approval):**

| Value | Vietnamese label | Use case |
|---|---|---|
| `bhyt` | BHYT (Bảo hiểm y tế) | Driver use case |
| `health_check_fee` | Phí khám sức khoẻ | Đầu khoá |
| `uniform_fee` | Đồng phục | Đầu khoá |
| `dormitory_fee` | Phí KTX | Theo kỳ |
| `physical_insurance` | Bảo hiểm thân thể | Đầu khoá |

5 values. Easy to extend later by another ALTER migration.

**Decisions not in scope of this migration:**
- `semester_id` stays NOT NULL. D6's "period" maps to `semester_id` (admin picks semester on form). No schema cost; invoice pipeline stays intact.
- No new unique constraint. Skip-duplicate (D7) enforced at action layer.
- `description` column reused for free-text "note" from form.

### 2. Backend

**New: `app/Modules/Finance/Actions/Operations/GenerateNonAcademicChargesAction.php`**

Static `run(array $data): array`. Input shape:

```php
[
  'fee_type'    => string,    // enum value, validated by FormRequest
  'semester_id' => int,       // admin-picked period
  'amount'      => string,    // decimal
  'due_date'    => string,    // Y-m-d
  'note'        => string,    // → finance_charges.description
  'student_codes' => array<string>, // parsed mssv list from CSV
]
```

Output shape:

```php
[
  'created' => [['student_code' => 'SE12345', 'charge_id' => 999], ...],
  'skipped' => [
    ['student_code' => 'SE99999', 'reason' => 'student_not_found'],
    ['student_code' => 'SE54321', 'reason' => 'wrong_campus'],
    ['student_code' => 'SE11111', 'reason' => 'duplicate_existing_charge_id_777'],
  ],
  'summary' => ['total' => 200, 'created' => 195, 'skipped' => 5],
]
```

Internal flow (DB::transaction wrapping the whole batch):
1. Resolve `current_campus_id` from session.
2. Load students by `student_code IN (...)` constrained by `campus_id`.
3. For each student_code in input:
   - missing in resolved set → skip with reason (`student_not_found` or `wrong_campus`).
   - existing active `FinanceCharge` matching `(student_id, fee_type, semester_id, status=active)` → skip with `duplicate_existing_charge_id_X`.
   - else: call `CreateFinanceChargeAction::handle([...])` to write charge + invoice line via shared service.
4. Set `source_type='NonAcademicChargeBatch'`, `source_id=null` (no batch table per D9; polymorphic source kept loose for future).

Per-student loop must follow critical-pattern #4 (≥2-row fixtures): tests will use ≥2 students, ≥2 duplicates, ≥2 wrong-campus.

**New: `app/Modules/Finance/Http/Requests/GenerateNonAcademicChargesRequest.php`**

FormRequest. Validates:
- `fee_type` in enum (NonAcademicChargeTypeEnum, see below).
- `semester_id` exists in semesters.
- `amount` numeric > 0.
- `due_date` date, not past.
- `note` string max 255.
- `csv_file` file `mimes:csv,xlsx`, max ~1 MB. Or `student_codes` array if pre-parsed by frontend.

Authorization: `can('view_finance_operations_generate_charges')` (permission name reused — see Permissions below).

**New: `app/Modules/Finance/Enums/NonAcademicChargeTypeEnum.php`**

PHP 8.1 backed enum mirroring the DB enum additions. Used by:
- FormRequest validation (`Rule::enum(...)`).
- Controller to pass `cases()` as options to Inertia.
- Action to type-check `fee_type` input.

This enum is the **single source of truth for non-academic types** in code. The DB enum mirrors it; mirror drift is caught by an integration test that compares `NonAcademicChargeTypeEnum::values()` against `SHOW COLUMNS FROM finance_charges WHERE field='charge_type'`.

**Edit: `app/Modules/Finance/Http/Web/Admin/BillingOperationsController.php`**

Rewrite `showGenerateCharges()`:
```
- Remove EGC/tuition props.
- Pass: ['feeTypes' => NonAcademicChargeTypeEnum::cases(), 'semesters' => Semester::orderByDesc('start_date')->get(), 'currentCampus' => app('campus')]
```

**Edit: `app/Modules/Finance/Http/Api/Admin/BillingOperationsController.php`**

Add `generateNonAcademic(GenerateNonAcademicChargesRequest)`:
```
- Parse CSV → student_codes (use Maatwebsite Excel for parity with ImportStudentActionsFromExcelAction; max 1000 rows hardcoded).
- Call GenerateNonAcademicChargesAction::run([...]).
- Return ApiResponse::success($result).
```

Remove (or hide behind a feature flag for cleanliness during rollout): old `generateCharges` + `previewCharges` API methods if they're not consumed elsewhere. **Verify zero other consumers before delete** — grep step in story pack.

**Edit: `app/Modules/Finance/routes/web.php` + `routes/api/admin.php`**

- Web route `/generate-charges` slug retained (D1 page identity is the slug). Permission name retained: `view_finance_operations_generate_charges`. Namespace stays clean (`view_finance_operations_*` group).
- New API route `POST /api/admin/finance/operations/generate-non-academic-charges` → `BillingOperationsController@generateNonAcademic`.
- Old API routes for the bulk multi-EGC flow removed if no other consumer.

### 3. Frontend

**Rewrite: `resources/js/pages/Finance/Operations/GenerateCharges.vue` (~920 → ~280 lines)**

Single form (no tabs, no scope_type, no checkbox options):

- **Fee type dropdown** — from `feeTypes` prop (enum cases with label).
- **Semester dropdown** — from `semesters` prop.
- **Amount** — number input (VND).
- **Due date** — `<DatePicker>` (component used in EGC page).
- **Note** — text input, max 255.
- **CSV upload** — `<input type="file" accept=".csv,.xlsx">` + downloadable template link.
- **Submit** — `useApi.post(route('api.finance.operations.generate-non-academic-charges'), formData)` with FormData (multipart).
- **Summary card** (after success): N created (green), M skipped table with `student_code` + `reason`. Refresh wipes (D9).

Critical-pattern #2 check: page is non-navigating JSON submit → `useApi` + `ApiResponse::success/error`. NO `useForm.post()`.

**New: `app/Modules/Finance/Exports/NonAcademicChargesTemplateExport.php`**

Tiny Maatwebsite export with single sheet: header row `student_code`, one example row. Served via existing template-download pattern.

### 4. Tests (Pest)

| Test | File | Purpose |
|---|---|---|
| Action happy path | `tests/Feature/Finance/Operations/GenerateNonAcademicChargesActionTest.php` | 3 students, 1 valid + 1 duplicate (pre-seeded charge) + 1 wrong-campus → assert created=1, skipped=2 with correct reasons. **≥2-row fixture per critical-pattern #4.** |
| Action invoice integration | same file | Created charge has invoice line via `InvoiceGenerationService`. |
| FormRequest | `tests/Feature/Finance/Operations/GenerateNonAcademicChargesRequestTest.php` | Invalid fee_type, missing semester, past due_date, malformed CSV all rejected. |
| Enum/DB parity | `tests/Feature/Finance/NonAcademicChargeTypeEnumParityTest.php` | `NonAcademicChargeTypeEnum::values()` ⊂ `SHOW COLUMNS` enum. Prevents drift. |
| API endpoint | `tests/Feature/Finance/Http/Api/GenerateNonAcademicChargesEndpointTest.php` | Auth gate, permission gate, multipart upload, response shape `ApiResponse::success`. |
| Web render | `tests/Feature/Finance/Http/Web/ShowGenerateChargesPageTest.php` | Inertia render passes `feeTypes`, `semesters`, `currentCampus`. |

Coverage target: ≥80% on new files. Per critical-pattern #2, only the API endpoint test catches the (useApi, ApiResponse) pairing; the Inertia-page render test does not.

## Files Touched

| Op | Path | Type |
|---|---|---|
| ADD | `database/migrations/2026_05_18_HHMMSS_add_non_academic_types_to_finance_charges_charge_type.php` | DDL |
| ADD | `app/Modules/Finance/Enums/NonAcademicChargeTypeEnum.php` | New |
| ADD | `app/Modules/Finance/Actions/Operations/GenerateNonAcademicChargesAction.php` | New |
| ADD | `app/Modules/Finance/Http/Requests/GenerateNonAcademicChargesRequest.php` | New |
| ADD | `app/Modules/Finance/Exports/NonAcademicChargesTemplateExport.php` | New |
| EDIT | `app/Modules/Finance/Http/Web/Admin/BillingOperationsController.php` | Rewrite `showGenerateCharges` props |
| EDIT | `app/Modules/Finance/Http/Api/Admin/BillingOperationsController.php` | Add `generateNonAcademic`; remove unused bulk-EGC API methods if no consumer |
| EDIT | `app/Modules/Finance/routes/web.php` | Keep `/generate-charges`; permission retained |
| EDIT | `routes/api/admin.php` | New POST endpoint |
| REWRITE | `resources/js/pages/Finance/Operations/GenerateCharges.vue` | 920 → ~280 lines |
| ADD | 6 test files (see Tests table) | New |

**File-ops count: 11 ops.** Over the pack-as-bead 10-file-op recommendation by 1 (test files inflate). Stay within small mode by treating the 6 tests as a single test-pack story sub-row. Validating must accept or push to splitting into 2 packs.

## Risks

| # | Risk | Severity | Mitigation |
|---|---|---|---|
| R1 | Enum migration locks `finance_charges` table during ALTER on large prod data | medium | Run in low-traffic window; `ALTER TABLE` on enum is metadata-only in MySQL 8 → near-instant. Verify in pre-deploy. |
| R2 | "manual_fee + description" hack proposed instead of real enum values (cheap path) | high | **Rejected**: critical-pattern #4 (storage = lifecycle). Validating gate must reject this. |
| R3 | Per-student loop silently mismatches student_code → student_id | medium | `≥2 students` fixture + assert charge.student_id matches per row. Loop test, not just batch count. |
| R4 | Invoice generation side-effects unfamiliar; non-academic may pollute existing invoice | medium | First validate one fresh student `CreateFinanceChargeAction` for `bhyt` in tinker/spike; confirm invoice line is acceptable shape. Add to validating questions. |
| R5 | Removing old API methods breaks consumers we missed | medium | Grep step in story pack: `rg "preview-charges\|generate-charges-api" --type ts --type php`. Block removal if hits found. |
| R6 | CSV with 1000+ rows times out HTTP request | low | MAX_IMPORT_ROWS=1000 enforced in Action; if user needs more, defer to a future queued job. |
| R7 | "manual_fee" overlap — admin could pick `manual_fee` from non-academic enum dropdown by accident | low | `NonAcademicChargeTypeEnum` is its own enum; does NOT include `manual_fee`. Distinct dropdown. |
| R8 | `Inertia::flash` vs `ApiResponse` confusion on Vue side | low | Critical-pattern #2 covered. Submit is `useApi` → JSON, not page form. |

## Validating Questions

To resolve in `khuym:validating` (or earlier if user already has the answer):

1. **Enum shortlist OK?** Are the 5 proposed values (`bhyt`, `health_check_fee`, `uniform_fee`, `dormitory_fee`, `physical_insurance`) the right initial set? Any to add/remove?
2. **`semester_id` semantics OK?** D6 "period" = semester picked on form. Confirm BHYT charges should attach to a specific semester (not academic year). If wrong, schema migration is required (semester_id nullable).
3. **Invoice integration OK?** Each non-academic charge will create/append to a `StudentInvoice` via `CreateFinanceChargeAction → InvoiceGenerationService`. Confirm this is the intended behavior (not a separate non-invoiced ledger).
4. **Old bulk-EGC API methods — safe to remove?** After grep confirms no other consumer.
5. **Maatwebsite vs textarea?** Plan proposes real file upload (CSV/XLSX) via Maatwebsite — matches Academic precedent and D5 wording. Confirm.
6. **Permission name unchanged?** Keep `view_finance_operations_generate_charges` for namespace cleanliness. Confirm.
7. **Story pack split?** 11 file ops slightly over 10-op bead recommendation. OK as single pack, or split (e.g., backend pack + frontend+tests pack)?
8. **Spike needed?** Risk R4 (invoice line shape for `bhyt`) — sufficient to handle in TDD red-step, or pre-spike in tinker first?

## Out-of-Scope (Per CONTEXT.md Deferred Ideas)

- Trang config quản lý enum (Deferred).
- Batch history page (D9).
- CSV per-row override amount/due_date (D6).
- Filter-based target selection (D5).
- Multi-campus generation (D8).
- Voucher/scholarship for non-academic (D3).
- Touching EGC page (D4).

## Next Step

Write the shape artifact: a single `current-story-pack.md` listing entry/exit states, file ops list, verification commands, and the per-row DAG (one row since single pack). After user approves the shape, hand off to `khuym:validating`.
