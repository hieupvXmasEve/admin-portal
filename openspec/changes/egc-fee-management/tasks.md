## 1. Database & Model

- [x] 1.1 Create migration `create_egc_blocks_table`: id, student_id, semester_id, block_number, level_number, result (enum: pending/pass/fail), attendance_rate (decimal nullable), is_retake (boolean default false), finance_charge_id (nullable FK → finance_charges), retake_discount_id (nullable FK → invoice_discounts), synced_at (timestamp nullable), timestamps; UNIQUE on (student_id, semester_id, block_number)
- [x] 1.2 Create migration `create_egc_retake_discount_links_table`: id, invoice_discount_id (FK → invoice_discounts), source_egc_block_id (FK → egc_blocks), target_finance_charge_id (FK → finance_charges), timestamps; UNIQUE on (target_finance_charge_id) — DB-level guard against double discount on same charge
- [x] 1.2 Create `app/Models/EgcBlock.php` with fillable, casts, relationships (student, semester, financeCharge, retakeDiscount → InvoiceDiscount)
- [x] 1.3 Update `Student::egcProgress()` to reference `EgcBlock::class` instead of `EgcStudentProgress::class`
- [x] 1.4 Run migration and verify schema

## 2. Permissions

- [x] 2.1 Add permissions to seeder: `view_egc_finance_operations`, `generate_egc_finance_charges`, `view_egc_block_results`, `sync_egc_block_results`, `view_egc_retake_adjustments`, `apply_egc_retake_adjustment`
- [x] 2.2 Assign permissions to appropriate roles

## 3. EGC Charge Generation — Backend

- [x] 3.1 Create `app/Modules/Finance/Actions/Egc/GenerateEgcChargesAction.php` — handles 1-or-2-block selection, deferred block creation, retake flag detection (prior egc_block fail + attendance ≥ 80% + retake_discount_id IS NULL → set is_retake = true on new block); all finance_charges are created at full price (15,000,000); guards against duplicate blocks via UNIQUE constraint
- [x] 3.2 Create `app/Modules/Finance/Queries/Egc/PreviewEgcChargeGenerationQuery.php` — returns preview list: student name, blocks to charge, deferred blocks, retake flags, per-block amounts, total; only includes `intake_pre_uni_gc` students
- [x] 3.3 Create `app/Modules/Finance/Http/Web/Admin/EgcChargeGenerationController.php` with `index` (preview) and `store` (confirm generation) actions
- [x] 3.4 Add routes under `finance.egc.charges.*`
- [x] 3.5 Write Pest feature tests: 2-block generation, 1-block + deferred, is_retake = true flagged (fail + attend ≥ 80%), is_retake = false when entitlement already consumed, is_retake = false when attendance < 80%, duplicate block guard — in all retake cases finance_charge is full price

## 4. Block Results Sync — Backend

- [x] 4.1 Create `app/Modules/Finance/Actions/Egc/SyncEgcBlockResultsAction.php` — queries academic_records for EGC units in given semester, resolves result (override_pass → pass; else latest is_passed; attendance_percentage), updates egc_block.result, attendance_rate, synced_at
- [x] 4.2 Create `app/Modules/Finance/Queries/Egc/ListEgcBlockResultsQuery.php` — returns egc_blocks for a semester with student info, result, attendance, synced_at
- [x] 4.3 Create `app/Modules/Finance/Http/Web/Admin/EgcBlockResultsController.php` with `index` (list) and `sync` (trigger sync) actions
- [x] 4.4 Add routes under `finance.egc.block-results.*`
- [x] 4.5 Write Pest feature tests: sync sets result + attendance, override_pass wins, latest record used when duplicates, re-sync overwrites previous result

## 5. Retake Adjustments — Backend

