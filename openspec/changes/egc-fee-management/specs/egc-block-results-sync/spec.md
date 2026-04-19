## ADDED Requirements

### Requirement: Staff can trigger block result sync from academic_records
The system SHALL provide a "Sync Results" action on the Block Results page. When triggered, the system SHALL read `academic_records` for EGC units in the current semester and update matching `egc_blocks` records.

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

### Requirement: Block Results page shows current semester blocks with their results
The system SHALL display a table of all egc_blocks for the selected semester, showing student name, block number, level, result, attendance rate, and last synced timestamp.

#### Scenario: Pending blocks shown before sync
- **WHEN** the Block Results page loads for a semester where charges were generated but sync not yet run
- **THEN** all egc_blocks for that semester appear with `result = pending` and no attendance_rate

#### Scenario: Results shown after sync
- **WHEN** sync has been run
- **THEN** each row shows the updated result and attendance_rate
