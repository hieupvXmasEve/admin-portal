## 1. Database & Model

- [ ] 1.1 Create migration `create_egc_blocks_table` with columns: id, student_id, semester_id, block_number, level_number, result (enum: pending/pass/fail), attendance_rate (decimal nullable), is_retake (boolean default false), finance_charge_id (nullable FK), adjustment_charge_id (nullable FK), synced_at (timestamp nullable), timestamps; add UNIQUE constraint on (student_id, semester_id, block_number) — NOT on level_number (same level can appear as block 1 and block 2 in same semester)
- [ ] 1.2 Create `app/Models/EgcBlock.php` with fillable, casts, relationships (student, semester, financeCharge, adjustmentCharge)
- [ ] 1.3 Update `Student::egcProgress()` to reference `EgcBlock::class` instead of `EgcStudentProgress::class`
- [ ] 1.4 Run migration and verify schema

## 2. Permissions

- [ ] 2.1 Add permissions to seeder: `view_egc_finance_operations`, `generate_egc_finance_charges`, `view_egc_block_results`, `sync_egc_block_results`, `view_egc_retake_adjustments`, `apply_egc_retake_adjustment`
- [ ] 2.2 Assign permissions to appropriate roles

## 3. EGC Charge Generation — Backend

- [ ] 3.1 Create `app/Modules/Finance/Actions/Egc/GenerateEgcChargesAction.php` — handles 1-or-2-block selection, deferred block creation, retake discount detection (egc_block fail + attendance ≥ 80% + no adjustment), creates egc_blocks + finance_charges; guards against duplicate blocks via UNIQUE constraint
- [ ] 3.2 Create `app/Modules/Finance/Queries/Egc/PreviewEgcChargeGenerationQuery.php` — returns preview list: student name, blocks to charge, deferred blocks, retake flags, per-block amounts, total; only includes `intake_pre_uni_gc` students
- [ ] 3.3 Create `app/Modules/Finance/Http/Web/Admin/EgcChargeGenerationController.php` with `index` (preview) and `store` (confirm generation) actions
- [ ] 3.4 Add routes under `finance.egc.charges.*`
- [ ] 3.5 Write Pest feature tests: 2-block generation, 1-block + deferred, retake discount applied, retake discount skipped (already adjusted), retake discount skipped (attendance < 80%), duplicate block guard

## 4. Block Results Sync — Backend

- [ ] 4.1 Create `app/Modules/Finance/Actions/Egc/SyncEgcBlockResultsAction.php` — queries academic_records for EGC units in given semester, resolves result (override_pass → pass; else latest is_passed; attendance_percentage), updates egc_block.result, attendance_rate, synced_at
- [ ] 4.2 Create `app/Modules/Finance/Queries/Egc/ListEgcBlockResultsQuery.php` — returns egc_blocks for a semester with student info, result, attendance, synced_at
- [ ] 4.3 Create `app/Modules/Finance/Http/Web/Admin/EgcBlockResultsController.php` with `index` (list) and `sync` (trigger sync) actions
- [ ] 4.4 Add routes under `finance.egc.block-results.*`
- [ ] 4.5 Write Pest feature tests: sync sets result + attendance, override_pass wins, latest record used when duplicates, re-sync overwrites previous result

## 5. Retake Adjustments — Backend

- [ ] 5.1 Create `app/Modules/Finance/Queries/Egc/ListEgcRetakeAdjustmentsQuery.php` — returns fail-result egc_blocks for semester, grouped by: eligible-block1 (apply now), eligible-block2 (auto next sem), ineligible (<80%), already-adjusted
- [ ] 5.2 Create `app/Modules/Finance/Actions/Egc/ApplyEgcRetakeCreditAction.php` — creates TYPE_ADJUSTMENT FinanceCharge (-7,500,000) for the student in the semester, links adjustment_charge_id on egc_block; guards against double-apply
- [ ] 5.3 Create `app/Modules/Finance/Http/Web/Admin/EgcRetakeAdjustmentsController.php` with `index` and `store` (apply credit) actions
- [ ] 5.4 Add routes under `finance.egc.retake-adjustments.*`
- [ ] 5.5 Write Pest feature tests: eligible block1 applies credit, idempotent (no double apply), block2 shown as auto-next, ineligible shown correctly

## 6. Frontend — EGC Operations Pages

- [ ] 6.1 Create `resources/js/pages/Finance/EgcOperations/GenerateCharges.vue` — semester selector, student preview table (name, block count toggle 1/2, deferred indicator, retake badge, amount per block, total), confirm button
- [ ] 6.2 Create `resources/js/pages/Finance/EgcOperations/BlockResults.vue` — semester selector, results table (student, block, level, result badge, attendance %, synced_at), "Sync Results" button with loading state
- [ ] 6.3 Create `resources/js/pages/Finance/EgcOperations/RetakeAdjustments.vue` — semester selector, table grouped/filtered by status (eligible-b1, eligible-b2, ineligible, adjusted), "Apply Credit Now" button per eligible-b1 row, confirmation dialog showing credit amount
- [ ] 6.4 Add `resources/js/pages/Finance/EgcOperations/Dashboard.vue` (can reuse/adapt existing BillingOperations dashboard filtered to EGC)

## 7. Navigation

- [ ] 7.1 Update `resources/js/constants/menu-sidebar.ts` — split Finance Operations into two groups: "EGC Operations" (Generate Charges, Block Results, Retake Adjustments, Due Calendar) and "Major Operations" (Generate Charges, Due Calendar, Settlement, Batch DNG, Exceptions); apply respective permission guards

## 8. Backfill (Required — run before next Generate Charges)

- [ ] 8.1 Create artisan command `php artisan egc:backfill-blocks` — scope: students with `status = intake_pre_uni_gc` only; source: `course_registrations` JOIN `course_offerings` JOIN `units WHERE unit_type = egc`; create one `egc_block` per registration row; `block_number` = position ordered by `course_registration.id ASC` within `(student_id, semester_id)`; same level allowed in block 1 and block 2 (no dedup by level); skip if `egc_block` for that `(student_id, semester_id, block_number)` already exists
- [ ] 8.2 Link `finance_charge_id` during backfill — for each egc_block, lookup `finance_charges WHERE student_id = X AND semester_id = Y AND description LIKE '%Level {N}%' AND status = active`; take first unlinked match; leave null if none found (studied but not charged)
- [ ] 8.3 Sync results during backfill — reuse `SyncEgcBlockResultsAction` logic per semester; set `result` and `attendance_rate` from `academic_records`; `is_retake = false` for all FALL2025 (sem 1) blocks regardless of outcome (policy not in effect)
- [ ] 8.4 Apply `is_retake` for SPRING2026 (sem 2) blocks — after syncing FALL2025 results, scan SPRING2026 blocks: if student has a FALL2025 block with `result = fail` AND `attendance_rate ≥ 80` for the same `level_number`, set `is_retake = true` on the matching SPRING2026 block
- [ ] 8.5 Verify backfill output — query and log: total egc_blocks created, blocks with finance_charge_id null, blocks with is_retake = true, blocks with result = fail
