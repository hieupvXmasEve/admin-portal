# Implementation Plan

This document outlines the implementation tasks for the Fee Management System. Each task builds incrementally on previous tasks, following test-driven development practices where appropriate.

## Task List

- [x]   1. Database migrations and models for scholarship management
    - Create migrations for `scholarship_definitions` and `student_scholarship_awards` tables
    - Create Eloquent models with relationships and validation
    - _Requirements: 1.1, 1.2, 1.3_

- [x]   2. Scholarship CRUD functionality
- [x] 2.1 Create ScholarshipService with business logic
    - Implement createScholarship, updateScholarship, deleteScholarship methods
    - Add validation for code uniqueness and date ranges
    - _Requirements: 1.1, 1.2, 1.5_

- [x] 2.2 Create ScholarshipController with Inertia responses
    - Implement index, create, store, show, edit, update, destroy methods
    - Pass data as Inertia props to Vue components
    - _Requirements: 1.1, 1.4, 1.8_

- [x] 2.3 Create Vue components for scholarship management
    - Build Scholarships/Index.vue with DataTable
    - Build Scholarships/Create.vue and Edit.vue forms
    - Build Scholarships/Show.vue detail view
    - _Requirements: 1.4, 1.8_

- [x] 2.4 Add form validation with Zod schemas
    - Create TypeScript interfaces for scholarship data
    - Implement client-side validation with Vee-validate
    - _Requirements: 1.2_

- [ ]   3. Scholarship Excel import functionality
- [ ] 3.1 Create ImportService for scholarship imports
    - Implement file validation and parsing logic
    - Add error handling and result reporting
    - _Requirements: 2.1, 2.2, 2.4, 2.5_

- [ ] 3.2 Create import controller and routes
    - Implement scholarships import endpoint
    - Add template download functionality
    - _Requirements: 2.1, 2.6_

- [ ] 3.3 Create import UI components
    - Build Imports/Scholarships.vue with file uploader
    - Display import history and results
    - _Requirements: 2.5, 2.6, 2.7_

- [x]   4. Student scholarship assignment
- [x] 4.1 Implement scholarship assignment logic
    - Add assignScholarshipToStudent method to ScholarshipService
    - Implement validation for existing scholarships
    - _Requirements: 3.1, 3.2, 3.4_

- [x] 4.2 Create assignment UI
    - Build StudentScholarships/Index.vue to view assignments
    - Build StudentScholarships/Assign.vue form
    - _Requirements: 3.3, 3.6_

- [x] 4.3 Add assignment management to scholarship detail view
    - Display assigned students in Scholarships/Show.vue
    - Add remove assignment functionality
    - _Requirements: 3.5, 3.7_

- [x]   5. Student data Excel import
- [x] 5.1 Extend ImportService for student data
    - Implement importStudentData method with scholarship assignment
    - Add payment amount processing logic
    - Add voucher redemption logic
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [x] 5.2 Create student import controller and routes
    - Implement students import endpoint
    - Add validation for student IDs and scholarship codes
    - _Requirements: 4.2, 4.3, 4.6, 4.9_

- [x] 5.3 Create student import UI
    - Build Imports/Students.vue with file uploader
    - Display import results with errors and warnings
    - _Requirements: 4.6, 4.7, 4.8_

- [ ]   6. Tuition plan management
- [ ] 6.1 Create tuition plan migrations and models
    - Create migrations for `tuition_plans` and `tuition_plan_terms` tables
    - Create models with relationships
    - _Requirements: 5.1, 5.2, 5.3_

- [ ] 6.2 Create TuitionPlanService
    - Implement createTuitionPlan, updateTuitionPlan methods
    - Add term management methods (addTerm, updateTerm)
    - Implement validation for curriculum/intake uniqueness
    - _Requirements: 5.1, 5.4, 5.5, 5.6_

- [ ] 6.3 Create TuitionPlanController with Inertia
    - Implement CRUD operations
    - Pass curriculum versions and semesters as props
    - _Requirements: 5.1, 5.7_

- [ ] 6.4 Create tuition plan Vue components
    - Build TuitionPlans/Index.vue with list view
    - Build TuitionPlans/Create.vue and Edit.vue with term management
    - Build TuitionPlans/Show.vue with term breakdown
    - _Requirements: 5.7_

- [ ]   7. Scholarship application to invoices
- [ ] 7.1 Implement scholarship lookup and validation
    - Add getStudentScholarship method to ScholarshipService
    - Implement validity period checking
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

- [ ] 7.2 Create discount calculation logic
    - Implement percentage-based discount calculation
    - Implement fixed-amount discount application
    - _Requirements: 6.9, 6.10_

- [ ] 7.3 Add scholarship display to invoice views
    - Show scholarship code, name, and amount on invoices
    - Display validity status
    - _Requirements: 6.5, 6.8_

