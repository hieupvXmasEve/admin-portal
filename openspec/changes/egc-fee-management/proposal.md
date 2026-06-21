## Why

EGC students (intake_pre_uni_gc) follow a block-based fee model fundamentally different from Course students — 2 levels charged upfront per semester, with retake discounts triggered by block results. Currently there is no block entity, no dedicated EGC finance UI, and the two student types are handled through conditional branches in shared screens. A separate Finance Operations menu for EGC staff and a proper block tracking system are needed to support the retake discount and carry-forward policies defined in the EGC fee requirements.

## What Changes

- Create `egc_blocks` table as the central entity tracking each EGC block per student per semester (level, result, attendance, linked charges)
- Separate the Finance sidebar into **EGC Operations** and **Major Operations** sections — each scoped to its respective staff team
- Add **Generate EGC Charges** page: supports 1-block or 2-block charging per student, handles deferred blocks from prior semester, flags retake-eligible blocks (`is_retake = true`) — all charges are at full price (15,000,000 VND)
- Add **Block Results + Retake Reconciliation** page: staff-triggered sync from `academic_records` (is_passed, override_pass, attendance_percentage) updates egc_block results, then automatically reconciles safe post-sync retake charge changes
- Keep **Retake Adjustments** as the manual repair path: lists fail-result blocks with attendance ≥ 80%; staff selects a target charge when auto-reconciliation cannot safely resolve the target

## Capabilities

### New Capabilities

- `egc-block-tracking`: Core entity `egc_blocks` tracking each student's block per semester — level, result, attendance, charge links, retake status, and deferred-to-semester logic
- `egc-charge-generation`: Generate EGC charges with 1-or-2-block selection, deferred block carry-forward, and retake eligibility flagging (`is_retake = true`) — all charges start at full price; discount is applied by post-sync reconciliation or manual repair
- `egc-block-results-sync`: Sync block results from academic_records into egc_blocks via staff-triggered action and run safe post-sync charge reconciliation
- `egc-retake-adjustments`: Surface eligible fail-result blocks (attendance ≥ 80%); auto-reconciled rows are shown as applied, while staff can still select a target charge for repair cases — tracked in `egc_retake_discount_links`

### Modified Capabilities

- `email-content-layer`: No requirement changes — implementation only

## Impact

- **New migration**: `egc_blocks` table
- **New model**: `EgcBlock` (replaces unimplemented `EgcStudentProgress` stub)
- **Finance module**: New Actions, Queries, Controllers under `app/Modules/Finance/`
- **Frontend**: New Vue pages under `resources/js/pages/Finance/EgcOperations/`
- **Sidebar**: `menu-sidebar.ts` split into EGC Operations / Major Operations sections
- **Permissions**: New `egc_finance_*` permission set
- **Existing**: `GenerateBatchChargesAction`, `PreviewChargeGenerationQuery`, `StudentChargeTimingResolver` — touched to accommodate block-aware logic; no breaking API changes
