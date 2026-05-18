# Discovery — Non-Academic Charge Generation

**Feature slug:** non-academic-charge-generation
**Date:** 2026-05-18
**Mode (proposed):** small — single-sprint refactor + scoped capability add. See `approach.md` for justification.

Evidence-only. No proposals here; proposals live in `approach.md`.

## Repo Reality

### Existing target page

| Asset | Path | Size | Role |
|---|---|---|---|
| Operations page (target) | `resources/js/pages/Finance/Operations/GenerateCharges.vue` | 920 lines | Bulk multi-EGC + options + paste mssv textarea. To be replaced. |
| EGC page (reference, untouched) | `resources/js/pages/Finance/EgcOperations/GenerateCharges.vue` | ~440 lines | Academic charge generation. NOT modified per D4. |
| Controller render | `app/Modules/Finance/Http/Web/Admin/BillingOperationsController.php:71-100` | — | `showGenerateCharges()` Inertia render. |
| Controller API submit | `app/Modules/Finance/Http/Api/Admin/BillingOperationsController.php` | — | Existing submit + preview-export endpoints. |
| Action (bulk) | `app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php` | 474 lines | Static `run(array $data)`. Handles EGC + tuition + voucher/scholarship. Will be **kept untouched** (EGC academic flow uses it). |
| Action (single) | `app/Modules/Finance/Actions/CreateFinanceChargeAction.php` | 225 lines | `handle(array $data)`. Single charge + invoice attach via `InvoiceGenerationService`. |
| Preview export | `app/Modules/Finance/Exports/GenerateChargesPreviewExport.php` | — | Maatwebsite XLSX export for preview before commit. |
| Route | `app/Modules/Finance/routes/web.php:71-73` | — | `GET /finance/operations/generate-charges` → `showGenerateCharges`. Permission `view_finance_operations_generate_charges`. |

### Database — `finance_charges` table

Source: `database/migrations/2026_01_17_100000_create_finance_charges_table.php`.

| Column | Type | Nullable | Note |
|---|---|---|---|
| `student_id` | FK → students | no | cascade on delete |
| `semester_id` | FK → semesters | **no** | cascade on delete — **NOT NULL** |
| `billing_cycle_id` | FK → billing_cycles | yes | |
| `charge_type` | **enum** | no | values: `tuition_term`, `egc_level_fee`, `retake_fee`, `course_fee`, `manual_fee`, `defer_credit`, `egc_exempt_credit`, `scholarship_credit`, `voucher_credit`, `adjustment`, `admission_fee`. **No `bhyt` / health-insurance / dormitory / uniform values.** |
| `amount` | decimal(15,2) | no | positive = charge, negative = credit |
| `description` | string(255) | no | |
| `effective_at` | dateTime | no | |
| `status` | enum(`active`,`void`) | no | default `active` |
| `source_type` | string(100) | yes | polymorphic |
| `source_id` | unsignedBigInteger | yes | polymorphic |
| `created_by_user_id` | FK → users | yes | |

Indexes: `student_id`, `semester_id`, `billing_cycle_id`, `charge_type`, `status`, composite `(source_type, source_id)`, composite `(student_id, semester_id, status)`.

**No unique constraint on `(student_id, charge_type, semester_id)`** — duplicate detection (D7) must run as a `whereExists`/`whereIn` lookup at action time, not by DB constraint.

There is a migration adding `exam_resit_fee` (`2026_05_05_061055_add_exam_resit_fee_to_finance_charges_charge_type.php`) — confirms enum alterations are an established practice in this codebase.

### Existing charge-type enum values (model)

Source: `app/Models/FinanceCharge.php:42-64`. Includes `TYPE_MANUAL_FEE = 'manual_fee'` — a generic catch-all already supported.

### Invoice integration

`CreateFinanceChargeAction::handle()` calls `InvoiceGenerationService->applyInvoiceDiscount(...)` and `updateInvoiceStatus(...)` (`CreateFinanceChargeAction.php:87,185`). All finance charges in this codebase are linked to invoices — **there is no current path that creates a `FinanceCharge` without invoice integration.** This is an architectural fact: non-academic charges must enter the same invoice pipeline OR a separate pathway must be designed (the second option diverges from established patterns).

### Campus scope

`session('current_campus_id')` is the canonical resolver:
- `app/Http/Middleware/SetCampus.php:19-20`
- `app/Http/Middleware/HandleInertiaRequests.php:44`
- `app/Http/Middleware/CheckCampusSelected.php:23`
- `app/Modules/Finance/Http/Web/Admin/EgcRetakeAdjustmentsController.php:28`
- `app/Modules/Finance/Support/BillingScopeHelper.php:33` — `->when($campusId, fn($q) => $q->where('students.campus_id', $campusId))`

