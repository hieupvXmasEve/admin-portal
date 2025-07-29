# 📘 PRD: Lecturer Portal — Assessments & Assessment Report

## 1. `/lecturer/teaching/courses/:id/assessments`

### Purpose

Manage all assessment components of a `course_offering`. Lecturers can view assessment structure, input grades, and monitor grading progress.

### Data Display

### 1.1 Assessment Components (from `assessment_components`)

- Fields: `id`, `syllabus_id`, `name`, `type`, `weight`, `is_required_to_sit_final_exam`
- Type options: `quiz`, `assignment`, `project`, `exam`, `online_activity`, `other`
- Weight: decimal(2) - percentage contribution to final grade
- Linked via: `course_offering.curriculum_unit_id → syllabus.curriculum_unit_id → assessment_components.syllabus_id`

### 1.2 Assessment Details (from `assessment_component_details`)

- Fields: `id`, `assessment_component_id`, `name`, `weight`
- Weight: decimal(2) - sub-component percentage within parent assessment
- Stats: number of submissions, number graded, grading status
- Aggregated from: `assessment_component_detail_scores`

### 1.3 Grading Status Summary (from `assessment_component_detail_scores`)

**Key Fields for Grading Status:**
- `score_status`: Current status of the score
- `status`: Submission status (submitted, grading, graded, returned)
- `points_earned`: decimal(2) - Raw points scored
- `percentage_score`: decimal(2) - Percentage score
- `letter_grade`: Final letter grade assigned
- `graded_at`: Timestamp when grading was completed
- `graded_by_lecture_id`: ID of lecturer who graded

**Submission Tracking:**
- `submitted_at`: When student submitted work
- `submission_attempt`: Number of attempts
- `submission_files`: Array of submitted files
- `submission_text`: Text submission content
- `submission_url`: URL submission

**Late Submission Management:**
- `is_late`: Boolean flag for late submission
- `minutes_late`: Number of minutes past deadline
- `late_penalty_applied`: decimal(2) - Penalty amount applied
- `late_excuse`: Reason for late submission
- `late_excuse_approved`: Boolean approval status

**Academic Integrity:**
- `plagiarism_suspected`: Boolean flag
- `plagiarism_score`: decimal(2) - Plagiarism detection score
- `plagiarism_notes`: Text notes about suspected plagiarism
- `integrity_status`: Academic integrity status

**Appeals & Feedback:**
- `appeal_requested`: Boolean flag
- `appeal_requested_at`: Timestamp of appeal request
- `appeal_reason`: Reason for grade appeal
- `appeal_status`: Current status of appeal
- `instructor_feedback`: Feedback from instructor
- `private_notes`: Private instructor notes

**Score Adjustments:**
- `bonus_points`: decimal(2) - Extra credit points
- `bonus_reason`: Reason for bonus points
- `score_excluded`: Boolean - exclude from calculations
- `exclusion_reason`: Reason for exclusion

### Table Relationships

```
plaintext
course_offerings → curriculum_unit_id
    → syllabus.curriculum_unit_id → assessment_components.syllabus_id
        → assessment_component_details.assessment_component_id
            → assessment_component_detail_scores.assessment_component_detail_id

```

### Actions

**Assessment Management:**
- Add new assessment component to syllabus (creates record in `assessment_components`)
- Add assessment details/sub-components (creates record in `assessment_component_details`)
- Validate total weight doesn't exceed 100%

**Grading Workflow:**
- Grade by student: Show all assessments for one student
- Grade by component: Show all students for one assessment component
- Update `points_earned`, `percentage_score`, `letter_grade` in `assessment_component_detail_scores`
- Change `score_status` (draft → provisional → final)
- Record `graded_by_lecture_id` and `graded_at` timestamp

**Late Submissions:**
- Mark submissions with `is_late = true` and calculate `minutes_late`
- Apply late penalties via `late_penalty_applied` field
- Process late excuses and approval workflow

**Academic Integrity:**
- Flag suspected plagiarism with `plagiarism_suspected = true`
- Record plagiarism detection scores and notes
- Manage integrity status workflow

**Data Export:**
- Export current grades to Excel format
- Include student info, component scores, totals, and status flags
- Filter by score status and exclusion flags

## 2. `/lecturer/teaching/courses/:id/assessments/report`

### 🎯 Purpose

Summarize and visualize student performance across all assessments in the course — useful for final grading and progress evaluation.

### 📊 Data Display

### 2.1 Overview

**Student Statistics:**
- Total enrolled students (from `course_registrations`)
- Students with submitted work per component
- Students with final grades per component

**Score Distribution:**
- Average `percentage_score` per assessment component
- Highest and lowest scores per component
- Standard deviation and grade distribution
- Pass/fail rates per component

**Completion Tracking:**
- Count of submissions by `status` (submitted, grading, graded, returned)
- Count of scores by `score_status` (draft, provisional, final)
- Missing submissions (students without scores)

### 2.2 Grade Table (row per student)

**Data Source:** Join `students` with `assessment_component_detail_scores` via `student_id`

| Student  | A1 (10%) | Quiz (20%) | Midterm (30%) | Final (40%) | Total | Grade |
| -------- | -------- | ---------- | ------------- | ----------- | ----- | ----- |
| Nguyen A | 9.0      | 17.5       | 26.0          | 34.0        | 86.5  | B+    |

**Implementation Details:**
- Use `percentage_score` from `assessment_component_detail_scores` for component scores
- Apply `weight` from `assessment_components` for weighted calculation
- Filter by `score_status = 'final'` and `score_excluded = false`
- Handle missing scores (NULL values) appropriately
- Calculate total using component weights: `SUM(percentage_score * weight / 100)`

**Grade Mapping:**
- Can sync with `final_letter_grade` from `academic_records` table
- Use `letter_grade` field from individual scores if available
- Apply institutional grade scale for letter grade conversion

### Table Relationships

```
plaintext
students → assessment_component_detail_scores
    ← assessment_component_details ← assessment_components
        ← syllabus ← curriculum_units ← course_offerings

```

- Optional link: `academic_records` for comparing or syncing final grades

### 📈 Optional Features

**Visualizations:**
- Grade distribution histogram using `percentage_score` data
- Component performance comparison charts
- Student progress tracking over time using `graded_at` timestamps

**Student Identification:**
- At-risk students: Low scores, high absence rates, missing submissions
- Exceptional students: Consistently high performers
- Students requiring attention: Late submissions, integrity issues, appeals

**Academic Integrity Monitoring:**
- List students with `plagiarism_suspected = true`
- Track `integrity_status` across assessments
- Monitor unusual scoring patterns

**Export Options:**
- PDF report with charts and summary statistics
- Excel export with detailed score breakdowns
- Include all relevant fields from `assessment_component_detail_scores`
- Filter options by date range, score status, student groups
