# Implementation Plan

- [x]   1. Set up backend foundation and API routes

    - Create assessment management routes in routes/api.php
    - Set up route permissions and middleware for lecturer access
    - Create basic controller structure with proper authorization
    - _Requirements: 1.1, 2.1_

- [x]   2. Implement assessment data retrieval and structure
- [x] 2.1 Create AssessmentManagementService for core business logic

    - Implement getAssessmentStructure method to retrieve course assessment hierarchy
    - Add methods for calculating grading statistics and submission counts
    - Create weight validation logic for assessment components
    - _Requirements: 1.1, 1.2, 1.3, 2.4_

- [x] 2.2 Enhance AssessmentComponent model with additional methods

    - Add getGradingStatistics method to aggregate score data
    - Implement getSubmissionCounts for tracking submission status
    - Create validateTotalWeight method for weight constraint checking
    - _Requirements: 1.4, 2.4_

- [x] 2.3 Implement AssessmentController index method

    - Create endpoint to retrieve assessment structure for course offering
    - Include assessment components, details, and grading statistics
    - Add proper error handling and response formatting
    - _Requirements: 1.1, 1.2, 1.3_

- [ ]   3. Build assessment component management functionality
- [x] 3.1 Create form request classes for validation

    - Implement StoreAssessmentRequest with weight and type validation
    - Create UpdateAssessmentRequest with constraint checking
    - Add validation for assessment component details
    - _Requirements: 2.1, 2.2, 2.4_

- [x] 3.2 Implement assessment CRUD operations in controller

    - Create store method for new assessment components
    - Implement update method with weight validation
    - Add destroy method with proper dependency checking
    - _Requirements: 2.1, 2.2, 2.3_

- [x] 3.3 Add assessment component detail management

    - Implement methods for creating and updating assessment details
    - Add validation for detail weights within parent component
    - Create proper error handling for constraint violations
    - _Requirements: 2.3, 2.4_

- [x]   4. Implement grading interface backend
- [x] 4.1 Create grading data retrieval methods

    - Implement gradeByStudent method to show all assessments for one student
    - Create gradeByComponent method to show all students for one assessment
    - Add proper data formatting and relationship loading
    - _Requirements: 3.1, 3.2_

- [x] 4.2 Build grade update functionality

    - Create UpdateGradeRequest with score validation
    - Implement updateGrade method for individual score updates
    - Add automatic timestamp and lecturer tracking
    - _Requirements: 3.3, 3.4, 3.5_

- [x] 4.3 Implement bulk grading operations

    - Create BulkUpdateGradesRequest for multiple score updates
    - Implement bulkUpdateGrades method with transaction handling
    - Add validation for bulk operations
    - _Requirements: 3.3, 3.4, 3.5_

- [x]   5. Build late submission and penalty management
- [x] 5.1 Enhance AssessmentComponentDetailScore model

    - Add calculateLatePenalty method for penalty calculations
    - Implement applyLatePenalty method for score adjustments
    - Create methods for late excuse workflow management
    - _Requirements: 3.6, 3.7_

- [x] 5.2 Implement late submission processing in service

    - Create calculateLatePenalty method in AssessmentManagementService
    - Add logic for minutes_late calculation
    - Implement late excuse approval workflow
    - _Requirements: 3.6, 3.7_

- [x]   6. Implement academic integrity management
- [x] 6.1 Create academic integrity flagging functionality

    - Add processAcademicIntegrityFlag method to service
    - Implement plagiarism detection score recording
    - Create integrity status workflow management
    - _Requirements: 4.1, 4.2, 4.3_

- [x] 6.2 Build appeals management system

    - Implement appeal request processing
    - Add appeal status tracking and workflow
    - Create methods for instructor feedback and private notes
    - _Requirements: 4.4, 4.5_

- [x]   7. Implement score adjustments and exclusions
- [x] 7.1 Create bonus points and exclusion functionality

    - Add methods for applying bonus points with reasoning
    - Implement score exclusion with audit trail
    - Create calculateWeightedScore method considering adjustments
    - _Requirements: 5.1, 5.2, 5.3_

- [x] 7.2 Enhance grade calculation logic

    - Update final grade calculations to respect exclusions
    - Add bonus point application in score calculations
    - Implement proper audit trail for all adjustments
    - _Requirements: 5.3, 5.4, 5.5_

- [x]   8. Build assessment reporting backend
- [x] 8.1 Create AssessmentReportService for analytics

    - Implement generateOverviewStatistics for student and score statistics
    - Create calculateScoreDistribution for performance analytics
    - Add getCompletionStatistics for tracking submission status
    - _Requirements: 7.1, 7.2, 7.3, 7.4_

- [x] 8.2 Implement AssessmentReportController

    - Create overview endpoint for summary statistics
    - Implement gradeMatrix endpoint for comprehensive grade table
    - Add statistics endpoint for detailed analytics
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