- [ ]   8. Billing cycle management
- [ ] 8.1 Create billing cycle migrations and models
    - Create migration for `billing_cycles` table
    - Create BillingCycle model with status management
    - _Requirements: 7.1, 7.2_

- [ ] 8.2 Create BillingCycleService
    - Implement createBillingCycle, activateBillingCycle, closeBillingCycle methods
    - Add date overlap validation
    - _Requirements: 7.2, 7.3, 7.4, 7.5_

- [ ] 8.3 Create BillingCycleController with Inertia
    - Implement CRUD and status change operations
    - Pass semesters as props
    - _Requirements: 7.1, 7.6_

- [ ] 8.4 Create billing cycle Vue components
    - Build BillingCycles/Index.vue with status indicators
    - Build BillingCycles/Create.vue form
    - Build BillingCycles/Show.vue with invoice list
    - _Requirements: 7.6_

- [ ]   9. Invoice generation and management
- [ ] 9.1 Create invoice migrations and models
    - Create migrations for `student_invoices`, `invoice_items`, `invoice_discounts` tables
    - Create models with relationships and calculated attributes
    - _Requirements: 8.1, 8.2, 8.5, 8.6_

- [ ] 9.2 Create InvoiceService with generation logic
    - Implement generateInvoicesForCycle method
    - Add tuition item creation from tuition plans
    - Implement automatic scholarship discount application
    - Add invoice total calculation
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ] 9.3 Implement invoice number generation
    - Create unique invoice number generator
    - Add invoice number to invoice creation
    - _Requirements: 8.5_

- [ ] 9.4 Create InvoiceController with Inertia
    - Implement index with filtering
    - Implement show with items and discounts
    - Implement generate endpoint
    - _Requirements: 8.1, 8.6, 8.8_

- [ ] 9.5 Create invoice Vue components
    - Build Invoices/Index.vue with filters and status badges
    - Build Invoices/Show.vue with items and discounts tables
    - Build Invoices/Generate.vue form
    - _Requirements: 8.6, 8.8_

- [ ]   10. Invoice item management
- [ ] 10.1 Implement invoice item CRUD in InvoiceService
    - Add addInvoiceItem, updateInvoiceItem, deleteInvoiceItem methods
    - Implement item type validation
    - Add automatic total recalculation
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6_

- [ ] 10.2 Add item management to invoice controller
    - Implement addItem endpoint
    - Implement deleteItem endpoint
    - _Requirements: 9.1, 9.5_

- [ ] 10.3 Create invoice item UI components
    - Build InvoiceItemsTable.vue component
    - Add item creation form
    - _Requirements: 9.5_

- [ ]   11. Invoice discount management
- [ ] 11.1 Implement discount application logic
    - Add applyScholarshipDiscount method
    - Add applyVoucherDiscount method
    - Implement discount validation
    - _Requirements: 10.1, 10.2, 10.3, 10.5, 10.6_

- [ ] 11.2 Add discount management to invoice controller
    - Implement applyDiscount endpoint
    - _Requirements: 10.1, 10.7_

- [ ] 11.3 Create discount UI components
    - Build DiscountsTable.vue component
    - Add discount application form
    - _Requirements: 10.7_

- [x]   12. Student cash wallet management
- [x] 12.1 Create wallet migrations and models
    - Create migrations for `student_cash_wallets` and `wallet_transactions` tables
    - Create models with balance tracking
    - _Requirements: 11.1, 11.2, 11.7_

- [x] 12.2 Create WalletService with transaction logic
    - Implement createWallet, deposit, withdraw methods
    - Add balance validation
    - Implement atomic transaction processing with locking
    - _Requirements: 11.2, 11.3, 11.4, 11.5, 11.6_

- [x] 12.3 Create WalletController with Inertia
    - Implement show endpoint with wallet and transactions
    - Implement deposit endpoint
    - _Requirements: 11.2, 11.6_

- [x] 12.4 Create wallet Vue components
    - Build Wallets/Show.vue with balance and transaction history
    - Build deposit form
    - _Requirements: 11.2, 11.6_

- [x]   13. Wallet transaction processing
- [x] 13.1 Implement transaction recording
    - Add createTransaction method with balance snapshots
    - Implement transaction type handling
    - Add invoice linking for payments
    - _Requirements: 12.1, 12.2, 12.3, 12.5, 12.6_

- [x] 13.2 Implement payment processing
    - Add processPayment method with validation
    - Implement invoice status update on payment
    - Add rollback handling for failed payments
    - _Requirements: 12.4, 12.5, 12.7_

- [x] 13.3 Create transaction history UI
    - Build TransactionHistory.vue component
    - Display running balance
    - _Requirements: 12.6_

- [ ]   14. Voucher management
- [ ] 14.1 Create voucher migrations and models
    - Create migrations for `voucher_definitions` and `voucher_redemptions` tables
    - Create models with type handling
    - _Requirements: 13.1, 13.2_

