## ADDED Requirements

### Requirement: EGC block entity exists per student per block position per semester
The system SHALL maintain one `egc_blocks` record per block position (block_number 1 or 2) per student per semester. Each record tracks: block number, level number (GC level 0–6), result (pending/pass/fail), attendance rate, linked finance charge (`finance_charge_id`), retake entitlement consumed marker (`retake_discount_id` FK → invoice_discounts), retake flag (`is_retake`), and deferred state (finance_charge_id = null until charged). The UNIQUE constraint is `(student_id, semester_id, block_number)`.

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

### Requirement: Retake discount entitlement is tracked on the source block
The system SHALL set `egc_block.retake_discount_id` (nullable FK → `invoice_discounts`) when the block's entitlement is consumed. A consumed entitlement cannot be used again.

#### Scenario: Source block marked as consumed after apply
- **WHEN** the system or staff applies a retake discount sourced from an egc_block
- **THEN** `egc_block.retake_discount_id` is set to the InvoiceDiscount id, and the block no longer appears as an available source in the manual-repair section

#### Scenario: Same source block cannot be used twice
- **WHEN** `egc_block.retake_discount_id` is already set
- **THEN** the apply action is rejected with a validation error

### Requirement: Source-to-target mapping is stored in egc_retake_discount_links
The system SHALL maintain a record in `egc_retake_discount_links` for every applied retake discount, storing `invoice_discount_id`, `source_egc_block_id`, and `target_finance_charge_id`. A UNIQUE constraint on `target_finance_charge_id` prevents any target charge from receiving a retake discount more than once.

#### Scenario: Target charge uniqueness enforced at DB level
- **WHEN** an attempt is made to apply a retake discount to a target charge that already has an `egc_retake_discount_links` record
- **THEN** the action fails with a validation error before any DB write occurs (application-level pre-check) and the UNIQUE constraint acts as a final guard

#### Scenario: Audit trail queryable
- **WHEN** querying which block discounted which charge
- **THEN** `egc_retake_discount_links` returns the source egc_block and target finance_charge for any InvoiceDiscount of type `egc_retake`

#### Scenario: Discount only available when target charge has an invoice
- **WHEN** the target `egc_level_fee` charge does not yet have an associated invoice
- **THEN** the apply action is unavailable — UI shows "Charge kỳ sau chưa được generate"
