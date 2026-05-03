## ADDED Requirements

### Requirement: Lifecycle progress bar displays current stage
The Course Offering Show page SHALL display a horizontal progress bar above the tabs showing the lifecycle stage of the course offering. Stages: Setup → Registration → Teaching → Grading → Completed.

#### Scenario: Course with no sessions and no enrollment
- **WHEN** course offering has no class sessions and `current_enrollment === 0`
- **THEN** lifecycle bar SHALL highlight "Setup" stage as active

#### Scenario: Course with enrollment but no sessions
- **WHEN** course offering has `current_enrollment > 0` but no class sessions
- **THEN** lifecycle bar SHALL highlight "Registration" stage as active

#### Scenario: Course with scheduled sessions not yet started
- **WHEN** course offering has class sessions but none have status `in_progress` or `completed`
- **THEN** lifecycle bar SHALL highlight "Registration" stage as active (sessions scheduled but teaching not begun)

#### Scenario: Course with in-progress or partially completed sessions
- **WHEN** at least one session has status `in_progress` or `completed` but NOT all sessions are `completed`
- **THEN** lifecycle bar SHALL highlight "Teaching" stage as active
- **THEN** progress text SHALL show "X/Y sessions completed"

#### Scenario: Course with all sessions completed
- **WHEN** all class sessions have status `completed` AND `course_status !== 'completed'`
- **THEN** lifecycle bar SHALL highlight "Grading" stage as active

#### Scenario: Course marked as completed
- **WHEN** `course_status === 'completed'`
- **THEN** lifecycle bar SHALL highlight "Completed" stage with all stages marked as done

#### Scenario: Course cancelled
- **WHEN** `course_status === 'cancelled'`
- **THEN** lifecycle bar SHALL show "Cancelled" with a destructive badge and all stages greyed out

### Requirement: Lifecycle stage is computed purely from frontend data
The lifecycle stage SHALL be determined by a computed property using data already present in the `courseOffering` prop (class_sessions statuses, current_enrollment, course_status). No additional backend endpoint SHALL be required.

#### Scenario: No new API call for lifecycle
- **WHEN** Show page loads with default courseOffering data
- **THEN** lifecycle stage SHALL be computed without any additional HTTP request

### Requirement: Warnings bar shows pending action items
Below the lifecycle progress bar, a warnings bar SHALL display contextual alerts for items requiring coordinator attention. Warnings SHALL only appear when relevant.

#### Scenario: Sessions without attendance
- **WHEN** one or more class sessions have status `completed` but have no attendance records (or `attendance_percentage` is null/0)
- **THEN** a warning SHALL display: "X session(s) completed without attendance records"

#### Scenario: Survey not created
- **WHEN** course offering has no associated survey (form_targets is empty) AND course has started teaching
- **THEN** a warning SHALL display: "Course survey not yet created"

#### Scenario: All sessions completed but course not finalized
- **WHEN** all sessions are `completed` AND `course_status !== 'completed'`
- **THEN** a warning SHALL display: "All sessions completed — ready to mark course as completed"

#### Scenario: No warnings
- **WHEN** no pending action items exist
- **THEN** the warnings bar SHALL not be rendered (hidden entirely, not empty)

### Requirement: Lifecycle header is a standalone component
The lifecycle header SHALL be implemented as `CourseLifecycleHeader.vue` accepting the course offering data as props. It SHALL be reusable outside the Show page if needed.

#### Scenario: Component interface
- **WHEN** CourseLifecycleHeader is rendered
- **THEN** it SHALL accept props:
  ```typescript
  interface Props {
      courseOffering: {
          course_status: string
          current_enrollment: number
          class_sessions: Array<{ status: string; attendance_percentage: number | null }>
          form_targets?: Array<{ id: number }>  // survey targets
      }
  }
  ```
  Note: Laravel serializes relations as snake_case — `class_sessions` not `classSessions`, `form_targets` not `formTargets`. Match existing `Show.vue` patterns.
- **THEN** it SHALL emit no events (display only)

#### Scenario: Lifecycle badge labels
- **THEN** stage labels SHALL be: "Setup", "Registration", "Teaching", "Grading", "Completed"
- **THEN** cancelled state label SHALL be: "Cancelled"
- **THEN** progress bar SHALL use shadcn-vue `Progress` or custom step indicator with completed/active/pending states
