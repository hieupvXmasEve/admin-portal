## ADDED Requirements

### Requirement: EGC block entity exists per student per block position per semester
The system SHALL maintain one `egc_blocks` record per block position (block_number 1 or 2) per student per semester. Each record tracks: block number, level number (GC level 0–6), result (pending/pass/fail), attendance rate, linked finance charge, linked adjustment charge, retake flag, and deferred state (finance_charge_id = null until charged). The UNIQUE constraint is `(student_id, semester_id, block_number)`.

#### Scenario: Block created at charge generation time
- **WHEN** Generate EGC Charges runs for a student in semester S
- **THEN** an `egc_block` record is created for each block being charged, with `result = pending`, `finance_charge_id` linked to the new `egc_level_fee` charge

#### Scenario: Same level appears as both block 1 and block 2 in same semester
- **WHEN** a student fails block 1 (level X) and retakes the same level as block 2 in the same semester
- **THEN** two separate `egc_block` records exist: `(block_number=1, level_number=X)` and `(block_number=2, level_number=X)` — both valid, uniqueness is on block_number not level_number

#### Scenario: Deferred block exists before charge
- **WHEN** a student was charged 1 block in semester S and a deferred egc_block was created with `semester_id = S+1` and `finance_charge_id = null`
- **THEN** Generate EGC Charges for S+1 detects this block and charges it, setting `finance_charge_id` on the existing record

#### Scenario: Duplicate block position prevented
- **WHEN** Generate EGC Charges attempts to create an egc_block for a student/semester/block_number combination that already exists
- **THEN** the action skips creation and reuses the existing record (no duplicate charge)

### Requirement: EgcBlock model replaces EgcStudentProgress stub
The system SHALL use `App\Models\EgcBlock` as the Eloquent model for `egc_blocks`. The `Student::egcProgress()` relationship SHALL be updated to reference `EgcBlock`.

#### Scenario: Student EGC progress relationship
- **WHEN** `$student->egcProgress` is accessed
- **THEN** it returns all EgcBlock records for that student

### Requirement: Adjustment charge is linked to the block
The system SHALL store the FK of any adjustment FinanceCharge on `egc_block.adjustment_charge_id` when a retake credit is applied.

#### Scenario: Credit linked after apply
- **WHEN** staff applies a retake credit adjustment for an egc_block
- **THEN** `egc_block.adjustment_charge_id` is set to the new FinanceCharge id
