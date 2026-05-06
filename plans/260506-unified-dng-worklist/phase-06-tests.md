# Phase 6: Tests + Verification

## Status: pending

## Tasks

### 6.1 Unit Tests for `ListDngWorklistQuery`

**File:** `tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php`

**Cases:**
- [ ] Returns students with active charges of specified fee_type
- [ ] Excludes students with zero balance (fully paid)
- [ ] Excludes voided charges
- [ ] Correctly maps each DNG fee_type to charge_types (HP→tuition/egc/course, HL→retake, PTL→exam_resit, KHAC→manual/adjustment)
- [ ] Balance = amount - paid - discount
- [ ] Shows active DNG status per student
- [ ] Search by student name/code works
- [ ] Campus/semester filters work
- [ ] DNG status filter (no_dng, has_active_dng) works
- [ ] Pagination works

### 6.2 Unit Tests for `CreateBatchDngFromChargesAction`

**File:** `tests/Feature/Finance/Dng/CreateBatchDngFromChargesActionTest.php`

**Cases:**
- [ ] Creates DNG + pivot rows for single student
- [ ] Creates DNG for multiple students in batch
- [ ] Auto-cancels existing active DNG for same fee_type
- [ ] Uses amount override when provided
- [ ] Fails gracefully per student (doesn't abort batch)
- [ ] Creates correct pivot amount per charge
- [ ] Validation: rejects zero-balance students
- [ ] Transaction: rolls back on DNG API failure per student
- [ ] HL fee_type: auto-creates charge for approved retake registration before DNG push
- [ ] HL fee_type: skips registration that already has charge (no duplicate)

### 6.3 Controller Tests

**File:** `tests/Feature/Finance/Dng/DngWorklistControllerTest.php`

**Cases:**
- [ ] GET /dng-worklist returns Inertia page with required props
- [ ] GET /dng-worklist?dng_fee_type=HL returns retake charges
- [ ] POST /dng-worklist creates DNG from charges
- [ ] POST /dng-worklist requires authentication + permission
- [ ] Validation errors for missing required fields

### 6.4 Webhook Allocation Verification

Verify that webhook payment allocation correctly uses pivot:
- [ ] Existing test `DngPaymentServiceAllocationNotificationTest` still passes
- [ ] New test: webhook for DNG with chargeLinks → allocates to each linked charge proportionally

### 6.5 Frontend Type-Check + Lint

```bash
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
```

### 6.6 Removed Routes Verification

Verify removed routes return 404 or redirect:
- [ ] `GET /finance/payments/create` → 404
- [ ] `GET /finance/retake-course` → 404
- [ ] `GET /finance/operations/batch-dng` → 404 (or redirect to dng-worklist)
- [ ] `POST /api/v1/finance/dng/payment-requests` → 404
- [ ] `POST /api/v1/finance/dng/batch` → 404
- [ ] No references to removed route names in codebase

### 6.7 Regression Checks

- [ ] Settlement page: apply settlement still works
- [ ] Academic retake registration flow still works (approve → auto-create charge)
- [ ] DNG webhook: existing webhooks process correctly
- [ ] DNG payment request list/show pages still work
- [ ] DNG cancel action still works

## Acceptance Criteria

- [ ] All new tests pass
- [ ] All existing DNG/settlement tests pass (update tests referencing removed routes)
- [ ] Frontend type-check clean
- [ ] No regressions in settlement, retake academic, webhook flows
- [ ] No dangling route references in codebase
