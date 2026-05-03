## ADDED Requirements

### Requirement: Row click navigates to Show page
Clicking a row in the Course Offering Index table SHALL navigate to the corresponding Show page using route helpers.

#### Scenario: Click on a table row
- **WHEN** coordinator clicks anywhere on a course offering row (excluding action buttons)
- **THEN** browser SHALL navigate using `route('course-offerings.show', courseOffering.id)` (not literal URL)

### Requirement: Eye icon as visible primary action
An Eye icon button SHALL be always visible (not inside dropdown) as the primary navigation action for each row.

#### Scenario: Click eye icon
- **WHEN** coordinator clicks the Eye icon on a row
- **THEN** browser SHALL navigate using `route('course-offerings.show', courseOffering.id)`

### Requirement: Dropdown contains only mutation actions
The row dropdown menu SHALL contain only state-changing (mutation) actions. Navigation actions SHALL be removed from the dropdown.

#### Scenario: Dropdown for active course (not completed, not cancelled)
- **WHEN** coordinator opens the dropdown for a course with `course_status` not `completed` and not `cancelled`
- **THEN** dropdown SHALL show: Edit, Duplicate, Toggle Registration (Open/Close), Mark as Completed, Delete
- **THEN** dropdown SHALL NOT show: "View Details" or "View Statistics"

#### Scenario: Dropdown for completed course
- **WHEN** coordinator opens the dropdown for a course with `course_status === 'completed'`
- **THEN** dropdown SHALL show: Duplicate only
- **THEN** "Recalculate Course Result" SHALL NOT appear (moved to Scores tab inside Show page)

#### Scenario: Dropdown for cancelled course
- **WHEN** coordinator opens the dropdown for a course with `course_status === 'cancelled'`
- **THEN** dropdown SHALL show: Duplicate only

### Requirement: Lifecycle badge replaces separate status columns
The separate `enrollment_status` and `course_status` columns SHALL be merged into a single "Lifecycle" column displaying a computed badge.

#### Scenario: Course not started with open registration
- **WHEN** `course_status === 'not_started'` and `enrollment_status === 'open'`
- **THEN** lifecycle badge SHALL display "Enrolling" with default variant

#### Scenario: Course in progress
- **WHEN** `course_status === 'in_progress'`
- **THEN** lifecycle badge SHALL display "Teaching" with secondary variant

#### Scenario: Course completed
- **WHEN** `course_status === 'completed'`
- **THEN** lifecycle badge SHALL display "Completed" with outline variant

#### Scenario: Course cancelled
- **WHEN** `course_status === 'cancelled'`
- **THEN** lifecycle badge SHALL display "Cancelled" with destructive variant

#### Scenario: Course not started with closed registration
- **WHEN** `course_status === 'not_started'` and `enrollment_status === 'closed'`
- **THEN** lifecycle badge SHALL display "Scheduled" with secondary variant

### Requirement: Survey column removed from Index
The inline "Survey" column with "Create Survey" button SHALL be removed from the Index table. Survey management is accessed via the Survey tab inside the Show page.

#### Scenario: Index table columns
- **WHEN** Index page renders the data table
- **THEN** columns SHALL be: No, Unit, Unit Name, Level, Unit Type, Semester, Enrollment, Delivery Mode, Lifecycle, Lecture, Actions
- **THEN** "Survey" column SHALL NOT be present

### Requirement: Sidebar restructure
The sidebar navigation SHALL be reorganized to reduce fragmentation.

#### Scenario: Attendance Management group removed
- **WHEN** sidebar renders navigation items
- **THEN** "Attendance Management" group with "Class Sessions" and "Attendance Reports" SHALL NOT appear as a top-level group

#### Scenario: Attendance Summary in Reports
- **WHEN** sidebar renders "Reports & Analytics" group
- **THEN** it SHALL include "Attendance Summary" item (formerly "Attendance Reports") linking to the existing attendance route

#### Scenario: Course Management group
- **WHEN** sidebar renders navigation items
- **THEN** "Course Offerings & Registration" group title SHALL remain as-is (or rename to "Course Management")
- **THEN** it SHALL contain: Course Offerings, Class Schedule, Course Registration

### Requirement: All Index page navigation uses route helpers
All navigation actions in the Index page SHALL use `route(...)` helpers instead of literal URL strings.

#### Scenario: Edit action
- **WHEN** coordinator clicks "Edit" in the dropdown
- **THEN** navigation SHALL use `route('course-offerings.edit', courseOffering.id)`

#### Scenario: Duplicate action
- **WHEN** coordinator clicks "Duplicate" in the dropdown
- **THEN** the action SHALL use the appropriate route helper for the duplicate endpoint

### Requirement: Lifecycle badge acceptance criteria

#### Scenario: Badge variant mapping
- **THEN** the lifecycle badge SHALL use these exact label → variant mappings:
  | `course_status` | `enrollment_status` | Label | Badge variant |
  |-----------------|--------------------|----|---------------|
  | `not_started` | `open` | Enrolling | `default` |
  | `not_started` | `closed` | Scheduled | `secondary` |
  | `in_progress` | any | Teaching | `secondary` |
  | `completed` | any | Completed | `outline` |
  | `cancelled` | any | Cancelled | `destructive` |

#### Scenario: Grading stage badge
- **WHEN** all class sessions are `completed` AND `course_status` is NOT `completed`
- **THEN** lifecycle badge SHALL display "Grading" with `warning` variant

**Data dependency note:** The "Grading" state requires knowing class session statuses, but the Index query (`CourseOfferingController@index`) does NOT load `classSessions`. Options to resolve:
1. Add a `withCount(['classSessions as completed_sessions_count' => fn ($q) => $q->where('status', 'completed'), 'classSessions'])` to the index query — enables `completed_sessions_count === class_sessions_count` check on frontend
2. Add a model accessor `is_grading` (computed from DB, cached)
3. Skip "Grading" badge on Index page — only show it in the Show page's `CourseLifecycleHeader` where session data is available. On Index, `in_progress` courses show "Teaching" regardless of session progress.

Recommended: **Option 3** (simplest, no additional queries on list page). "Grading" is a nuanced state that belongs in the detail view.
