# Requirements Document

## Introduction

The Teaching Assignments module is an administrative feature that enables academic administrators to efficiently manage lecturer teaching responsibilities across course offerings within specific semesters. This system ensures proper assignment of lecturers to courses while preventing scheduling conflicts and maintaining data consistency with the existing database schema. The module integrates with the existing academic management system, utilizing relationships between lecturers, course offerings, semesters, curriculum units, and campus data.

## Requirements

### Requirement 1

**User Story:** As an academic administrator, I want to view all current teaching assignments organized by semester, so that I can effectively manage teaching loads and identify potential conflicts.

#### Acceptance Criteria

1. WHEN an administrator accesses the teaching assignments module THEN the system SHALL display all course offerings grouped by semester
2. WHEN displaying course offerings THEN the system SHALL show unit code and name via curriculum_unit_id relationship
3. WHEN displaying course offerings THEN the system SHALL show section code, assigned lecturer name, schedule details, delivery mode, and enrollment information
4. WHEN displaying assignments THEN the system SHALL include current enrollment count and maximum capacity for each course offering
5. WHEN no assignments exist for a semester THEN the system SHALL display an appropriate empty state message

### Requirement 2

**User Story:** As an academic administrator, I want to assign or reassign lecturers to specific course offerings, so that I can ensure proper teaching coverage for all courses.

#### Acceptance Criteria

1. WHEN assigning a lecturer to a course offering THEN the system SHALL update the lecture_id field in the course_offerings table
2. WHEN selecting lecturers for assignment THEN the system SHALL only display lecturers where is_available_for_assignment equals 1
3. WHEN selecting lecturers for assignment THEN the system SHALL only display lecturers where employment_status equals 'active'
4. WHEN filtering available lecturers THEN the system SHALL provide optional filters by faculty, department, and campus_id
5. WHEN an assignment is successfully saved THEN the system SHALL display a confirmation message
6. WHEN an assignment fails due to database constraints THEN the system SHALL display an appropriate error message

### Requirement 3

**User Story:** As an academic administrator, I want the system to prevent scheduling conflicts when assigning lecturers, so that no lecturer is double-booked for overlapping class times.

#### Acceptance Criteria

1. WHEN attempting to assign a lecturer to a course offering THEN the system SHALL check for existing assignments with overlapping schedules
2. WHEN checking for conflicts THEN the system SHALL compare schedule_days and time ranges (schedule_time_start to schedule_time_end)
3. WHEN a scheduling conflict is detected THEN the system SHALL prevent the assignment and display a detailed conflict message
4. WHEN a scheduling conflict is detected THEN the system SHALL show the conflicting course details including unit code, section, and time
5. WHEN no conflicts exist THEN the system SHALL allow the assignment to proceed

### Requirement 4

**User Story:** As an academic administrator, I want to search and filter teaching assignments efficiently, so that I can quickly locate specific assignments or lecturers.

#### Acceptance Criteria

1. WHEN using the search functionality THEN the system SHALL support searching by unit code and unit name
2. WHEN using the search functionality THEN the system SHALL support searching by lecturer first name and last name
3. WHEN using the search functionality THEN the system SHALL support searching by enrollment status
4. WHEN using filters THEN the system SHALL provide filtering options by semester, campus, faculty, and department
5. WHEN search or filter criteria are applied THEN the system SHALL update the display in real-time
6. WHEN clearing search or filter criteria THEN the system SHALL restore the full list of assignments

### Requirement 5

**User Story:** As an academic administrator, I want to export teaching assignment reports, so that I can review assignments offline and share information with other stakeholders.

#### Acceptance Criteria

1. WHEN exporting assignments THEN the system SHALL support both Excel and PDF formats
2. WHEN exporting assignments THEN the system SHALL include unit code, unit name, lecturer name, section code, semester, and schedule information
3. WHEN exporting with active filters THEN the system SHALL only export the filtered results
4. WHEN export is complete THEN the system SHALL provide a download link or automatically trigger the download
5. WHEN export fails THEN the system SHALL display an appropriate error message

### Requirement 6

**User Story:** As an academic administrator, I want the system to maintain data integrity and respect user permissions, so that only authorized users can make teaching assignments and all data remains consistent.

#### Acceptance Criteria

1. WHEN performing any assignment operation THEN the system SHALL respect all foreign key constraints between course_offerings, lectures, semesters, and curriculum_units
2. WHEN a user attempts to access the teaching assignments module THEN the system SHALL verify the user has appropriate permissions via campus_user_roles and role_permissions
3. WHEN unauthorized access is attempted THEN the system SHALL redirect to an appropriate error page or login screen
4. WHEN database operations fail due to constraint violations THEN the system SHALL provide meaningful error messages to the user
5. WHEN concurrent users modify the same assignment THEN the system SHALL handle conflicts gracefully and notify users of changes
