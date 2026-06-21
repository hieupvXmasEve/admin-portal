## Context

EGC students (`intake_pre_uni_gc`) follow a block-based fee model: 2 levels charged upfront per semester (30M total), with 50% retake discounts triggered by block results and attendance. Currently:

- No `egc_blocks` table — block state is inferred at runtime from `gc_current_level`, `gc_starting_level`, and `academic_records`
- `EgcStudentProgress` is referenced in `Student::egcProgress()` but the class does not exist
- Finance Operations UI is shared between EGC and Course students via conditional branches in `GenerateBatchChargesAction`, `PreviewChargeGenerationQuery`, `StudentChargeTimingResolver`
- Two distinct staff teams operate EGC Finance and Major Finance — no menu-level separation exists

Constraints: shared infra (`FinanceCharge`, `StudentInvoice`, `Payment`) must not be broken. Permissions use Spatie Laravel Permission with campus scoping.

## Goals / Non-Goals

**Goals:**
- Introduce `egc_blocks` as a first-class entity linking academic outcome to finance charge
- Separate EGC and Major finance navigation per staff team
- Enable: block-aware charge generation (1 or 2 blocks, deferred carry-forward, retake eligibility flagging), result sync from academic_records, automatic safe post-sync retake reconciliation, and manual repair via Retake Adjustments

**Non-Goals:**
- Changing invoice, payment, or settlement infrastructure
- Automating block result entry without staff trigger
- Handling non-EGC unit retake fees (handled separately in `CourseOfferingController`)

## Decisions

### D1: New `egc_blocks` table instead of extending academic_records or students

**Chosen**: Dedicated `egc_blocks` table with finance charge links.

**Rationale**: `academic_records` is owned by the Academic module and carries unit-level grade data; it should not hold finance references. `students.gc_current_level` is a scalar counter, not a history of blocks. A dedicated table provides: audit trail per block, direct FK to finance charges, retake/deferred state, and a clean source for the combined EGC reconciliation page.

**Alternative considered**: Add columns to `academic_records` — rejected because it couples Academic and Finance modules.

### D2: `egc_blocks.semester_id` = the semester the block is charged in (not necessarily studied in)

**Chosen**: `semester_id` on `egc_blocks` represents when the charge is issued. A deferred block (1-block student) gets `semester_id = next_semester_id` at creation time, with `finance_charge_id = null` until that semester's Generate Charges runs.

**Rationale**: Enables next-semester Generate Charges to query `egc_blocks WHERE semester_id = ? AND finance_charge_id IS NULL` — a clean, indexable pattern.

### D3: Retake discount uses InvoiceDiscount + a thin audit link table

**Chosen**: The 50% retake discount (7,500,000 VND) is applied as an `InvoiceDiscount` (discount_type = `egc_retake`) on the invoice of the target `egc_level_fee` charge, plus a `DiscountAllocation` to the exact invoice line. To make the source→target mapping explicit and queryable, a thin join table `egc_retake_discount_links` stores both audit refs:

```
egc_retake_discount_links
  id
  invoice_discount_id       FK → invoice_discounts
  source_egc_block_id       FK → egc_blocks        (the "right to discount")
  target_finance_charge_id  FK → finance_charges   (the charge being discounted)
  UNIQUE (target_finance_charge_id)                 ← prevents double discount on same charge
  timestamps
```

`egc_block.retake_discount_id` (nullable FK → `invoice_discounts`) marks that a source block's entitlement has been consumed.

**Business rules locked:**
- One eligible block → one target charge (1:1 entitlement use)
- One target charge → cannot receive retake discount twice (`UNIQUE target_finance_charge_id`)
- A student can hold multiple block entitlements in the same semester — each maps to a different target charge
- If the target invoice is already paid when discount is applied, the system still applies and the overpaid amount is released as unapplied payment following the existing settlement flow

**Target resolution**: post-sync reconciliation automatically picks the safe current/later target charge when generated levels were too high. Staff can still select a current or next semester retake level charge in the manual repair path. UI shows only charges that (a) exist, (b) have an invoice, and (c) have no existing `egc_retake_discount_links` record.

**Rationale**: `egc_retake_discount_links.UNIQUE(target_finance_charge_id)` enforces the "one discount per target charge" rule at the DB level, no application-layer race condition possible. Storing both FKs explicitly enables traceability ("block X discounted charge Y") without joining through DiscountAllocation → InvoiceLine on every query.

**Alternative considered**: `TYPE_ADJUSTMENT -7.5M` FinanceCharge — rejected; free-floating credit not linked to the discounted charge, breaks settlement traceability and balance reporting.

### D4: Retake discount logic lives in `GenerateEgcChargesAction` (new), not in shared `GenerateBatchChargesAction`

**Chosen**: New `GenerateEgcChargesAction` in `app/Modules/Finance/Actions/Egc/` handles all EGC-specific charge generation. `GenerateBatchChargesAction` retains Course student logic.

**Rationale**: The two flows are fully diverged. Continuing to branch inside `GenerateBatchChargesAction` increases cyclomatic complexity with no benefit. Separation makes each action independently testable.

### D5: Block result sync reads academic_records directly (no intermediate cache)

**Chosen**: Sync action queries `academic_records` on demand, joining on `student_id` + `semester_id` + EGC unit level. Staff clicks "Sync Results" on the combined Block Results + Retake Reconciliation page.

**Rationale**: academic_records are updated by Academic module staff; EGC Finance staff need to pull results when ready. A push/event approach would require cross-module coupling. On-demand sync is simple and auditable via `egc_block.synced_at`.

### D5.1: Post-sync reconciliation handles pre-generated levels before manual repair

