# Requirements Document

## Introduction

This document outlines the requirements for an Invoice Generation Wizard that automates the creation of student invoices based on specific business rules for different student types (EGC and Major/Course students). The wizard builds upon the existing Fee Management System and implements sophisticated logic for handling tuition installments, EGC level fees, scholarships, and vouchers.

The wizard ensures idempotent invoice generation, meaning it can be run multiple times without creating duplicate charges, and follows strict business rules for determining which students are eligible for invoice generation in each semester.

## Requirements

### Requirement 1: Wizard Scope and Student Filtering

**User Story:** As a billing administrator, I want the invoice generation wizard to only process eligible students for the current or future semesters, so that invoices are generated according to business rules.

#### Acceptance Criteria

1. WHEN selecting a semester THEN the system SHALL only allow selection of current or future semesters
2. WHEN processing students THEN the system SHALL only include students belonging to the current campus
3. WHEN filtering students THEN the system SHALL only process students with current status of `intake_pre_uni_gc` or `intake_course`
4. WHEN generating invoices THEN the system SHALL ensure unique constraint of one invoice per student per semester
5. WHEN running the wizard multiple times THEN the system SHALL be idempotent and not create duplicate invoices
6. WHEN a student doesn't meet eligibility criteria THEN the system SHALL skip that student and log the reason

### Requirement 2: EGC Student Invoice Generation (Group A)

**User Story:** As a billing administrator, I want to generate invoices for EGC students based on their current level progression, so that they are charged for the appropriate number of levels per semester.

#### Acceptance Criteria

1. WHEN processing EGC students THEN the system SHALL identify students with current status `intake_pre_uni_gc`
2. WHEN determining level fees THEN the system SHALL use `gc_current_level` and `gc_total_levels` to calculate required levels
3. WHEN `gc_current_level >= gc_total_levels - 1` THEN the system SHALL charge for 1 level only
4. WHEN `gc_current_level < gc_total_levels - 1` THEN the system SHALL charge for 2 levels (current and next)
5. WHEN creating level charges THEN the system SHALL create `EGC_LEVEL_FEE` finance charges for each required level
6. WHEN determining level fees THEN the system SHALL use `unit.base_fee` for each level (retake fees not considered in wizard)
7. WHEN creating charges THEN the system SHALL ensure idempotency using key (student, semester, level)
8. WHEN a student has `gc_current_level = 5` THEN the system SHALL only charge for level 5 (final level)

### Requirement 3: Major/Course Student Invoice Generation (Group B)

**User Story:** As a billing administrator, I want to generate tuition invoices for Major/Course students based on their tuition plan installments, so that they are charged according to their payment schedule.

#### Acceptance Criteria

1. WHEN processing Major students THEN the system SHALL identify students with current status `intake_course`
2. WHEN determining tuition eligibility THEN the system SHALL only create tuition for semesters >= `intake_major`
3. WHEN selected semester < `intake_major` THEN the system SHALL not create tuition charges
4. WHEN selected semester >= `intake_major` THEN the system SHALL create tuition according to installment schedule
5. WHEN determining installment number THEN the system SHALL count existing tuition installments from `semester >= intake_major`
6. WHEN calculating next installment THEN the system SHALL use `installment_next = count(existing tuition installments) + 1`
7. WHEN `installment_next > N` (total installments in plan) THEN the system SHALL skip tuition creation
8. WHEN creating tuition charges THEN the system SHALL use charge type `MAJOR_TUITION`
9. WHEN creating charges THEN the system SHALL ensure idempotency using key (student, semester, installment_no or tuition_plan_term_id)

### Requirement 4: Scholarship Application

**User Story:** As a billing administrator, I want scholarships to be automatically applied to eligible student invoices, so that students receive appropriate discounts across all applicable semesters.

#### Acceptance Criteria

1. WHEN generating invoices for any student THEN the system SHALL check for scholarship awards in `student_scholarship_awards` table
2. WHEN a student has scholarship awards THEN the system SHALL use the most recent award (latest `awarded_at` date)
3. WHEN a student has a scholarship award THEN the system SHALL create `SCHOLARSHIP_CREDIT` (negative amount)
4. WHEN applying scholarships to Major students THEN the system SHALL apply the scholarship to all semesters in their tuition plan
5. WHEN applying scholarships THEN the system SHALL NOT check scholarship validity dates or due dates
6. WHEN creating scholarship credits THEN the system SHALL ensure idempotency using key (award + semester)
7. WHEN creating scholarship credits THEN the system SHALL link to the original scholarship award
8. WHEN multiple scholarship awards exist for a student THEN the system SHALL only use the most recent one

### Requirement 5: Voucher Application

**User Story:** As a billing administrator, I want vouchers to be automatically applied to eligible student invoices, so that students receive voucher benefits.

#### Acceptance Criteria

1. WHEN processing any student THEN the system SHALL check for unused voucher applications with `invoice_id IS NULL`
2. WHEN a voucher is a discount voucher THEN the system SHALL create `VOUCHER_CREDIT` (negative amount) and invoice line
3. WHEN a voucher is informational only THEN the system SHALL only update `voucher_applications.invoice_id` without creating credit
4. WHEN applying voucher discounts THEN the system SHALL update `voucher_applications.invoice_id` to link to the invoice
5. WHEN creating voucher credits THEN the system SHALL ensure idempotency using voucher application ID
6. WHEN multiple vouchers exist for a student THEN the system SHALL apply all eligible vouchers
7. WHEN a voucher has expired THEN the system SHALL not apply it and log a warning

### Requirement 6: Invoice Creation and Management