Pattern is consistent across Finance + Academic. D8 maps cleanly.

### CSV / Excel ingest precedent

Strongest reference: `app/Modules/Academic/Actions/ImportStudentActionsFromExcelAction.php`.
- Uses `Maatwebsite\Excel\Facades\Excel`.
- `preview()` and `execute()` methods with the same processing core.
- Header validation via `StudentActionExcelRowMapper::validateHeaders()`.
- Hard cap: `MAX_IMPORT_ROWS = 1000`.
- Companion: `app/Modules/Academic/Exports/StudentActionImportTemplateExport.php` — generates downloadable template.

Maatwebsite Excel already in dependencies (used by Finance + Academic exports/imports). Existing Operations page uses a `<textarea>` paste pattern instead — diverges from this stronger Academic pattern.

### Permission system

- Config key exists: `config/permission.php:337` → `'view_finance_operations_generate_charges'`.
- Route middleware: `app/Modules/Finance/routes/web.php:72` → `->middleware('can:view_finance_operations_generate_charges')`.
- Permissions are seeded via DB; renaming a permission requires both config change + seed migration.

### Frontend submit pattern

`Operations/GenerateCharges.vue` already imports `useApi` from `@/composables/useApiRequest` (line 73) — non-navigating JSON submit, paired with `ApiResponse::success/error`. Matches the critical-pattern rule on `useForm` ↔ response-shape pairing.

### Critical patterns that apply

From `history/learnings/critical-patterns.md`:

1. **Storage decisions are about lifecycle** (line 73-92) — non-academic charges may diverge from academic on: lifecycle (no auto-discount), audit (per-batch traceability via `source_type`), enum closedness. Validating must reject the "manual_fee + description=BHYT" hack if lifecycle diverges.
2. **Inertia useForm ↔ response-shape pairing** (line 95-113) — confirm submit goes JSON API + `useApi`, never `useForm.post()` against an API route returning `ApiResponse::success()`.
3. **Pack-as-bead with DAG when `.beads/` absent** (line 161-178) — `.beads/` not present in repo; story pack will encode entry/exit + file ops + verification.
4. **Per-recipient loops need ≥2-row fixtures** (line 138-156) — bulk charge action's per-student loop must be tested with ≥2 students + ≥2 with duplicate state to catch loop-pairing regressions.

## Schema Constraints That Force Design Decisions

These are **hard facts** that planning must resolve before shape approval:

1. **`finance_charges.charge_type` is a closed DB enum.** Adding `bhyt`/`health_insurance`/`uniform_fee`/`dormitory_fee` requires either:
   - (a) ALTER enum to add values (DDL migration; precedent exists),
   - (b) convert enum → varchar (heavier DDL, breaks indexes briefly),
   - (c) reuse `manual_fee` + put subtype in `description` (clashes with critical-pattern #4, no native indexability).

2. **`finance_charges.semester_id` is NOT NULL.** BHYT may be year-bound, not semester-bound. Options:
   - (a) admin picks semester on form (D6's "period" = semester) — no schema change,
   - (b) make `semester_id` nullable (DDL + invoice-pipeline impact unknown).

3. **No unique constraint on `(student_id, charge_type, semester_id)`.** D7's skip-duplicate behavior must be enforced at action time. Could add a unique constraint as defense in depth but it would block legitimate adjustments — needs decision.

4. **Invoice integration is universal.** `CreateFinanceChargeAction` always touches `InvoiceGenerationService`. Decision: non-academic flow MUST create invoice lines (reuse `CreateFinanceChargeAction`) OR a separate non-invoiced pathway exists. Currently the second pathway does not exist.

## Open Repo Questions (No Evidence Yet — Pre-Approach)

- Is there an existing "ALTER enum charge_type" pattern that uses `ENUM(...)` modification vs DROP/RECREATE? Need to inspect the `exam_resit_fee` migration for the SQL flavor before writing a similar migration.
- Are there permissions hierarchically grouped (e.g., `view_finance_operations_*`) that influence rename strategy?
- Does the existing `Operations/GenerateCharges.vue` paste-textarea flow currently validate mssv format or just splits on `\n`?

These will be answered in `approach.md` before story decomposition.

## Inputs To Approach

- 9 locked decisions (D1–D9) in `CONTEXT.md`.
- 4 critical patterns above.
- 4 hard schema constraints above.

CONTEXT.md is source of truth. Discovery is read-only evidence. Approach proposes the path.