- [x] 5.1 Create `app/Modules/Finance/Queries/Egc/ListEgcRetakeAdjustmentsQuery.php` — returns fail-result egc_blocks for semester with: eligibility (attendance ≥ 80%), discount status (retake_discount_id null/not null), and for each eligible block: available target charges (current semester and/or next semester egc_level_fee charge for same level that has an invoice); grouped by: eligible-with-targets, eligible-no-targets, ineligible (<80%), already-discounted
- [x] 5.2 Create `app/Modules/Finance/Actions/Egc/ApplyEgcRetakeDiscountAction.php` — validates target charge exists and has an invoice; creates InvoiceDiscount (discount_type = 'egc_retake', discount_source = EgcBlock::class, reference_id = egc_block.id, amount = 7_500_000) on target invoice; creates DiscountAllocation to the target invoice line (charge_id = target egc_level_fee charge); sets egc_block.retake_discount_id; guards against double-apply; triggers invoice recalculation. **Note**: `discount_type = 'egc_retake'` is a new value — extend the InvoiceDiscount type enum/validation before using.
- [x] 5.3 Create `app/Modules/Finance/Actions/Egc/ApplyEgcMajorEntryCreditAction.php` — validates student has transitioned from `intake_pre_uni_gc`; validates student has an existing invoice for the selected semester (reject with error if no invoice — do not auto-create); guards against double-apply (checks existing `TYPE_EGC_EXEMPT_CREDIT` charge for same student + semester); creates `TYPE_EGC_EXEMPT_CREDIT` FinanceCharge of -15,000,000 on that invoice; triggers invoice recalculation
- [x] 5.4 Create `app/Modules/Finance/Http/Web/Admin/EgcRetakeAdjustmentsController.php` with `index` (list with target options + transitioned students eligible for major entry credit) and `store` (apply retake discount, requires: egc_block_id + target_charge_id) and `applyMajorEntryCredit` (apply -15M credit, requires: student_id + semester_id) actions
- [x] 5.5 Add routes under `finance.egc.retake-adjustments.*` (index, store, apply-major-entry-credit)
- [x] 5.6 Write Pest feature tests: eligible block applies discount to current-sem charge, eligible block applies discount to next-sem charge, idempotent retake discount (no double apply), disabled when target charge has no invoice, disabled when target charge not yet generated, ineligible shown correctly, major entry credit applied to transitioned student, major entry credit double-apply blocked, major entry credit hidden for active EGC students

## 6. Frontend — EGC Operations Pages

- [x] 6.1 Create `resources/js/pages/Finance/EgcOperations/GenerateCharges.vue` — semester selector, student preview table (name, block count toggle 1/2, deferred indicator, retake badge, amount per block, total), confirm button
- [x] 6.2 Create `resources/js/pages/Finance/EgcOperations/BlockResults.vue` — semester selector, results table (student, block, level, result badge, attendance %, synced_at), "Sync Results" button with loading state
- [x] 6.3 Create `resources/js/pages/Finance/EgcOperations/RetakeAdjustments.vue` — semester selector, table grouped/filtered by status (eligible-with-target, eligible-no-target, ineligible, discounted); per eligible row: target semester dropdown (only options where charge+invoice exist, disabled if none), "Apply Retake Discount" button, confirmation dialog showing discount amount and target charge; separate section listing transitioned students eligible for "Apply Early Major Entry Credit" (-15M) with double-apply guard
- [x] 6.4 Add `resources/js/pages/Finance/EgcOperations/Dashboard.vue` (can reuse/adapt existing BillingOperations dashboard filtered to EGC)

## 7. Navigation

- [x] 7.1 Update `resources/js/constants/menu-sidebar.ts` — split Finance Operations into two groups: "EGC Operations" (Generate Charges, Block Results, Retake Adjustments, Due Calendar) and "Major Operations" (Generate Charges, Due Calendar, Settlement, Batch DNG, Exceptions); apply respective permission guards

## 8. Backfill (Required — run before next Generate Charges)

- [x] 8.1 Create artisan command `php artisan egc:backfill-blocks` — scope: students with `status = intake_pre_uni_gc` only; source: `course_registrations` JOIN `course_offerings` JOIN `units WHERE unit_type = egc`; create one `egc_block` per registration row; `block_number` = position ordered by `course_registration.id ASC` within `(student_id, semester_id)`; same level allowed in block 1 and block 2 (no dedup by level); skip if `egc_block` for that `(student_id, semester_id, block_number)` already exists
- [x] 8.2 Link `finance_charge_id` during backfill — for each egc_block, lookup `finance_charges WHERE student_id = X AND semester_id = Y AND description LIKE '%Level {N}%' AND status = active`; take first unlinked match; leave null if none found (studied but not charged)
- [x] 8.3 Sync results during backfill — reuse `SyncEgcBlockResultsAction` logic per semester; set `result` and `attendance_rate` from `academic_records`; `is_retake = false` for all FALL2025 (sem 1) blocks regardless of outcome (policy not in effect)
- [x] 8.4 Apply `is_retake` for SPRING2026 (sem 2) blocks — after syncing FALL2025 results, scan SPRING2026 blocks: if student has a FALL2025 block with `result = fail` AND `attendance_rate ≥ 80` for the same `level_number`, set `is_retake = true` on the matching SPRING2026 block; leave `retake_discount_id = null` (staff applies discount manually via Retake Adjustments page)
- [x] 8.5 Verify backfill output — query and log: total egc_blocks created, blocks with finance_charge_id null, blocks with is_retake = true, blocks with result = fail
