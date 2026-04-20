## Why

EGC students (intake_pre_uni_gc) follow a block-based fee model fundamentally different from Course students — 2 levels charged upfront per semester, with retake discounts triggered by block results. Currently there is no block entity, no dedicated EGC finance UI, and the two student types are handled through conditional branches in shared screens. A separate Finance Operations menu for EGC staff and a proper block tracking system are needed to support the retake discount and carry-forward policies defined in the EGC fee requirements.

## What Changes

- Create `egc_blocks` table as the central entity tracking each EGC block per student per semester (level, result, attendance, linked charges)
- Separate the Finance sidebar into **EGC Operations** and **Major Operations** sections — each scoped to its respective staff team
- Add **Generate EGC Charges** page: supports 1-block or 2-block charging per student, handles deferred blocks from prior semester, flags retake-eligible blocks (`is_retake = true`) — all charges are at full price (15,000,000 VND)
- Add **Block Results** page: staff-triggered sync from `academic_records` (is_passed, override_pass, attendance_percentage) to update egc_block results
- Add **Retake Adjustments** page: lists fail-result blocks with attendance ≥ 80%; staff selects a target charge (current or next semester) and applies the 50% discount (7,500,000 VND) as an InvoiceDiscount on that charge's invoice — only available when target charge + invoice already exist

## Capabilities

### New Capabilities

- `egc-block-tracking`: Core entity `egc_blocks` tracking each student's block per semester — level, result, attendance, charge links, retake status, and deferred-to-semester logic
- `egc-charge-generation`: Generate EGC charges with 1-or-2-block selection, deferred block carry-forward, and retake eligibility flagging (`is_retake = true`) — all charges at full price; discount applied separately via Retake Adjustments
- `egc-block-results-sync`: Sync block results from academic_records into egc_blocks via staff-triggered action
- `egc-retake-adjustments`: Surface eligible fail-result blocks (attendance ≥ 80%); staff selects target charge (current or next semester, must already exist with invoice) and applies 50% InvoiceDiscount — tracked in `egc_retake_discount_links`

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
