# Brainstorm: Unified DNG Creation Page

**Date:** 2026-05-06
**Status:** Approved — ready for planning

---

## Problem Statement

3 separate pages create DNG payment requests with inconsistent flows:

| Page | Data source | Charge link | DNG constraint check |
|------|-------------|-------------|----------------------|
| Settlement (`/finance/operations/settlement`) | `ListSettlementWorklistQuery` (invoices) | None — no charge↔DNG link | No |
| BatchDng (`/finance/operations/batch-dng`) | Same worklist query | None | No |
| RetakeCourse (`/finance/retake-course`) | `CourseRetakeRegistration` | Yes — via `dng_payment_request_charges` pivot | Partial |

**Consequences:**
1. **Allocation mismatch**: Settlement/BatchDng create DNG without linking to charges → webhook payment cannot allocate to correct charge → display errors
2. **DNG constraint violation**: DNG allows only 1 active record per `fee_type` per student. Pushing duplicate fee_type overwrites silently → data loss
3. **Maintenance burden**: 3 separate controllers, queries, Vue pages for same core operation

## Decision

**Phương án A: Fee-Type Filter Page** — 1 unified page, query from `finance_charges`, fee_type-centric UX.

### Scope

- **Merge**: Settlement DNG UI + BatchDng page + RetakeCourse charge page → 1 new page
- **Keep separate**: Settlement (apply settlement only), Payments/Create (manual single DNG)
- **Settlement page**: remove all DNG UI, keep only apply settlement functionality

### Architecture

**Data source:** `finance_charges` table (source of truth for all charges)

**Query flow:**
```
finance_charges
  WHERE status = 'active' AND amount > 0
    AND charge_type IN (mapped from selected DNG fee_type)
  GROUP BY student_id

  + LEFT JOIN dng_payment_requests (active, same fee_type, same student)
    → show current DNG status

  + Calculate balance = SUM(amount) - SUM(paid) - SUM(discount)
    → only show students with balance > 0
```

**DNG fee_type ↔ charge_type mapping** (via `DngFeeTypeOptions::fromChargeType`):
- HP ← tuition_term, egc_level_fee, course_fee
- HL ← retake_fee
- PTL ← exam_resit_fee
- KHAC ← manual_fee, adjustment

**Create DNG flow:**
1. Input: `student_ids[]`, `fee_type`, `due_date`, `semester_id`
2. Per student:
   a. Load active charges of matching charge_type(s), calculate balance
   b. Check existing active DNG for same fee_type → cancel old if exists
   c. Push to DNG API (amount = sum of balances)
   d. Create `DngPaymentRequest` record
   e. Create `DngPaymentRequestCharge` pivot rows (1 per charge)
3. Return created/failed counts

### UX Flow

```
[Select Fee Type (required)] → [Student list with charges] → [Batch select] → [Set due_date/semester] → [Push DNG]
```

**Page features:**
- Fee_type selector (required) — maps DNG fee_type to charge_type filter
- Student table: info | charge count | total balance | active DNG status | amount (editable)
- Filters: search, campus, semester
- Batch select + push with shared due_date/semester
- Per-student: expandable row showing individual charge breakdown

### Files to Create/Modify

**New:**
- `app/Modules/Finance/Queries/Dng/ListDngWorklistQuery.php` — charge-based worklist
- `app/Modules/Finance/Actions/CreateBatchDngFromChargesAction.php` — batch DNG with charge linking
- `app/Modules/Finance/Http/Web/Admin/DngWorklistController.php` — page controller
- `resources/js/pages/Finance/Operations/DngWorklist.vue` — unified page

**Modified:**
- `resources/js/pages/Finance/Operations/Settlement.vue` — remove DNG dialog + button
- `app/Modules/Finance/routes/web.php` — add new route, keep old routes temporarily
- Navigation menu — update links

**Removed:**
- `resources/js/pages/Finance/Operations/BatchDng.vue`
- `resources/js/pages/Finance/RetakeCourse/Index.vue`
- `resources/js/pages/Finance/Payments/Create.vue`
- `app/Modules/Finance/Http/Web/Admin/BatchDngPageController.php`
- `app/Modules/Finance/Http/Web/Admin/RetakeCourseChargeController.php` (fully absorbed)
- `app/Modules/Finance/Dng/Http/Controllers/DngPaymentController.php` (manual DNG API removed)
- `app/Modules/Finance/Dng/Http/Controllers/BatchDngApiController.php` (replaced by charge-linked action)

**Migration script:**
- Link existing DNG records to charges via student_id + fee_type + amount matching

### Risks

1. **Balance calculation N+1**: `FinanceCharge::getBalanceAttribute()` calls `SettlementService` per row. Need batch-optimized query with subquery/join for paid + discount amounts.
2. **RetakeCourse registration state**: charges created when registration approved → `payment_pending` status. New page shows charges regardless of registration status. Need to verify this doesn't break retake flow.
3. **Existing active DNG conflict**: when pushing new DNG for fee_type that already has active DNG, need clear UX to warn/auto-cancel. DNG API behavior: later push overwrites earlier.

### Success Criteria

- [ ] 1 unified page replaces 3 DNG creation flows
- [ ] Every DNG record linked to charge(s) via pivot
- [ ] Webhook allocation uses pivot → correct charge allocation
- [ ] DNG fee_type uniqueness enforced (warn/cancel old)
- [ ] Settlement page has no DNG UI
- [ ] Legacy DNG records linked to charges via migration

### Resolved Questions

1. ~~`storeSimple` — giữ nguyên hay gộp?~~ → **Gộp**. DNG worklist auto-creates charge for approved retake registrations when pushing HL fee_type.
2. ~~`Payments/Create` — enforce charge linking?~~ → **Loại bỏ hoàn toàn**. Mọi DNG creation qua unified page.
3. Balance calculation → subquery approach (batch-optimized, no denormalization needed).
