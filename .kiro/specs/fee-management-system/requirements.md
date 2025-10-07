# Requirements Document

## Introduction

This document outlines the requirements for a Fee Management System that handles tuition plans, billing, invoicing, and payments for students. The system imports student financial data (scholarships, vouchers, payments) from Excel files and provides an administrative interface for managing the complete billing lifecycle.

The system integrates with existing curriculum versions and semester management to provide automated billing and payment tracking through student cash wallets. This initial phase focuses on the administrative interface only.

## Requirements

### Requirement 1: Scholarship Definition Management

**User Story:** As a billing administrator, I want to create and manage scholarship definitions, so that I can establish the available scholarships before assigning them to students.

#### Acceptance Criteria

1. WHEN creating a scholarship THEN the system SHALL require a unique scholarship code
2. WHEN defining a scholarship THEN the system SHALL require scholarship name, amount, and validity period (start and end dates)
3. WHEN creating a scholarship THEN the system SHALL support both percentage-based and fixed-amount discounts
4. WHEN viewing scholarships THEN the system SHALL display all scholarship definitions with their codes, amounts, and validity status
5. WHEN editing a scholarship THEN the system SHALL allow updating amount and validity period
6. WHEN deleting a scholarship THEN the system SHALL check if it's assigned to any students and warn before deletion
7. WHEN a scholarship validity period expires THEN the system SHALL mark it as expired in the interface
8. WHEN viewing scholarship details THEN the system SHALL show how many students are currently assigned to it

### Requirement 2: Scholarship Excel Import

**User Story:** As a billing administrator, I want to import scholarship definitions from an Excel file, so that I can bulk load multiple scholarships at once.

#### Acceptance Criteria

1. WHEN importing a scholarship Excel file THEN the system SHALL validate the file format and required columns (scholarship code, name, amount, type, validity start date, validity end date)
2. WHEN processing scholarship import THEN the system SHALL validate that scholarship codes are unique
3. WHEN a duplicate scholarship code is found THEN the system SHALL provide options to skip or update the existing scholarship
4. WHEN import validation fails THEN the system SHALL display clear error messages indicating which rows have issues
5. WHEN an import is successful THEN the system SHALL display a summary of scholarships created/updated
6. WHEN viewing import history THEN the system SHALL display all previous scholarship imports with timestamps and record counts
7. WHEN importing scholarships THEN the system SHALL validate date formats and amount values

### Requirement 3: Student Scholarship Assignment

**User Story:** As a billing administrator, I want to assign scholarships to students, so that the correct discounts are applied to their invoices.

#### Acceptance Criteria

1. WHEN assigning a scholarship to a student THEN the system SHALL validate that the scholarship code exists
2. WHEN assigning a scholarship THEN the system SHALL link the student to the scholarship code
3. WHEN viewing a student's financial profile THEN the system SHALL display their assigned scholarship with details
4. WHEN a student already has a scholarship THEN the system SHALL allow replacing it with a different one
5. WHEN removing a scholarship assignment THEN the system SHALL require confirmation
6. WHEN viewing scholarship assignments THEN the system SHALL display all students assigned to each scholarship
7. WHEN a scholarship is expired THEN the system SHALL show a warning when viewing the student's assignment

### Requirement 4: Student Financial Data Excel Import

**User Story:** As a billing administrator, I want to import student financial data from an Excel file, so that I can bulk assign scholarships, vouchers, and payment history to students.

#### Acceptance Criteria

1. WHEN importing a student Excel file THEN the system SHALL validate the file format and required columns (student ID, scholarship code, voucher codes, payment amounts)
2. WHEN processing student import THEN the system SHALL validate that each student ID exists in the system
3. WHEN processing student import THEN the system SHALL validate that scholarship codes match existing scholarship definitions
4. WHEN a scholarship code does not exist THEN the system SHALL log an error and skip that assignment
5. WHEN an import is successful THEN the system SHALL create scholarship assignments for students
6. WHEN import validation fails THEN the system SHALL display clear error messages indicating which rows have issues
7. WHEN viewing import history THEN the system SHALL display all previous student imports with timestamps and record counts
8. WHEN importing data THEN the system SHALL support both creating new assignments and updating existing ones
9. IF a student identifier is not found THEN the system SHALL skip that row and log the error

### Requirement 5: Tuition Plan Management

**User Story:** As an academic administrator, I want to define tuition plans for different curriculum versions and intake periods, so that students are charged the correct tuition fees based on their program and enrollment period.

#### Acceptance Criteria

