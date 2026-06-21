## ADDED Requirements

### Requirement: Combined EGC reconciliation page lists eligible source blocks with available target charges
The system SHALL display all egc_blocks for the selected semester where `result = fail` and `attendance_rate ≥ 80%` and `retake_discount_id IS NULL`, each annotated with a list of candidate target charges (egc_level_fee charges for the same level in current or next semester that have an invoice and no existing retake discount link). Blocks without any available target are shown with a disabled/manual-repair state. Blocks already consumed (`retake_discount_id IS NOT NULL`) are shown in a separate "Applied" section. The common safe case is auto-reconciled after Sync Results; this manual target selection remains for repair rows.

#### Scenario: Eligible block with available targets shown with selector
- **WHEN** an egc_block has `result = fail`, `attendance_rate ≥ 80%`, `retake_discount_id = null`, and at least one candidate target charge exists
- **THEN** the row shows a target charge selector (populated with available charges: semester label + amount) and an "Apply Retake Discount" button

#### Scenario: Eligible block with no available targets shown as disabled
- **WHEN** an egc_block has `result = fail`, `attendance_rate ≥ 80%`, `retake_discount_id = null`, but no candidate target charge exists (not yet generated or all targets already consumed)
- **THEN** the row shows "Charge chưa có hoặc đã được dùng" — apply action is disabled

#### Scenario: Target charge already consumed is excluded from selector
- **WHEN** a candidate target charge already has an `egc_retake_discount_links` record
- **THEN** that charge is NOT shown in the selector for any other source block

#### Scenario: Ineligible failures listed separately
- **WHEN** an egc_block has `result = fail` and `attendance_rate < 80%`
- **THEN** the row shows "Không đủ điều kiện (điểm danh < 80%)" with no action

#### Scenario: Applied discounts listed in Applied section
- **WHEN** an egc_block has `retake_discount_id IS NOT NULL`
- **THEN** the row appears in the Applied section with: discount amount, target charge reference, target semester, applied date

#### Scenario: Auto-reconciled discounts listed in Applied section
- **WHEN** Sync Results auto-applies an EGC retake discount through post-sync reconciliation
- **THEN** the consumed source block appears in the Applied section without requiring staff to visit a separate Retake Adjustments page

### Requirement: Apply action validates source, target, and uniqueness before writing
The system SHALL enforce a 6-step validation sequence before applying the retake discount.

#### Scenario: Full validation sequence passes — discount applied
- **WHEN** staff submits with a valid source block and target charge
- **THEN** the system: (1) confirms source block is eligible (fail + attendance ≥ 80% + retake_discount_id null), (2) confirms target charge exists, (3) confirms target charge has an invoice, (4) confirms no existing `egc_retake_discount_links` for target charge, (5) creates InvoiceDiscount (discount_type = 'egc_retake', amount = 7_500_000) on the target invoice + DiscountAllocation to the target invoice line + egc_retake_discount_links record, (6) recalculates invoice totals; sets `egc_block.retake_discount_id`

#### Scenario: Target charge already received retake discount — rejected
- **WHEN** the selected target charge already has an `egc_retake_discount_links` record
- **THEN** the action returns a validation error: "Target charge đã nhận retake discount" — no writes occur

#### Scenario: Source block already consumed — rejected
- **WHEN** `egc_block.retake_discount_id` is already set
- **THEN** the action returns a validation error — no writes occur

#### Scenario: Target charge has no invoice — rejected
- **WHEN** the selected target charge has no associated invoice
- **THEN** the action returns a validation error: "Target charge chưa có invoice"

#### Scenario: Paid invoice — discount applied, surplus becomes unapplied payment
- **WHEN** the target invoice is already fully paid and the discount causes an overpayment
- **THEN** the discount is still applied; invoice is recalculated; the overpaid amount is released as unapplied payment on the student account following the existing settlement flow

#### Scenario: Multiple blocks eligible same semester — each maps to different target
- **WHEN** a student has two fail-eligible egc_blocks in the same semester (different levels)
- **THEN** each block can independently apply a discount to a different target charge; the two discounts do not interfere with each other

### Requirement: Staff can manually apply early major entry credit on the combined EGC reconciliation page
The system SHALL provide an "Apply Early Major Entry Credit" action for EGC students who have transitioned to major (student status no longer `intake_pre_uni_gc`). When applied, the system SHALL create a `TYPE_EGC_EXEMPT_CREDIT` FinanceCharge of -15,000,000 VND on the student's current semester invoice and recalculate invoice totals. The action SHALL be guarded against double-apply (one credit per student per semester).

#### Scenario: Credit applied to transitioned student
- **WHEN** staff selects a student who has transitioned to major and applies the early major entry credit
- **THEN** a `TYPE_EGC_EXEMPT_CREDIT` FinanceCharge of -15,000,000 is created on the student's invoice for the selected semester and the invoice is recalculated

#### Scenario: Double-apply blocked
- **WHEN** a `TYPE_EGC_EXEMPT_CREDIT` charge already exists for the student in that semester
- **THEN** the apply action is rejected with a validation error — no second credit is created

#### Scenario: No invoice for selected semester — rejected
- **WHEN** staff applies the early major entry credit but the student has no invoice for the selected semester
- **THEN** the action returns a validation error: "Student chưa có invoice cho kỳ này" — no FinanceCharge is created; staff must generate charges first

#### Scenario: Only available for transitioned students
- **WHEN** a student still has status `intake_pre_uni_gc`
- **THEN** the early major entry credit action is not shown for that student

### Requirement: Semester filter controls which semester's source blocks are shown
The system SHALL allow staff to filter the combined EGC reconciliation page by the semester of the failed (source) block.

#### Scenario: Default to current semester
- **WHEN** the page loads without a semester query parameter
- **THEN** the current active semester is selected and its eligible blocks displayed