**User Story:** As a billing administrator, I want the wizard to create properly structured invoices with all charges and credits, so that students receive accurate billing statements.

#### Acceptance Criteria

1. WHEN generating invoices THEN the system SHALL create or update `student_invoices` with unique constraint (student_id, semester_id)
2. WHEN creating invoice charges THEN the system SHALL create corresponding `finance_charges` records
3. WHEN creating charges THEN the system SHALL create corresponding `invoice_lines` for display
4. WHEN calculating invoice totals THEN the system SHALL sum all positive charges and subtract all credits
5. WHEN an invoice already exists THEN the system SHALL add new charges without duplicating existing ones
6. WHEN all charges are processed THEN the system SHALL update invoice totals
7. WHEN invoice generation fails for a student THEN the system SHALL log the error and continue with other students

### Requirement 7: Wizard User Interface

**User Story:** As a billing administrator, I want an intuitive wizard interface to configure and run invoice generation, so that I can efficiently manage the billing process.

#### Acceptance Criteria

1. WHEN accessing the wizard THEN the system SHALL display a step-by-step interface
2. WHEN selecting semester THEN the system SHALL only show current and future semesters
3. WHEN configuring options THEN the system SHALL allow selection of student groups (EGC, Major, or both)
4. WHEN reviewing settings THEN the system SHALL display a summary of selected options and estimated student count
5. WHEN running generation THEN the system SHALL display progress indicators and real-time status
6. WHEN generation completes THEN the system SHALL display a summary report with counts and any errors
7. WHEN errors occur THEN the system SHALL display detailed error messages with student information
8. WHEN generation is successful THEN the system SHALL provide links to view generated invoices

### Requirement 8: Idempotency and Error Handling

**User Story:** As a billing administrator, I want the wizard to handle errors gracefully and allow safe re-running, so that I can recover from issues without creating duplicate charges.

#### Acceptance Criteria

1. WHEN running the wizard multiple times THEN the system SHALL not create duplicate charges for the same student/semester/charge type
2. WHEN a charge already exists THEN the system SHALL skip creation and log the skip reason
3. WHEN database errors occur THEN the system SHALL rollback the current student's transaction and continue with others
4. WHEN validation errors occur THEN the system SHALL log the error and skip the problematic student
5. WHEN the wizard is interrupted THEN the system SHALL allow resuming from where it left off
6. WHEN re-running after partial completion THEN the system SHALL only process students who don't have complete invoices
7. WHEN critical errors occur THEN the system SHALL stop processing and report the issue

### Requirement 9: Audit Trail and Logging

**User Story:** As a billing administrator, I want comprehensive logging of all wizard activities, so that I can track what was generated and troubleshoot issues.

#### Acceptance Criteria

1. WHEN running the wizard THEN the system SHALL log the start time, user, and configuration
2. WHEN processing each student THEN the system SHALL log the student ID and actions taken
3. WHEN creating charges THEN the system SHALL log the charge type, amount, and source
4. WHEN skipping students THEN the system SHALL log the reason for skipping
5. WHEN errors occur THEN the system SHALL log detailed error information including stack traces
6. WHEN wizard completes THEN the system SHALL log completion time and summary statistics
7. WHEN viewing audit logs THEN the system SHALL provide filtering and search capabilities

### Requirement 10: Business Rule Validation

**User Story:** As a billing administrator, I want the wizard to validate business rules before processing, so that invoices are generated according to institutional policies.

#### Acceptance Criteria

1. WHEN processing EGC students THEN the system SHALL validate that `gc_current_level` is within valid range (0-5)
2. WHEN processing Major students THEN the system SHALL validate that tuition plans exist for their curriculum
3. WHEN applying scholarships THEN the system SHALL validate scholarship validity dates
4. WHEN applying vouchers THEN the system SHALL validate voucher eligibility and usage limits
5. WHEN creating charges THEN the system SHALL validate that amounts are positive (except for credits)
6. WHEN semester is selected THEN the system SHALL validate that it's not in the past
7. WHEN business rules are violated THEN the system SHALL log the violation and skip the affected student

### Requirement 11: Performance and Scalability

**User Story:** As a billing administrator, I want the wizard to handle large numbers of students efficiently, so that invoice generation completes in reasonable time.

#### Acceptance Criteria

1. WHEN processing large student populations THEN the system SHALL process students in batches of 100
2. WHEN running long operations THEN the system SHALL provide progress updates every 10 students processed
3. WHEN database operations are intensive THEN the system SHALL use appropriate indexing and query optimization
4. WHEN memory usage is high THEN the system SHALL use chunked processing to avoid memory exhaustion
5. WHEN processing takes longer than expected THEN the system SHALL allow background processing with status updates
6. WHEN concurrent wizard runs are attempted THEN the system SHALL prevent conflicts with appropriate locking
7. WHEN system resources are limited THEN the system SHALL gracefully handle resource constraints

### Requirement 12: Integration with Existing Systems

**User Story:** As a billing administrator, I want the wizard to integrate seamlessly with existing fee management components, so that generated invoices work with the rest of the system.

#### Acceptance Criteria

1. WHEN creating invoices THEN the system SHALL use existing invoice numbering schemes
2. WHEN creating charges THEN the system SHALL use existing finance charge types and structures
3. WHEN applying scholarships THEN the system SHALL use existing scholarship award records
4. WHEN applying vouchers THEN the system SHALL use existing voucher application records
5. WHEN generating reports THEN the system SHALL integrate with existing reporting infrastructure
6. WHEN permissions are checked THEN the system SHALL use existing permission system
7. WHEN audit trails are created THEN the system SHALL use existing audit logging mechanisms