1. WHEN an administrator creates a tuition plan THEN the system SHALL associate it with a specific curriculum version and intake semester
2. WHEN defining a tuition plan THEN the system SHALL allow specification of total tuition amount and currency
3. WHEN a tuition plan is created THEN the system SHALL support defining payment terms across multiple semesters
4. WHEN defining payment terms THEN the system SHALL allow setting different amounts per term including zero-amount terms
5. IF a tuition plan exists for a curriculum version and intake THEN the system SHALL prevent duplicate plans for the same combination
6. WHEN a tuition plan is updated THEN the system SHALL maintain audit trail of changes
7. WHEN viewing tuition plans THEN the system SHALL display total amount, term breakdown, and associated curriculum information

### Requirement 6: Scholarship Application to Invoices

**User Story:** As a billing administrator, I want scholarships to be automatically applied to student invoices, so that students receive the correct discounted amounts based on their scholarship assignments.

#### Acceptance Criteria

1. WHEN generating an invoice THEN the system SHALL look up the student's assigned scholarship code
2. WHEN a student has a scholarship assignment THEN the system SHALL retrieve the scholarship definition details
3. WHEN a scholarship is valid for the invoice period THEN the system SHALL apply the scholarship amount as a discount
4. WHEN a scholarship has expired THEN the system SHALL not apply it to the invoice and log a warning
5. WHEN viewing an invoice THEN the system SHALL display the scholarship code, name, amount, and validity status
6. WHEN calculating invoice total THEN the system SHALL subtract the scholarship amount from the base tuition
7. IF a student has no scholarship assignment THEN the system SHALL charge the full tuition amount
8. IF a student's scholarship code does not match any definition THEN the system SHALL charge the full tuition and log an error
9. WHEN a percentage-based scholarship is applied THEN the system SHALL calculate the discount based on the tuition amount
10. WHEN a fixed-amount scholarship is applied THEN the system SHALL apply the exact amount as a discount

### Requirement 7: Billing Cycle Management

**User Story:** As a finance administrator, I want to manage billing cycles aligned with academic semesters, so that invoices are generated at the appropriate times throughout the academic year.

#### Acceptance Criteria

1. WHEN a billing cycle is created THEN the system SHALL associate it with a specific semester
2. WHEN defining a billing cycle THEN the system SHALL require start date, end date, and due date
3. WHEN a billing cycle is active THEN the system SHALL allow invoice generation for enrolled students
4. WHEN a semester ends THEN the system SHALL close the associated billing cycle
5. IF a billing cycle overlaps with another cycle THEN the system SHALL prevent creation to avoid conflicts
6. WHEN viewing billing cycles THEN the system SHALL display cycle status, associated semester, and invoice statistics

### Requirement 8: Student Invoice Generation

**User Story:** As a billing administrator, I want to automatically generate invoices for students based on their enrollment and tuition plans, so that students receive accurate billing statements each semester.

#### Acceptance Criteria

1. WHEN a billing cycle is initiated THEN the system SHALL generate invoices for all enrolled students
2. WHEN generating an invoice THEN the system SHALL include the student's tuition amount for that term
3. WHEN a student has course registrations THEN the system SHALL calculate and include applicable fees (EGC, retake fees)
4. WHEN a student has scholarships THEN the system SHALL automatically apply scholarship discounts to the invoice
5. WHEN an invoice is generated THEN the system SHALL assign a unique invoice number and due date
6. WHEN viewing an invoice THEN the system SHALL display all line items, discounts, and total amount due
7. WHEN an invoice is paid THEN the system SHALL update the invoice status to paid
8. IF an invoice is overdue THEN the system SHALL flag it for follow-up

### Requirement 9: Invoice Item Management

**User Story:** As a billing administrator, I want to add various types of fees to student invoices, so that all charges are properly documented and tracked.

#### Acceptance Criteria

1. WHEN creating invoice items THEN the system SHALL support multiple item types (tuition, EGC, retake, miscellaneous)
2. WHEN adding a tuition item THEN the system SHALL reference the student's tuition plan term amount
3. WHEN a student retakes a course THEN the system SHALL automatically add retake fees to the invoice
4. WHEN adding miscellaneous fees THEN the system SHALL require a description and amount
5. WHEN viewing invoice items THEN the system SHALL display item type, description, quantity, unit price, and total
6. WHEN calculating invoice total THEN the system SHALL sum all invoice items before applying discounts

### Requirement 10: Invoice Discount Application

**User Story:** As a billing administrator, I want to apply discounts from scholarships and vouchers to student invoices, so that students receive the correct reduced amounts.

#### Acceptance Criteria