- [x] 8.3 Build grade matrix generation

    - Implement generateGradeMatrix method with proper joins
    - Add weighted score calculations using component weights
    - Create proper handling for missing scores and exclusions
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7_

- [x]   9. Implement student identification and analytics
- [x] 9.1 Create student performance analysis

    - Implement identifyAtRiskStudents method for intervention identification
    - Add logic for identifying exceptional performers
    - Create monitoring for academic integrity issues
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

- [x] 9.2 Build performance visualization data

    - Create generatePerformanceAnalytics for chart data
    - Implement grade distribution histogram data
    - Add component performance comparison data
    - _Requirements: 10.1, 10.2, 10.3_

- [x]   10. Build export functionality
- [x] 10.1 Create AssessmentExportService

    - Implement exportToExcel method with proper formatting
    - Create exportToPdf method with charts and statistics
    - Add formatExportData method for data transformation
    - _Requirements: 6.1, 6.2, 6.3, 10.4, 10.5_

- [x] 10.2 Implement export controllers and routes

    - Create exportExcel endpoint with filtering options
    - Implement exportPdf endpoint with visualization options
    - Add proper file handling and response formatting
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ]   11. Build frontend assessment management interface
- [ ] 11.1 Create AssessmentManagement.vue component

    - Implement assessment component listing with hierarchical display
    - Add inline editing for weights and assessment properties
    - Create real-time weight validation with user feedback
    - _Requirements: 1.1, 1.2, 1.5, 2.4_

- [ ] 11.2 Build assessment component creation and editing

    - Create forms for adding new assessment components
    - Implement assessment detail management interface
    - Add validation feedback and error handling
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [ ]   12. Implement grading interface frontend
- [ ] 12.1 Create GradingInterface.vue component

    - Build tabular grading interface with keyboard navigation
    - Implement view mode switching (by student/by component)
    - Add real-time grade validation and feedback
    - _Requirements: 3.1, 3.2, 3.3_

- [ ] 12.2 Add advanced grading features

    - Implement bulk grading operations interface
    - Create late submission handling with penalty display
    - Add academic integrity flagging interface
    - _Requirements: 3.6, 3.7, 4.1, 4.2, 4.3_

- [ ] 12.3 Build score adjustment interface

    - Create bonus points application interface
    - Implement score exclusion management
    - Add appeals processing interface
    - _Requirements: 4.4, 4.5, 5.1, 5.2_

- [ ]   13. Build assessment reporting frontend
- [ ] 13.1 Create AssessmentReport.vue component

    - Implement statistical overview with charts using Chart.js
    - Create grade distribution visualizations
    - Add student performance matrix display
    - _Requirements: 7.1, 7.2, 7.3, 10.1, 10.2_

- [ ] 13.2 Implement comprehensive grade table

    - Create responsive grade matrix with all assessment components
    - Add sorting and filtering capabilities
    - Implement proper handling of missing scores and exclusions
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7_

- [ ] 13.3 Add student identification features

    - Create at-risk student highlighting
    - Implement exceptional student identification
    - Add academic integrity monitoring display
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

- [ ]   14. Implement export functionality frontend
- [ ] 14.1 Create export interface components

    - Build export options modal with filtering
    - Implement progress tracking for large exports
    - Add export format selection (Excel/PDF)
    - _Requirements: 6.1, 6.2, 6.3, 10.4, 10.5_

- [ ] 14.2 Add visualization export features

    - Create chart export functionality
    - Implement PDF report generation with charts
    - Add custom report filtering options
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [ ]   15. Implement comprehensive testing
- [ ] 15.1 Create unit tests for models and services

    - Write tests for AssessmentManagementService methods
    - Create tests for AssessmentReportService functionality
    - Add tests for all model enhancements and calculations
    - _Requirements: All requirements_

- [ ] 15.2 Build integration tests for API endpoints

    - Create tests for all assessment management endpoints
    - Implement tests for grading workflow scenarios
    - Add tests for reporting and export functionality
    - _Requirements: All requirements_

- [ ] 15.3 Add feature tests for complete workflows

    - Create end-to-end tests for assessment creation and management
    - Implement tests for complete grading workflows
    - Add tests for report generation and export processes
    - _Requirements: All requirements_

- [ ]   16. Performance optimization and final integration
- [ ] 16.1 Optimize database queries and caching

    - Implement query optimization for large datasets
    - Add caching for frequently accessed assessment data
    - Create database indexes for performance improvement
    - _Requirements: All requirements_

- [ ] 16.2 Final integration and user acceptance testing
    - Integrate all components and test complete workflows
    - Perform user acceptance testing with sample data
    - Fix any remaining issues and optimize user experience
    - _Requirements: All requirements_
