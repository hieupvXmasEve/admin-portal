# Phase 5: Tests

**Priority:** High | **Effort:** M | **Status:** Planned

## Overview

Feature tests cho tất cả API endpoints mới và enhanced.

## Test File

`tests/Feature/Finance/StudentFinanceApiTest.php`

## Test Cases

### DNG Requests (Phase 1)
- [ ] `it lists student dng payment requests`
- [ ] `it filters dng requests by status`
- [ ] `it shows dng request detail with qr payload`
- [ ] `it prevents access to other student dng requests`
- [ ] `it returns 401 for unauthenticated`

### Invoices (Phase 2)
- [ ] `it lists student invoices`
- [ ] `it filters invoices by semester`
- [ ] `it filters invoices by status`
- [ ] `it shows invoice detail with lines and payments`
- [ ] `it prevents access to other student invoices`
- [ ] `it computes remaining amount correctly`

### Enhanced Payments (Phase 3)
- [ ] `it lists payments with allocations`
- [ ] `it filters payments by date range`
- [ ] `it filters payments by method`
- [ ] `it shows payment detail with allocation tree`
- [ ] `it includes dng request for gateway payments`
- [ ] `it prevents access to other student payments`

### Overview (Phase 4)
- [ ] `it returns finance overview`
- [ ] `it filters overview by semester`
- [ ] `it returns correct pending dng count`
- [ ] `it returns correct invoice summary`

## Implementation Steps

1. Create test file with Pest
2. Set up factories: Student, Payment, FinanceCharge, StudentInvoice, InvoiceLine, PaymentApplication, DngPaymentRequest
3. Test auth scoping (student can only see own data)
4. Test filter params
5. Test computed fields accuracy

## Success Criteria

- All endpoints covered (happy + auth + edge cases)
- Uses real database (no mocks for DB)
- Factories produce realistic data
- All tests pass
