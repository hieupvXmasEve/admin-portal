# Product Requirements Document: Teaching Assignments (Admin Portal)

## Overview

This module allows academic administrators to view, assign, and manage lecturer (`lectures`) teaching responsibilities across course offerings (`course_offerings`) for a specific semester (`semesters`). The data must be consistent with the schema defined in the database.
## Data Entities

- **Lecturer**: `lectures.id`
- **Course Offering**: `course_offerings.id`
- **Semester**: `semesters.id`
- **Curriculum Unit**: `curriculum_units.unit_id`
- **Unit**: `units.code`, `units.name`
- **Campus**: `campuses.id`
- **Department/Faculty**: `lectures.department`, `lectures.faculty`
## Functional Requirements

### 1. View Teaching Assignments

**User Story:** As an admin, I want to view all current teaching assignments by semester so I can manage teaching loads and resolve conflicts.

### Acceptance Criteria:

- System shall list all `course_offerings` grouped by `semester_id`
- Each record displays:
    - `units.code`, `units.name` (via `curriculum_unit_id`)
    - `course_offerings.section_code`
    - Assigned `lectures.first_name`, `last_name`
    - `course_offerings.schedule_days`, `schedule_time_start`, `schedule_time_end`
    - `course_offerings.delivery_mode`, `enrollment_status`
    - `course_offerings.current_enrollment`, `max_capacity`
### 2. Assign Lecturer to Course Offering

**User Story:** As an admin, I want to assign or reassign a lecturer to a specific course offering.

### Acceptance Criteria:

- System shall allow assigning `lecture_id` to `course_offerings.lecture_id`
- Assignment should be filtered by:
    - `lectures.is_available_for_assignment = 1`
    - `lectures.employment_status = 'active'`
    - Optional filters: `faculty`, `department`, `campus_id`
### 3. Conflict Detection

**User Story:** As an admin, I want to ensure that a lecturer is not assigned to overlapping class times.

### Acceptance Criteria:

- Before assigning, system shall:
    - Check for overlapping `schedule_days` and (`schedule_time_start`, `schedule_time_end`) across course offerings assigned to the same `lecture_id`
    - Prevent save if conflict exists
### 4. Search and Filter

**User Story:** As an admin, I want to quickly filter teaching assignments by lecturer, unit, or status.

### Acceptance Criteria:

- System shall support search by:
    - `units.code`, `units.name`
    - `lectures.first_name`, `last_name`
    - `course_offerings.enrollment_status`
- System shall support filter by:
    - `semester_id`
    - `campus_id`
    - `faculty`, `department`
### 5. Export Report

**User Story:** As an admin, I want to export teaching assignments for review and printing.

### Acceptance Criteria:

- System shall support export of filtered list to Excel or PDF
- Exported file must include:
    - Unit code and name
    - Lecturer name
    - Section code
    - Semester
    - Schedule days and times
## Non-Functional Requirements

- All operations must respect foreign key constraints from:
    - `course_offerings.lecture_id` → `lectures.id`
    - `course_offerings.semester_id` → `semesters.id`
    - `course_offerings.curriculum_unit_id` → `curriculum_units.id`
- Only users with valid role-permission from `campus_user_roles` and `role_permissions` can assign teaching.