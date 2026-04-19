## ADDED Requirements

### Requirement: Retake Adjustments page lists all eligible cases for a semester
The system SHALL display all egc_blocks for the selected semester where `result = fail`, grouped by eligibility status: eligible (attendance ≥ 80%), ineligible (attendance < 80%), and already-adjusted (adjustment_charge_id is not null).

#### Scenario: Eligible Block 1 failure shown with Apply Credit option
- **WHEN** an egc_block has `block_number = 1`, `result = fail`, `attendance_rate ≥ 80%`, and `adjustment_charge_id = null`
- **THEN** the row shows "Apply Credit Now" action and displays the credit amount (7,500,000 VND)

#### Scenario: Eligible Block 2 failure shown with next-semester note
- **WHEN** an egc_block has `block_number = 2`, `result = fail`, `attendance_rate ≥ 80%`, and `adjustment_charge_id = null`
- **THEN** the row shows "Auto-apply next semester" status badge (no manual action available)

#### Scenario: Ineligible failure shown with reason
- **WHEN** an egc_block has `result = fail` and `attendance_rate < 80%`
- **THEN** the row shows "Không đủ điều kiện (điểm danh < 80%)" — no action available

#### Scenario: Already-adjusted rows shown as complete
- **WHEN** an egc_block has `adjustment_charge_id IS NOT NULL`
- **THEN** the row shows the adjustment amount and date — no further action available

### Requirement: Staff can apply a -7,500,000 VND credit for eligible Block 1 failures
The system SHALL allow staff to apply an immediate adjustment for a Block 1 failure with attendance ≥ 80%. Applying creates a `TYPE_ADJUSTMENT` FinanceCharge of -7,500,000 VND in the current semester and sets `egc_block.adjustment_charge_id`.

#### Scenario: Apply Credit Now creates adjustment charge
- **WHEN** staff clicks "Apply Credit Now" for an eligible Block 1 egc_block
- **THEN** a FinanceCharge of type `adjustment` with `amount = -7500000` is created for the student in the current semester, `egc_block.adjustment_charge_id` is set, and the row updates to "Already adjusted"

#### Scenario: Apply is idempotent — cannot apply twice
- **WHEN** `adjustment_charge_id` is already set on an egc_block
- **THEN** the "Apply Credit Now" action is not available for that row

### Requirement: Semester filter controls which semester's adjustments are shown
The system SHALL allow staff to filter the Retake Adjustments page by semester.

#### Scenario: Default to current semester
- **WHEN** the page loads without a semester query parameter
- **THEN** the current active semester is selected and its blocks displayed