1. WHEN an invoice is generated THEN the system SHALL automatically apply the student's scholarship amount as a discount
2. WHEN a student has vouchers THEN the system SHALL display voucher information on the invoice
3. WHEN applying a discount THEN the system SHALL record the discount source (scholarship or voucher)
4. WHEN a voucher is informational-only THEN the system SHALL display it without affecting the invoice amount
5. WHEN a voucher provides a discount THEN the system SHALL apply it to the invoice total
6. WHEN a discount is applied THEN the system SHALL ensure the final amount is not negative
7. WHEN viewing invoice discounts THEN the system SHALL display discount type, source, and amount

### Requirement 11: Wallet Transaction Processing

**User Story:** As a finance administrator, I want to track all wallet transactions, so that there is a complete audit trail of all financial activities.

#### Acceptance Criteria

1. WHEN a wallet transaction occurs THEN the system SHALL record transaction type (deposit, payment, refund, adjustment)
2. WHEN recording a transaction THEN the system SHALL capture amount, currency, and timestamp
3. WHEN a transaction is related to an invoice THEN the system SHALL link the transaction to that invoice
4. WHEN processing a payment THEN the system SHALL validate sufficient wallet balance
5. WHEN a transaction is completed THEN the system SHALL update the wallet balance atomically
6. WHEN viewing transactions THEN the system SHALL display chronological history with running balance
7. IF a transaction fails THEN the system SHALL rollback any balance changes and log the error

### Requirement 12: Voucher Management

**User Story:** As a billing administrator, I want to manage student vouchers, so that voucher information can be displayed on invoices and discounts can be applied.

#### Acceptance Criteria

1. WHEN viewing vouchers THEN the system SHALL display all voucher definitions and student redemptions
2. WHEN a voucher is imported THEN the system SHALL store voucher type (informational or discount), code, and value
3. WHEN generating an invoice THEN the system SHALL retrieve voucher data from the database
4. WHEN a student has informational vouchers THEN the system SHALL display them on the invoice without affecting the amount
5. WHEN a student has discount vouchers THEN the system SHALL apply the discount to the invoice
6. WHEN displaying vouchers THEN the system SHALL show voucher type, description, and value
7. WHEN viewing an invoice THEN the system SHALL clearly distinguish between informational and discount vouchers

### Requirement 13: First Semester Admission Fee Handling

**User Story:** As a billing administrator, I want to support zero-amount admission fees for the first semester, so that students who have waived fees are not incorrectly charged.

#### Acceptance Criteria

1. WHEN generating a first semester invoice THEN the system SHALL check if the admission fee is zero
2. WHEN the admission fee is zero THEN the system SHALL not add admission fee charges to the invoice
3. WHEN the admission fee is non-zero THEN the system SHALL include it in the first semester invoice
4. WHEN viewing tuition plan terms THEN the system SHALL clearly indicate which terms have zero amounts
5. WHEN calculating invoice totals THEN the system SHALL correctly handle zero-amount terms

### Requirement 14: Financial Reporting and Analytics

**User Story:** As a finance director, I want to view comprehensive financial reports, so that I can monitor revenue, outstanding balances, and payment trends.

#### Acceptance Criteria

1. WHEN viewing financial reports THEN the system SHALL display total revenue by semester
2. WHEN generating reports THEN the system SHALL show outstanding invoice amounts
3. WHEN analyzing payments THEN the system SHALL display payment collection rates using imported payment data
4. WHEN reviewing scholarships THEN the system SHALL show total scholarship amounts from imported records
5. WHEN viewing voucher analytics THEN the system SHALL display voucher usage from imported data
6. WHEN generating reports THEN the system SHALL support filtering by date range, program, and student cohort
7. WHEN exporting reports THEN the system SHALL support Excel and PDF formats

### Requirement 15: Administrative Interface

**User Story:** As a billing administrator, I want a comprehensive admin interface, so that I can manage all aspects of the fee management system.

#### Acceptance Criteria

1. WHEN accessing the admin interface THEN the system SHALL require proper authentication and authorization
2. WHEN viewing the dashboard THEN the system SHALL display key metrics (total invoices, outstanding amounts, collection rates)
3. WHEN managing tuition plans THEN the system SHALL provide CRUD operations with validation
4. WHEN managing billing cycles THEN the system SHALL provide creation, viewing, and closing operations
5. WHEN viewing invoices THEN the system SHALL provide filtering, sorting, and search capabilities
6. WHEN managing student financial data THEN the system SHALL provide import functionality and data viewing
7. WHEN performing any operation THEN the system SHALL log the action for audit purposes
8. WHEN viewing data tables THEN the system SHALL support pagination for large datasets
