## ADDED Requirements

### Requirement: Staff can generate EGC charges with 1 or 2 blocks per student
The system SHALL allow staff to select whether to charge each EGC student 1 block or 2 blocks for the current semester. The default SHALL be 2 blocks. When 1 block is selected, the second block's egc_block record SHALL be created immediately with `semester_id = next_semester_id` and `finance_charge_id = null` (deferred).

#### Scenario: Two-block generation (default)
- **WHEN** staff generates charges for a student with 2 blocks selected
- **THEN** two egc_block records are created for the current semester, and two `egc_level_fee` finance_charges are created (one per level)

#### Scenario: One-block generation (mid-semester student)
- **WHEN** staff generates charges for a student with 1 block selected
- **THEN** one egc_block + finance_charge is created for the current semester, and one egc_block is created with `semester_id = next_semester_id` and `finance_charge_id = null`

### Requirement: Deferred blocks are charged in the following semester
The system SHALL automatically include deferred egc_blocks (finance_charge_id = null) in the charge generation preview for the semester they are deferred to.

#### Scenario: Deferred block appears in next semester preview
- **WHEN** Generate EGC Charges preview runs for semester S+1
- **THEN** students with a deferred egc_block for S+1 appear in the list with their deferred level pre-populated

#### Scenario: Deferred block charged at generation
- **WHEN** Generate EGC Charges is confirmed for semester S+1
- **THEN** the deferred egc_block is charged (finance_charge_id set), and a new egc_level_fee FinanceCharge is created

### Requirement: Retake-eligible blocks are flagged at charge generation, but discount is applied manually
The system SHALL detect students eligible for a retake discount (prior semester egc_block with result = fail, attendance_rate ≥ 80%, retake_discount_id IS NULL) and mark the new egc_block as `is_retake = true`. The charge is always generated at full price (15,000,000 VND). Staff then applies the 50% discount manually via the Retake Adjustments page by selecting a target charge.

#### Scenario: Retake-eligible block flagged at generation
- **WHEN** Generate EGC Charges runs for a student who failed a prior block with attendance ≥ 80% and retake_discount_id IS NULL
- **THEN** the new egc_block is created with `is_retake = true` and the finance_charge amount = 15,000,000 (full price)

#### Scenario: No retake flag if discount already consumed
- **WHEN** Generate EGC Charges runs for a student whose prior egc_block already has retake_discount_id set
- **THEN** `is_retake = false` — the entitlement was already used

#### Scenario: No retake flag if attendance below threshold
- **WHEN** Generate EGC Charges runs for a student who failed a block with attendance_rate < 80%
- **THEN** `is_retake = false` — student is not eligible for the retake discount

### Requirement: EGC charge generation is separate from Course/Major charge generation
The system SHALL provide a dedicated Generate EGC Charges flow under EGC Operations. It SHALL only process students with `status = intake_pre_uni_gc`.

#### Scenario: Course students excluded
- **WHEN** Generate EGC Charges runs
- **THEN** students with `status = intake_course` are not included in the preview or generation
