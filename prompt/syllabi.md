# 🛠️ Requirements for Implementing Syllabus & Assessments Management in Units Page

## 1. Migrations

Create migrations for the following tables:

### 🔹 `syllabi` table

- Fields:
  - `id` (bigint, auto-increment, primary key)
  - `unit_id` (foreign key to `units`)
  - `version` (varchar(10), nullable)
  - `description` (text, nullable)
  - `total_hours` (int, nullable)
  - `hours_per_session` (int, nullable)
  - `semester_id` (foreign key to `semesters`, nullable)
  - `is_active` (boolean, default true)

- Constraints:
  - Foreign key `unit_id → units.id`
  - Foreign key `semester_id → semesters.id`

---

### 🔹 `assessment_components` table

- Fields:
  - `id` (bigint, auto-increment, primary key)
  - `syllabus_id` (foreign key to `syllabi`)
  - `name` (varchar(100), nullable)
  - `weight` (decimal(5,2), nullable)
  - `type` (enum: quiz, assignment, project, exam, online_activity, other)
  - `is_required_to_sit_final_exam` (boolean, default true)

- Constraint:
  - Foreign key `syllabus_id → syllabi.id`

---

### 🔹 `assessment_component_details` table

- Fields:
  - `id` (bigint, auto-increment, primary key)
  - `component_id` (foreign key to `assessment_components`)
  - `name` (varchar(100), required)
  - `weight` (decimal(5,2), nullable)

- Constraint:
  - Foreign key `component_id → assessment_components.id` (on delete cascade)

---

## 2. Models

Create Eloquent models:

### ✅ `Syllabus`

- `belongsTo(Unit::class)`
- `belongsTo(Semester::class, 'semester_id')`
- `hasMany(AssessmentComponent::class)`

### ✅ `AssessmentComponent`

- `belongsTo(Syllabus::class)`
- `hasMany(AssessmentComponentDetail::class)`

### ✅ `AssessmentComponentDetail`

- `belongsTo(AssessmentComponent::class)`

---

## 3. Factories

Create model factories for testing & seeding:

### 🏭 `SyllabusFactory`

- `unit_id`: random from existing units
- `version`: e.g., `v1.0`, `v2.0`
- `description`: faker paragraph
- `total_hours`: random between 30–180
- `hours_per_session`: random between 1–4
- `semester_id`: random from `semesters`
- `is_active`: true/false

### 🏭 `AssessmentComponentFactory`

- `syllabus_id`: linked to existing syllabus
- `name`: e.g., "Final Exam"
- `weight`: 10–50%
- `type`: random enum
- `is_required_to_sit_final_exam`: true/false

### 🏭 `AssessmentComponentDetailFactory`

- `component_id`: linked to component
- `name`: "Proposal", "Presentation", etc.
- `weight`: 5–20%

---

## 4. CRUD Integration into Units Page

### 🔸 Listing Syllabi Per Unit

- Add a **"Syllabi" tab** or expandable section under each unit.
- List all syllabi related to that unit.
- Show:
  - `Version`
  - `Effective Semester`
  - `Total Hours`
  - `Active?`
  - Actions: `View`, `Edit`, `Delete`

---

### 🔸 Syllabus CRUD

- **Create:** Add new syllabus with basic fields + multiple assessment components
- **Edit:** Update version, description, hours, and active status
- **Delete:** Soft-delete or restrict if syllabus is active or in use
- Enforce **only one active syllabus per unit at a time**

---

### 🔸 Assessment Components and Details (Nested)

- Within each syllabus view/edit:
  - List all components with:
    - Type, Name, Weight, Final Exam Requirement
    - Sub-details (if any)
  - Allow inline creation/updating/deletion of:
    - Assessment Components
    - Assessment Component Details (as sub-items)

---

## 5. Validation Rules

### Syllabus

- `unit_id`: required
- `version`: optional but must be unique per unit if provided
- `total_hours`, `hours_per_session`: nullable integers >= 0

### AssessmentComponent

- `name`: required
- `weight`: required, decimal 0–100
- `type`: required, must match enum
- `is_required_to_sit_final_exam`: boolean

### AssessmentComponentDetail

- `name`: required
- `weight`: optional, decimal 0–100

---

## 6. Optional Features

- [ ] Support **versioning** of syllabi (e.g., auto-increment version if left blank)
- [ ] Auto-calculate total weight of all assessment components
- [ ] Toggle visibility of inactive syllabi
- [ ] Show warning if syllabus has 0% total assessment weight

---