**Chosen**: After sync confirms a source block failed, Finance runs `ReconcileEgcChargesAfterSyncAction` for safe cases where later pending EGC blocks were generated at levels that are now too high. The action relevels the pending block sequence, updates linked `finance_charges` and `invoice_lines`, recalculates invoice totals, applies the 50% retake discount when attendance is at least 80%, and releases overpayment through the existing settlement service when the invoice is already paid.

**Rationale**: Staff should not sync results on one page and then discover on a second page that the target retake charge is missing only because the pre-generated charge was L4 while the academic retake is L3. Sync is the point where Finance learns the final fail result, so it is also the safest point to realign pending Finance charge artifacts.

**Safety guards**: reconciliation skips rows that are already consumed, have no active target charge, have multiple active invoice lines, have a cancelled/void invoice, already have a retake link for the target, exceed the student's GC total level, or contain non-retake positive discount allocations on the target line. Skipped rows remain visible for manual repair.

## Risks / Trade-offs

- **Risk**: Student has egc_block deferred to S+1 AND a retake discount eligible from same prior semester — double discount applied at generation time.
  → **Mitigation**: `GenerateEgcChargesAction` checks `retake_discount_id IS NULL AND is_retake = false` before marking a block eligible; `UNIQUE(target_finance_charge_id)` in `egc_retake_discount_links` is the final DB-level guard.

- **Risk**: Target invoice is already fully paid when a discount is applied → negative balance on invoice.
  → **Mitigation**: Follow existing settlement flow — discount application or post-sync relevel triggers invoice recalculation; overpaid amount becomes unapplied payment on the student account (same as scholarship applied to paid invoice).

- **Risk**: `academic_records` for EGC units may have multiple records per student per level (e.g., supplementary assessments).
  → **Mitigation**: Sync logic takes the final result: `override_pass = true` takes precedence, else latest `is_passed` by `recorded_at` DESC.

- **Risk**: Staff generates charges for a student who already has an `egc_block` for that semester/block_number (duplicate).
  → **Mitigation**: `UNIQUE (student_id, semester_id, block_number)` constraint on `egc_blocks`; action checks before inserting.

- **Trade-off**: Splitting menu into EGC Operations / Major Operations means staff with both permissions see two separate sections — acceptable given the two-team model.

### D6: UNIQUE constraint on `(student_id, semester_id, block_number)`, not `level_number`

**Chosen**: `UNIQUE (student_id, semester_id, block_number)`.

**Rationale**: A student can study the same level in both block 1 and block 2 of the same semester (failed block 1, retakes same level at block 2). Constraining on `level_number` would prevent this valid case. `block_number` (1 or 2 within a semester) is the correct discriminator — it represents position in the billing sequence, not the academic level.

**Evidence**: Existing data shows students with 2 `course_registrations` for the same `level` in the same `semester_id` but different `course_offering_id` — these are legitimate retake-within-semester blocks, not data artifacts.

### D7: Retake discount policy applies from SPRING2026 onward only

**Chosen**: The 50% retake discount (`is_retake = true`) is applied only from SPRING2026 (semester 2) onward. FALL2025 (semester 1) blocks are backfilled as plain records with `is_retake = false` regardless of outcome.

**Rationale**: The retake discount policy was not in effect during FALL2025. Retroactively flagging historical blocks as `is_retake` would misrepresent the fee basis for that semester. SPRING2026 is the first semester where the system can correctly detect eligible retakes (from FALL2025 results) and apply the discount at charge generation.

## Migration Plan

1. Add migration: `create_egc_blocks_table`
2. Create `EgcBlock` model (replaces `EgcStudentProgress` stub — update `Student::egcProgress()` to point to `EgcBlock`)
3. **Backfill** (required before next Generate Charges run): artisan command `php artisan egc:backfill-blocks`
   - Scope: students with `status = intake_pre_uni_gc` only
   - Source: `course_registrations` JOIN `course_offerings` JOIN `units WHERE unit_type = egc`
   - One `egc_block` per registration row; `block_number` = ORDER BY `course_registration.id` ASC within `(student_id, semester_id)`
   - Same level can appear as block 1 and block 2 in the same semester (no dedup by level)
   - `finance_charge_id`: lookup `finance_charges` by `(student_id, semester_id, description LIKE '%Level {N}%', status = active)` — first unlinked match
   - `result` + `attendance_rate`: sync from `academic_records` using same logic as `SyncEgcBlockResultsAction`
   - `is_retake = false` for all FALL2025 blocks (policy not in effect)
   - `is_retake = true` for SPRING2026 blocks where student has a FALL2025 block with `result = fail` AND `attendance_rate ≥ 80` for the same level
4. Deploy new Actions, Queries, Controllers, Vue pages
5. Update `menu-sidebar.ts` — additive change, existing Major Operations items move under new group header
6. Add new permissions via seeder

Rollback: migration is additive; removing the new menu section and pages leaves existing functionality intact.

## Decisions (continued)

### D8: Early major entry credit is a manual staff action on the combined EGC reconciliation page

**Chosen**: Staff applies the -15M `TYPE_EGC_EXEMPT_CREDIT` credit manually from the combined EGC reconciliation page. No Academic module event hook required.

**Rationale**: Coupling to `STUDENT_MAJOR_ENROLLMENT` would introduce cross-module dependency and require Academic module changes. The credit is a finance operation — keeping it staff-initiated on the finance page preserves module boundaries and allows staff to verify timing.

**Implementation**: A dedicated "Apply Early Major Entry Credit" action on the combined EGC results/retake page, scoped to students with at least one egc_block who have transitioned to major (detectable via student status change or explicit staff selection). Creates a `TYPE_EGC_EXEMPT_CREDIT` FinanceCharge of -15,000,000 and triggers invoice recalculation.