- [ ] 14.2 Create VoucherService
    - Implement createVoucher, updateVoucher methods
    - Add voucher validation logic
    - Implement redemption tracking
    - _Requirements: 13.1, 13.3, 13.4, 13.5_

- [ ] 14.3 Create VoucherController with Inertia
    - Implement CRUD operations
    - Implement redeem endpoint
    - _Requirements: 13.1, 13.7_

- [ ] 14.4 Create voucher Vue components
    - Build Vouchers/Index.vue with type indicators
    - Build Vouchers/Create.vue and Edit.vue forms
    - Build Vouchers/Show.vue with redemption history
    - _Requirements: 13.6, 13.7_

- [ ]   15. First semester admission fee handling
- [ ] 15.1 Add zero-amount term support to tuition plans
    - Update TuitionPlanTerm to allow zero amounts
    - Add validation for zero-amount terms
    - _Requirements: 14.1, 14.2, 14.4_

- [ ] 15.2 Update invoice generation for zero-amount terms
    - Skip zero-amount admission fees in first semester
    - Add conditional logic in InvoiceService
    - _Requirements: 14.2, 14.3_

- [ ] 15.3 Update UI to display zero-amount terms
    - Show zero-amount indicators in tuition plan views
    - Display correctly in invoice generation
    - _Requirements: 14.4, 14.5_

- [ ]   16. Financial reporting and analytics
- [ ] 16.1 Create ReportService with data aggregation
    - Implement getFinancialSummary method
    - Implement getOutstandingInvoices method
    - Implement getScholarshipReport method
    - Implement getVoucherUsageReport method
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5_

- [ ] 16.2 Create ReportController with Inertia
    - Implement dashboard endpoint with metrics
    - Implement report endpoints with filtering
    - Implement export functionality
    - _Requirements: 15.1, 15.2, 15.6, 15.7_

- [ ] 16.3 Create report Vue components
    - Build Reports/Dashboard.vue with charts and metrics
    - Build Reports/FinancialSummary.vue
    - Build Reports/OutstandingInvoices.vue
    - Build Reports/Scholarships.vue
    - _Requirements: 15.1, 15.2, 15.6_

- [ ] 16.4 Implement Excel/PDF export
    - Add export functionality to ReportService
    - Create export templates
    - _Requirements: 15.7_

- [ ]   17. Administrative interface
- [ ] 17.1 Implement authentication and authorization
    - Add permission definitions to config/permission.php
    - Register permissions in PermissionServiceProvider
    - Add permission checks to controllers
    - _Requirements: 16.1, 16.7_

- [ ] 17.2 Create shared Inertia data
    - Update HandleInertiaRequests middleware
    - Share auth user and permissions
    - Share flash messages
    - _Requirements: 16.1_

- [ ] 17.3 Create dashboard with key metrics
    - Build admin dashboard page
    - Display total invoices, outstanding amounts, collection rates
    - _Requirements: 16.2_

- [ ] 17.4 Implement data table pagination
    - Add pagination to all list views
    - Implement sorting and filtering
    - _Requirements: 16.3, 16.5, 16.8_

- [ ] 17.5 Add search functionality
    - Implement search across scholarship, invoice, and student views
    - Add search filters to controllers
    - _Requirements: 16.5_

- [ ] 17.6 Implement audit logging
    - Create audit log table and model
    - Add logging to all financial operations
    - _Requirements: 16.7_

- [ ] 17.7 Create import history view
    - Build import history page
    - Display all imports with timestamps and results
    - _Requirements: 16.6_

- [ ]   18. Integration and polish
- [ ] 18.1 Add form validation error display
    - Implement error message display in all forms
    - Add field-level validation feedback
    - _Requirements: All form-related requirements_

- [ ] 18.2 Add loading states and progress indicators
    - Implement loading spinners for async operations
    - Add progress bars for imports
    - _Requirements: All UI requirements_

- [ ] 18.3 Add success/error notifications
    - Implement toast notifications for operations
    - Display flash messages from Inertia
    - _Requirements: All CRUD requirements_

- [ ] 18.4 Implement confirmation dialogs
    - Add confirmation for delete operations
    - Add confirmation for status changes
    - _Requirements: 1.6, 3.5, and other delete operations_

- [ ] 18.5 Add responsive design
    - Ensure all components work on mobile/tablet
    - Test and fix layout issues
    - _Requirements: All UI requirements_

- [ ] 18.6 Create navigation and breadcrumbs
    - Build main navigation menu
    - Add breadcrumb navigation
    - _Requirements: 16.1_

- [ ] 18.7 Add data export templates
    - Create Excel templates for imports
    - Add template download functionality
    - _Requirements: 2.6_

## Notes

- All tasks should be implemented following the existing project structure and conventions
- Use Inertia.js for passing data from Laravel to Vue components
- Follow the permission system defined in config/permission.php
- Maintain audit trails for all financial operations
- Ensure all financial transactions are atomic and use database locking where necessary
- Test with realistic data at each stage
