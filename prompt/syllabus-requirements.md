# 🧾 Syllabus Management Module - Requirements

## 🧠 Purpose
This module allows the academic team to define and manage course syllabi (`syllabi`) for each unit in every academic term. Each syllabus contains assessment structures, teaching staff, and grading components specific to the term.

---

## 📌 Core Features

### 1. Create/Edit/Delete Syllabus
- A syllabus is created per **unit** per **term**.
- Each syllabus includes:
  - Associated **unit** (unit_id)
  - Associated **term** (term_id)
  - Teaching **lecturer** (optional)
  - Notes or syllabus description (optional)

### 2. Define Assessment Components
- Each syllabus may contain multiple **assessment components**, such as:
  - Midterm Exam
  - Final Exam
  - Assignment
  - Quizzes, etc.
- Fields per component:
  - `name`
  - `weight` (percentage of final grade)
  - `order` (for display)
  - `is_required` (boolean: true if mandatory to pass)

### 3. Define Subcomponents (Assessment Details)
- Components can contain detailed breakdowns (e.g., multiple weekly quizzes).
- Fields per detail:
  - `title`
  - `weight`
  - `description` (optional)

### 4. Support Versioning (Optional)
- Each new syllabus per term can optionally clone and edit from the previous term.

---

## 🗃️ Database Schema

### Table: `syllabus`
| Column                       | Type        | Description                                                     |
| ---------------------------- | ----------- | --------------------------------------------------------------- |
| `id`                         | BIGINT      | Primary key                                                     |
| `version`                    | VARCHAR(10) | Optional version string                                         |
| `description`                | TEXT        | Optional syllabus description                                   |
| `total_hours`                | INTEGER     | Total contact hours for the unit                                |
| `hours_per_session`          | INTEGER     | Number of hours per teaching session                            |
| `semester_id` | BIGINT      | FK to `semesters`, nullable, defines when syllabus takes effect |
| `is_active`                  | BOOLEAN     | Whether this syllabus is currently active (default: true)       |
| `unit_id`                    | BIGINT      | FK to `units`, required                                         |
| `created_at`                 | TIMESTAMP   | Timestamp of creation                                           |
| `updated_at`                 | TIMESTAMP   | Timestamp of last update                                        |


### Table: `assessment_components`
| Column                          | Type         | Description                                                                 |
| ------------------------------- | ------------ | --------------------------------------------------------------------------- |
| `id`                            | BIGINT       | Primary key                                                                 |
| `syllabus_id`                   | BIGINT       | FK to `syllabus`, required                                                  |
| `name`                          | VARCHAR(100) | Name of the component (e.g., Midterm, Quiz)                                 |
| `weight`                        | DECIMAL(5,2) | Percentage contribution to total grade (0–100)                              |
| `type`                          | ENUM         | One of: `quiz`, `assignment`, `project`, `exam`, `online_activity`, `other` |
| `is_required_to_sit_final_exam` | BOOLEAN      | Whether this component must be passed to sit the final exam (default: true) |
| `created_at`                    | TIMESTAMP    | Timestamp of creation                                                       |
| `updated_at`                    | TIMESTAMP    | Timestamp of last update                                                    |

### Table: `assessment_component_details`
| Column         | Type         | Description                                   |
| -------------- | ------------ | --------------------------------------------- |
| `id`           | BIGINT       | Primary key                                   |
| `component_id` | BIGINT       | FK to `assessment_components`, required       |
| `name`         | VARCHAR(100) | Name of the subcomponent (e.g., Quiz Week 1)  |
| `weight`       | DECIMAL(5,2) | Weight within its parent component (optional) |
| `created_at`   | TIMESTAMP    | Timestamp of creation                         |
| `updated_at`   | TIMESTAMP    | Timestamp of last update                      |

---

## 📱 Frontend Requirements

### Pages

#### 1. Syllabus List Page
- Filter by: term, unit
- Show table of existing syllabi

#### 2. Syllabus Detail Page (Create/Edit)
- Unit (select)
- Term (select)
- Lecturer (optional)
- Notes (rich text)
- Assessment Components:
  - Add/Edit/Delete
  - Add subcomponents
  - Live percentage validation

#### 3. Clone Syllabus from Previous Term (Optional)
- Select term and unit to copy from
- Editable after clone

---

## 🔄 API Endpoints

### Backend - Laravel or Node.js

- `GET /api/syllabi`
- `GET /api/syllabi/{id}`
- `POST /api/syllabi`
- `PUT /api/syllabi/{id}`
- `DELETE /api/syllabi/{id}`

- `POST /api/syllabi/{id}/clone`
- Nested CRUD for `assessment_components` and `details`

---

## ⚠️ Business Rules

- Total assessment component weight must equal **100%**
- Total subcomponent weights must sum to 100% within each parent component
- A syllabus must be uniquely identified by `(unit_id, term_id)` combination
- A syllabus cannot be deleted if linked to student grades

---

## 📌 Notes

- The UI can be placed within the **Units module**, or as a **separate Syllabi module**, depending on design.
- Support `read-only` view for non-admin users.
