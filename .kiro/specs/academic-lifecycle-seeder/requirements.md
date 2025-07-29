# Requirements Document (Updated)

## Introduction

The Academic Lifecycle Seeder is a comprehensive data generation system that simulates the complete academic journey of students within the university management system. This feature creates realistic academic data including **semester enrollments first**, then course offerings based on enrollment demand, student registrations, class sessions, attendance records, assessments, grades, and academic standings to support development, testing, and demonstration purposes.

## Requirements

### Requirement 1

**User Story:** As a developer, I want to generate realistic academic lifecycle data starting with student enrollments, so that I can test and demonstrate the complete student academic journey from enrollment to graduation with logical data consistency.

#### Acceptance Criteria

1. **CRITICAL**: WHEN the seeder is executed THEN the system SHALL create semester enrollments for all active students BEFORE creating any course offerings
2. WHEN creating semester enrollments THEN the system SHALL calculate correct semester_number for each student by comparing their admission_date with semester start dates up to the currently active semester
3. WHEN determining course offering demand THEN the system SHALL query the enrollments table to identify which curriculum_units have enrolled students before creating any course offerings
4. WHEN a curriculum_unit has no enrolled students for the current semester THEN the system SHALL NOT create a course offering for it
5. WHEN a curriculum_unit has enrolled students THEN the system SHALL create a course offering with appropriate capacity based on enrollment demand
6. WHEN course offerings are created THEN the system SHALL include semester linkage, lecturer assignment, delivery mode, schedule, location, syllabus, and assessment components
7. WHEN creating syllabus and assessment components THEN the system SHALL skip generation if records already exist
8. WHEN the seeder completes THEN the system SHALL have created a complete academic timeline with logical enrollment-to-offering relationships

### Requirement 2

**User Story:** As a developer, I want students to be automatically registered for appropriate courses based on their semester enrollments, so that the system reflects realistic enrollment patterns with proper data flow.

#### Acceptance Criteria

1. WHEN student registration is processed THEN the system SHALL only register students who have active enrollments for the current semester
2. WHEN course registrations are created THEN the system SHALL match enrolled students with course offerings based on their curriculum version and semester number
3. WHEN course registrations are created THEN the system SHALL generate corresponding academic records with "in_progress" status
4. **CRITICAL**: WHEN large courses exceed capacity THEN the system SHALL automatically split them into multiple sections with students distributed EVENLY across sections using round-robin assignment
5. WHEN students are distributed across sections THEN the system SHALL ensure maximum difference of 1 student between any two sections
6. WHEN students are assigned to sections THEN the system SHALL use round-robin algorithm: student 1 to section A, student 2 to section B, student 3 to section C, student 4 back to section A, etc.
7. WHEN students retake failed courses THEN the system SHALL automatically register them in future semesters with incremented attempt_number and appropriate retake flags (if enabled)

### Requirement 3

**User Story:** As a developer, I want realistic class sessions and attendance data, so that I can test attendance tracking and session management features.

#### Acceptance Criteria

1. WHEN class sessions are generated THEN the system SHALL calculate the number of sessions by dividing syllabus total_hours by hours_per_session
2. WHEN sessions are created THEN the system SHALL assign rooms, dates, and time slots realistically based on course schedule
3. WHEN attendance is generated THEN the system SHALL create randomized attendance records with various statuses for each student

### Requirement 4

**User Story:** As a developer, I want comprehensive assessment and grading data, so that I can test academic performance tracking and GPA calculations.

#### Acceptance Criteria

1. WHEN assessments are generated THEN the system SHALL create multiple assessment component details with due dates and weight distribution
2. WHEN student scores are created THEN the system SHALL simulate realistic performance including submission times, scores, lateness, and bonus points
3. WHEN academic records are finalized THEN the system SHALL calculate final percentages, letter grades, grade points, and completion status accurately
4. WHEN failed courses are detected THEN the system SHALL flag them for potential retake processing in future phases

### Requirement 5

**User Story:** As a developer, I want GPA calculations and academic standings, so that I can test academic progress monitoring and intervention systems.

#### Acceptance Criteria

1. WHEN GPA calculations are performed THEN the system SHALL generate both semester and cumulative GPA records
2. WHEN academic standings are determined THEN the system SHALL assign appropriate status based on GPA thresholds (e.g., probation < 2.0, honors ≥ 3.5)
3. WHEN academic issues are identified THEN the system SHALL create academic holds and intervention records as appropriate

### Requirement 6

**User Story:** As a developer, I want to simulate academic complications and special cases, so that I can test edge cases and complex academic scenarios.

#### Acceptance Criteria

1. WHEN students fail units THEN the system SHALL create retake registrations in subsequent semesters with appropriate flags
2. WHEN program changes occur THEN the system SHALL simulate program change requests and reassign students to new curriculum versions
3. WHEN graduation requirements are met THEN the system SHALL update student status to graduated and create appropriate records

### Requirement 7

**User Story:** As a system administrator, I want configurable seeder parameters, so that I can control the scope and characteristics of generated data.

#### Acceptance Criteria

1. WHEN the seeder is configured THEN the system SHALL allow specification of target semesters, student populations, and data density
2. WHEN the seeder is executed THEN the system SHALL support selective phase execution (e.g., `--phases=1,2,4`) and scoped runs (e.g., `--student-id=123`)
3. WHEN data generation begins THEN the system SHALL provide progress feedback and completion status
4. WHEN seeder execution completes THEN the system SHALL provide summary statistics of generated records including enrollment-to-offering ratios and section distribution balance

### Requirement 8

**User Story:** As a developer, I want robust error handling and validation mechanisms, so that the seeder produces clean and consistent data.

#### Acceptance Criteria

1. WHEN data is generated THEN the system SHALL validate referential integrity and enforce model constraints
2. **CRITICAL**: WHEN validating data consistency THEN the system SHALL ensure course offerings only exist when corresponding enrollments exist
3. **CRITICAL**: WHEN validating section distribution THEN the system SHALL ensure students are distributed evenly with maximum difference of 1 student between sections
4. WHEN a phase fails THEN the system SHALL rollback any changes made during that phase
5. WHEN seeding completes THEN the system SHALL provide validation reports and data quality summaries

### Requirement 9

**User Story:** As a developer, I want automated testing coverage for the seeder system, so that I can ensure correctness and prevent regressions.

#### Acceptance Criteria

1. WHEN processor classes are tested THEN the system SHALL include unit tests for all major processors with mock input
2. **PRIORITY**: WHEN testing enrollment logic THEN the system SHALL validate enrollment creation and course offering demand calculation
3. **PRIORITY**: WHEN testing section distribution THEN the system SHALL validate round-robin student assignment algorithm
4. WHEN running end-to-end tests THEN the system SHALL validate complete lifecycle generation with small datasets including enrollment → course offering → registration → section splitting flow
5. WHEN failures occur during testing THEN the system SHALL report errors clearly and support retry or rollback
