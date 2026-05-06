# Phase 5: Legacy Data Migration

## Status: pending

## Tasks

### 5.1 Create Migration Script

**File:** `app/Console/Commands/LinkExistingDngToChargesCommand.php`

**Purpose:** Link existing `dng_payment_requests` records to `finance_charges` via `dng_payment_request_charges` pivot.

**Algorithm:**
```
For each DngPaymentRequest WHERE finance_charge_id IS NULL
  AND chargeLinks count = 0
  AND status IN (pending, pushed_to_dng, paid_uninvoiced, paid_invoiced, reconciled):

  1. Map fee_type → charge_types via DngFeeTypeOptions (reverse map)
  2. Find FinanceCharge WHERE:
     - student_id = dng.student_id
     - charge_type IN (mapped types)
     - status = active
     - semester_id = dng.semester_id (if dng has semester_id)
     - created_at <= dng.created_at + 1 day (temporal proximity)
  3. If exactly 1 charge found → link directly (both pivot + finance_charge_id)
  4. If multiple charges found → link all via pivot, distribute amount proportionally
  5. If no charge found → log warning, skip (manual review needed)
```

**Dry-run mode:**
```bash
./scripts/dev.sh artisan dng:link-charges --dry-run
```

**Execute mode:**
```bash
./scripts/dev.sh artisan dng:link-charges
```

**Output:**
```
Linked: 150
Skipped (already linked): 45
No charge found: 12  ← manual review list
Multiple candidates: 8  ← linked proportionally
```

### 5.2 Verify Linked Data

After running migration:

```sql
-- DNG records still without links
SELECT id, student_code, fee_type, amount, status, created_at
FROM dng_payment_requests
WHERE finance_charge_id IS NULL
  AND id NOT IN (SELECT dng_payment_request_id FROM dng_payment_request_charges)
  AND status NOT IN ('cancelled', 'cancel_pushed_to_dng', 'failed');
```

If any remain, create a manual mapping CSV for review.

### 5.3 Handle Edge Cases

- **Cancelled DNG records**: skip, no need to link
- **DNG with finance_charge_id set but no pivot**: create pivot row from existing link
- **RetakeCourse DNG (already has pivot)**: skip, already correct
- **Multiple DNG for same fee_type/student**: only link the latest active one

## Acceptance Criteria

- [ ] Dry-run shows expected link count
- [ ] All active DNG records linked to charges (or flagged for manual review)
- [ ] Pivot rows created with correct amounts
- [ ] No data loss or duplicate links
- [ ] Existing linked records (retake) untouched
