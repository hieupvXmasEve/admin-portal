## ADDED Requirements

### Requirement: Staff can trigger block result sync from academic_records
The system SHALL provide a "Sync Results" action on the combined Block Results + Retake Reconciliation page. When triggered, the system SHALL read `academic_records` for EGC units in the current semester and update matching `egc_blocks` records.

#### Scenario: Successful sync updates result and attendance
- **WHEN** staff triggers Sync Results for semester S
- **THEN** each egc_block with `semester_id = S` and `result = pending` is updated with `result` (pass/fail) and `attendance_rate` from the matching academic_record, and `synced_at` is set to now()

#### Scenario: Sync respects override_pass
- **WHEN** the academic_record for a student's EGC level has `override_pass = true`
- **THEN** the egc_block result is set to `pass` regardless of `is_passed`

#### Scenario: Sync takes latest record when multiple exist
- **WHEN** multiple academic_records exist for the same student + EGC level in a semester
- **THEN** the record with the latest `recorded_at` is used

#### Scenario: Already-synced blocks are re-synced on trigger
- **WHEN** Sync Results is triggered and an egc_block already has a non-pending result
- **THEN** the result is still updated from the latest academic_record (re-sync overwrites previous sync)

### Requirement: Sync runs safe post-sync charge reconciliation
After syncing EGC block results for semester S, the system SHALL inspect newly confirmed failed source blocks and reconcile later pending EGC blocks/charges when pre-generated levels are too high for the retake path.

#### Scenario: Failed block relevels pre-generated future charges
- **WHEN** sync confirms a student failed level L in semester S and later pending EGC charges were generated as L+1 and L+2
- **THEN** the system relevels those later pending blocks and linked charge/invoice-line snapshots to L and L+1

#### Scenario: Eligible failed block auto-applies retake discount
- **WHEN** the failed source block has attendance_rate ≥ 80% and the reconciled target charge is safe to discount
- **THEN** the system creates the `egc_retake` InvoiceDiscount, `DiscountAllocation`, and `egc_retake_discount_links` record, then sets `egc_block.retake_discount_id`

#### Scenario: Ineligible attendance still relevels but skips discount
- **WHEN** the failed source block has attendance_rate < 80%
- **THEN** the system may still relevel safe later pending blocks, but it does not create a retake discount

#### Scenario: Unsafe rows remain for manual repair
- **WHEN** the target charge is missing, void, already consumed, has multiple active invoice lines, or has non-retake positive discount allocations
- **THEN** reconciliation skips the row and reports it in the manual repair summary without mutating it

### Requirement: Combined Block Results page shows current semester blocks with their results
The system SHALL display a table of all egc_blocks for the selected semester, showing student name, block number, level, result, attendance rate, and last synced timestamp.

#### Scenario: Pending blocks shown before sync
- **WHEN** the combined Block Results page loads for a semester where charges were generated but sync not yet run
- **THEN** all egc_blocks for that semester appear with `result = pending` and no attendance_rate

#### Scenario: Results shown after sync
- **WHEN** sync has been run
- **THEN** each row shows the updated result and attendance_rate
