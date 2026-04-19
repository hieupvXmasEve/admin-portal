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
- Enable: block-aware charge generation (1 or 2 blocks, deferred carry-forward, auto retake discount), result sync from academic_records, and retake adjustment workflow

**Non-Goals:**
- Changing invoice, payment, or settlement infrastructure
- Automating block result entry without staff trigger
- Handling non-EGC unit retake fees (handled separately in `CourseOfferingController`)

## Decisions

### D1: New `egc_blocks` table instead of extending academic_records or students

**Chosen**: Dedicated `egc_blocks` table with finance charge links.

**Rationale**: `academic_records` is owned by the Academic module and carries unit-level grade data; it should not hold finance references. `students.gc_current_level` is a scalar counter, not a history of blocks. A dedicated table provides: audit trail per block, direct FK to finance charges, retake/deferred state, and a clean source for the Retake Adjustments page.

**Alternative considered**: Add columns to `academic_records` — rejected because it couples Academic and Finance modules.

### D2: `egc_blocks.semester_id` = the semester the block is charged in (not necessarily studied in)

**Chosen**: `semester_id` on `egc_blocks` represents when the charge is issued. A deferred block (1-block student) gets `semester_id = next_semester_id` at creation time, with `finance_charge_id = null` until that semester's Generate Charges runs.

**Rationale**: Enables next-semester Generate Charges to query `egc_blocks WHERE semester_id = ? AND finance_charge_id IS NULL` — a clean, indexable pattern.

### D3: Block 1 failure allows "Apply Credit Now" (-7.5M this semester) OR "auto next semester"

**Chosen**: Both options available on the Retake Adjustments page. "Apply Credit Now" creates a `TYPE_ADJUSTMENT` FinanceCharge (-7,500,000) immediately, linked via `egc_block.adjustment_charge_id`. "Auto next semester" leaves `adjustment_charge_id = null`; Generate Charges next semester detects this and sets `is_retake = true` on the matching level block (charge = 7.5M).

**Rationale**: Finance team may want to reflect the credit in the current semester's balance for reporting. Making both options available avoids forcing a policy decision at the code level.

### D4: Retake discount logic lives in `GenerateEgcChargesAction` (new), not in shared `GenerateBatchChargesAction`

**Chosen**: New `GenerateEgcChargesAction` in `app/Modules/Finance/Actions/Egc/` handles all EGC-specific charge generation. `GenerateBatchChargesAction` retains Course student logic.

**Rationale**: The two flows are fully diverged. Continuing to branch inside `GenerateBatchChargesAction` increases cyclomatic complexity with no benefit. Separation makes each action independently testable.

### D5: Block result sync reads academic_records directly (no intermediate cache)

**Chosen**: Sync action queries `academic_records` on demand, joining on `student_id` + `semester_id` + EGC unit level. Staff clicks "Sync Results" on the Block Results page.

**Rationale**: academic_records are updated by Academic module staff; EGC Finance staff need to pull results when ready. A push/event approach would require cross-module coupling. On-demand sync is simple and auditable via `egc_block.synced_at`.

## Risks / Trade-offs

- **Risk**: Student has egc_block deferred to S+1 AND a retake discount eligible from same prior semester — double discount applied at generation time.
  → **Mitigation**: `GenerateEgcChargesAction` checks `adjustment_charge_id IS NULL AND is_retake = false` before applying discount; unit tests cover this scenario.

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

## Open Questions

- For the "early major entry" credit (-15M `TYPE_EGC_EXEMPT_CREDIT`): should it be triggered from the Academic module (when student action `STUDENT_MAJOR_ENROLLMENT` fires) or remain a manual entry on the Retake Adjustments page?
