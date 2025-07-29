# Requirements Document

## Introduction

The Lecturer Course Assessments feature enables lecturers to comprehensively manage assessment components for their course offerings, including grading workflows, progress monitoring, and performance reporting. This system provides two main interfaces: an assessment management page for handling individual assessments and grading, and a comprehensive assessment report for analyzing student performance across all course assessments.

## Requirements

### Requirement 1

**User Story:** As a lecturer, I want to view and manage all assessment components for my course offering, so that I can maintain the assessment structure and monitor grading progress.

#### Acceptance Criteria

1. WHEN a lecturer accesses `/lecturer/teaching/courses/:id/assessments` THEN the system SHALL display all assessment components linked to the course offering's syllabus
2. WHEN displaying assessment components THEN the system SHALL show component name, type, weight, and requirement status for final exam eligibility
3. WHEN displaying assessment details THEN the system SHALL show sub-components with their individual weights and grading statistics
4. WHEN calculating grading statistics THEN the system SHALL aggregate data from assessment_component_detail_scores to show submission counts and grading status
5. WHEN total assessment weights are modified THEN the system SHALL validate that the total does not exceed 100%

### Requirement 2

**User Story:** As a lecturer, I want to add and modify assessment components, so that I can structure the course grading appropriately.

#### Acceptance Criteria

1. WHEN a lecturer adds a new assessment component THEN the system SHALL create a record in assessment_components table with syllabus_id linkage
2. WHEN creating assessment components THEN the system SHALL support types: quiz, assignment, project, exam, online_activity, other
3. WHEN adding assessment details THEN the system SHALL create records in assessment_component_details table
4. WHEN modifying component weights THEN the system SHALL validate total weight constraints across all components
5. WHEN saving assessment structure THEN the system SHALL ensure data integrity across assessment_components and assessment_component_details tables

### Requirement 3

**User Story:** As a lecturer, I want to grade student submissions efficiently, so that I can provide timely feedback and maintain accurate records.

#### Acceptance Criteria

1. WHEN a lecturer selects grade by student mode THEN the system SHALL display all assessments for the selected student
2. WHEN a lecturer selects grade by component mode THEN the system SHALL display all students for the selected assessment component
3. WHEN entering grades THEN the system SHALL update points_earned, percentage_score, and letter_grade in assessment_component_detail_scores
4. WHEN changing score status THEN the system SHALL support transitions: draft → provisional → final
5. WHEN completing grading THEN the system SHALL record graded_by_lecture_id and graded_at timestamp
6. WHEN processing late submissions THEN the system SHALL calculate minutes_late and apply late_penalty_applied
7. WHEN handling late excuses THEN the system SHALL support late_excuse approval workflow

### Requirement 4

**User Story:** As a lecturer, I want to manage academic integrity concerns, so that I can maintain academic standards and handle plagiarism cases appropriately.

#### Acceptance Criteria

1. WHEN suspected plagiarism is identified THEN the system SHALL allow flagging with plagiarism_suspected = true
2. WHEN recording plagiarism concerns THEN the system SHALL capture plagiarism_score and plagiarism_notes
3. WHEN managing integrity cases THEN the system SHALL track integrity_status throughout the workflow
4. WHEN handling appeals THEN the system SHALL record appeal_requested, appeal_reason, and appeal_status
5. WHEN providing feedback THEN the system SHALL support instructor_feedback and private_notes fields

### Requirement 5

**User Story:** As a lecturer, I want to apply score adjustments and exclusions, so that I can handle special circumstances and bonus points appropriately.

#### Acceptance Criteria

1. WHEN awarding bonus points THEN the system SHALL record bonus_points and bonus_reason
2. WHEN excluding scores from calculations THEN the system SHALL set score_excluded = true with exclusion_reason
3. WHEN calculating final grades THEN the system SHALL respect score exclusions and apply bonus points
4. WHEN processing adjustments THEN the system SHALL maintain audit trail of changes
5. WHEN displaying grades THEN the system SHALL clearly indicate excluded scores and bonus applications

### Requirement 6

**User Story:** As a lecturer, I want to export assessment data, so that I can share grades and maintain external records.

#### Acceptance Criteria

1. WHEN exporting to Excel THEN the system SHALL include student information, component scores, totals, and status flags
2. WHEN filtering export data THEN the system SHALL support filtering by score_status and exclusion flags
3. WHEN generating exports THEN the system SHALL include all relevant fields from assessment_component_detail_scores
4. WHEN creating export files THEN the system SHALL format data appropriately for external use
5. WHEN handling large datasets THEN the system SHALL optimize export performance

### Requirement 7

**User Story:** As a lecturer, I want to view comprehensive assessment reports, so that I can analyze student performance and identify trends across the course.

#### Acceptance Criteria

1. WHEN accessing `/lecturer/teaching/courses/:id/assessments/report` THEN the system SHALL display student performance summary statistics
2. WHEN showing enrollment statistics THEN the system SHALL count total enrolled students from course_registrations
3. WHEN displaying score distribution THEN the system SHALL calculate average, highest, lowest scores per component
4. WHEN showing completion tracking THEN the system SHALL count submissions by status and score_status
5. WHEN identifying missing submissions THEN the system SHALL highlight students without scores

### Requirement 8

**User Story:** As a lecturer, I want to view a comprehensive grade table, so that I can see all student performance in a consolidated format.

#### Acceptance Criteria

1. WHEN displaying the grade table THEN the system SHALL show one row per student with all assessment components
2. WHEN calculating component scores THEN the system SHALL use percentage_score from assessment_component_detail_scores
3. WHEN applying weights THEN the system SHALL use weight from assessment_components for weighted calculations
4. WHEN filtering scores THEN the system SHALL only include score_status = 'final' and score_excluded = false
5. WHEN calculating totals THEN the system SHALL use formula: SUM(percentage_score * weight / 100)
6. WHEN handling missing scores THEN the system SHALL appropriately display NULL values
7. WHEN showing letter grades THEN the system SHALL apply institutional grade scale conversion

### Requirement 9

**User Story:** As a lecturer, I want to identify students requiring attention, so that I can provide appropriate academic support and intervention.

#### Acceptance Criteria

1. WHEN analyzing student performance THEN the system SHALL identify at-risk students based on low scores and missing submissions
2. WHEN monitoring academic integrity THEN the system SHALL highlight students with plagiarism_suspected = true
3. WHEN tracking appeals THEN the system SHALL show students with active appeal_requested status
4. WHEN identifying exceptional students THEN the system SHALL highlight consistently high performers
5. WHEN monitoring late submissions THEN the system SHALL track students with frequent late_penalty_applied

### Requirement 10

**User Story:** As a lecturer, I want to generate detailed reports with visualizations, so that I can present performance data effectively and make data-driven decisions.

#### Acceptance Criteria

1. WHEN generating visualizations THEN the system SHALL create grade distribution histograms using percentage_score data
2. WHEN comparing components THEN the system SHALL provide component performance comparison charts
3. WHEN tracking progress THEN the system SHALL show student progress over time using graded_at timestamps
4. WHEN exporting reports THEN the system SHALL support PDF format with charts and summary statistics
5. WHEN filtering reports THEN the system SHALL support date range, score status, and student group filters