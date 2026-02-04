# Payment Management System - Requirements

## Overview

The Payment Management System provides simple payment tracking and allocation management for the Swinx fee management system following the "pay first, no debt" policy. The system handles payment processing from Excel imports with basic allocation tracking and credit management for future invoices.

**Core Flow**: Collect payment → Allocate to invoice/charges → If surplus, keep credit or apply to future invoices.

## User Stories

### 1. Payment List Management

**As a Finance Staff member**, I want to view and filter all payments so that I can track payment status and manage allocations.

**Acceptance Criteria:**
- 1.1 Display paginated list of all payments with key information (amount, date, source, student, allocation status)
- 1.2 Filter payments by source/method (Excel import, future: DNG webhook)
- 1.3 Filter payments by external reference/transaction ID
- 1.4 Filter payments by student ID/name
- 1.5 Filter payments by date range
- 1.6 Show allocation status: allocated / unallocated / partial (computed values)
  - allocated_amount = SUM(payment_allocations.amount)
  - unallocated_amount = payment.amount - allocated_amount
  - status: allocated (unallocated_amount = 0), partial (0 < unallocated_amount < payment.amount), unallocated (allocated_amount = 0)
- 1.7 Show remaining unallocated amount for each payment
- 1.8 Provide quick navigation to Payment Detail page
- 1.9 Simple, clean interface focused on essential information only

### 2. Payment Detail Management

**As a Finance Staff member**, I want to view payment details and manage allocations so that I can ensure accurate financial tracking.

**Acceptance Criteria:**
- 2.1 Display complete payment information (amount, date, source, external_ref, student details)
- 2.2 Show all current allocations with target charges/invoices and amounts
- 2.3 Allow manual allocation of unallocated amount to specific finance_charges (invoices are used as UI filter to show charges belonging to that invoice)
- 2.4 Allow reallocation of existing allocations between finance_charges
- 2.5 Basic validation: total allocations cannot exceed payment amount
- 2.6 Basic validation: cannot allocate to charges that are already fully paid
- 2.7 Show remaining unallocated balance prominently
- 2.8 Simple logging: record who, when, and reason for allocation/reallocation actions
- 2.9 Default allocation suggestion: allocate to student's most recent invoice or selected invoice

### 3. Unapplied Credit Monitor

**As a Finance Staff member**, I want to monitor unallocated payment balances so that I can manage surplus payments.

**Acceptance Criteria:**
- 3.1 Display list of payments with unallocated balances (unallocated_amount > 0)
- 3.2 Show unallocated amount and total payment amount for each entry
- 3.3 Filter by student and date range
- 3.4 Action: Apply credit to future invoices (draft status) of the same student (only if draft invoice exists, otherwise show link to create invoice)
- 3.5 Action: Keep credit unallocated for future use
- 3.6 Simple interface focused on credit application workflow
- 3.7 No refund functionality - credits can only be applied to future charges

### 4. Excel Payment Import

**As a Finance Staff member**, I want to import payments from Excel files so that I can efficiently process bulk payments.

**Acceptance Criteria:**
- 4.1 Upload Excel file with payment data
- 4.2 Preview imported data before committing to database
- 4.3 Validate required fields: student_code (map to students.id), amount, paid_at, external_ref (student_id optional if already provided)
- 4.4 Prevent duplicate payments based on unique constraint (source, external_ref)
- 4.5 Show validation errors with specific row references
- 4.6 Provide import summary (successful, failed, duplicates)
- 4.7 Set source = "excel" for all imported payments
- 4.8 No automatic allocation after import - manual allocation required
- 4.9 Simple Excel format with defined column mapping

## Business Rules

### Payment Processing Rules
- Each payment must have a unique (source, external_ref) combination within the system
- Total allocations cannot exceed the payment amount
- Payments are processed following "pay first, no debt" policy
- Source field prepared for future expansion (excel, dng_webhook)

### Allocation Rules
- All allocations target finance_charges records directly
- Invoices are used as UI filters to group and display related charges
- Simple default rule: allocate to selected invoice or student's most recent invoice
- Manual allocation and reallocation with basic logging
- No complex auto-allocation rules or priorities
- No proportional allocation across multiple charges

### Credit Management Rules
- Unallocated credits can be applied to future invoices (draft status only)
- Credits can be kept unallocated indefinitely
- No refund processing - all credits must remain in system
- No aging rules or approval workflows

### Access Rules
- Finance staff have full access to payment management
- Student services staff have read-only access for inquiry support
- Simple role-based access without complex permission matrix

## Technical Requirements

### Database Requirements
- Integration with existing finance_charges and student_invoices tables
- Payment table with source field (excel, future: dng_webhook)
- Payment allocation table linking payments to finance_charges (invoice chỉ là UI filter, không lưu vào allocation
- Simple audit log for allocation changes

### Performance Requirements
- Payment list page loads within 2 seconds for normal data volumes
- Excel import handles up to 500 payments per batch
- Simple search and filtering operations

### Integration Requirements
- Current: Excel import workflow
- Future ready: DNG webhook integration (source field prepared)
- Maintain compatibility with existing fee management system

## Success Metrics

- Efficient payment processing with minimal manual effort
- Zero duplicate payment processing
- Clear audit trail for all allocation activities
- Simple, intuitive user interface
- Accurate financial tracking with unallocated credit management
