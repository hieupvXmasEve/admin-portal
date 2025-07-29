# 📘 PRD: Admin Portal — Lecturer Management

## 🧭 Module Overview
The Lecturer Management module allows administrators to manage lecturer profiles, teaching assignments, availability, schedules, and grading responsibilities. Core data is based on the `lectures` table and linked to `course_offerings`, `class_sessions`, `assessment_component_detail_scores`, and `academic_records`.

## 🔐 Permissions

**Required Permissions:**
- `lectures.view`
- `lectures.create`
- `lectures.edit`
- `lectures.delete`

## 📂 Admin Portal Pages

### 1. Lecturer List

**URL:** `/lectures`  
**Purpose:** Display a searchable list of all lecturers in the system.

**Features:**
- Search by name, email, or employee ID
- Filter by: campus, academic rank, employment status
- Display columns:
  - Name, Employee ID, Email, Campus
  - Academic Rank, Employment Type, Status
  - Teaching load (assigned courses / teaching hours)
- Actions:
  - View, Edit, Delete
  - Toggle Active/Inactive

### 2. Create/Edit Lecturer

**URL:**  
- `/lectures/create`  
- `/lectures/:id/edit`  

**Purpose:** Create or update lecturer records.

**Field Groups:**
- **Personal Info:** title, first_name, last_name, email, phone, gender, date_of_birth
- **Employment Info:** employee_id, campus_id, hire_date, contract_start_date, contract_end_date, employment_type, employment_status, salary, hourly_rate
- **Academic Info:** academic_rank, highest_degree, degree_field, alma_mater, graduation_year, specialization, expertise_areas
- **Preferences:** preferred_teaching_days, preferred_start_time, preferred_end_time, max_teaching_hours_per_week, can_teach_online
- **Emergency Contact:** name, phone, relationship
- **Other:** office info, certifications, languages, biography, notes

### 3. Lecturer Detail View

**URL:** `/admin/lectures/:id`  
**Purpose:** View complete lecturer information.

**Tabs:**
- **Profile:** Personal and academic information
- **Teaching Assignments:** All assigned courses
- **Timetable:** Weekly schedule (from class_sessions)
- **Assessment Activity:** List of assessments graded
- **GPA Submissions:** GPA entries submitted/verified (if applicable)

### 4. Teaching Assignments

**URL:** `/admin/lectures/:id/assignments`  
**Purpose:** Manage course assignments for the lecturer.

**Data source:** `course_offerings` where `lecture_id = :id`

**Displayed Columns:**
- Unit code + name, Semester, Section code
- Schedule (days, start & end time)
- Delivery mode, Location
- Enrollment: current / max
- Number of class sessions

**Features:**
- Filter by semester
- Actions: Unassign / Reassign / View class sessions

### 5. Lecturer Timetable

**URL:** `/admin/lectures/:id/timetable`  
**Purpose:** View weekly schedule for the lecturer.

**Data source:** `class_sessions` where `instructor_id = :id`

**Display:**
- Week view (Mon–Sun), time blocks per hour
- Each session shows: Unit + Section + Room + Type
- Colored indicators by session status: scheduled, completed, cancelled

**Features:**
- Weekly teaching hour summary
- Warning if exceeding `max_teaching_hours_per_week`
- Export timetable as PDF or printable view

## 🧩 Notes

**Main Relationships:**
- `lectures.id ← course_offerings.lecture_id ← class_sessions.instructor_id`
- `lectures.id ← assessment_component_detail_scores.graded_by_lecture_id`
- `lectures.id ← academic_records.grade_submitted_by_lecture_id`

**Best Practices:**
- Use soft deletes (`deleted_at`) for lecturers
- Enable activity logging / audit trail for changes

