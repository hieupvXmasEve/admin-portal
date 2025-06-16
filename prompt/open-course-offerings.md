# 📘 Admin - Semester Course Enrollment & Offering Management

## 🧩 Objective

Allows admin to:
- Create a list of students enrolled in a new semester (`enrollments`)
- Suggest which courses should be opened based on students' curriculum
- Open course classes (`course_offerings`) either in bulk or individually
- Track student course registration progress

---

## 🗃 Related Tables

| Table                  | Description                                                                 |
|------------------------|-----------------------------------------------------------------------------|
| `semesters`            | Semester information (e.g., SUM2025)                                        |
| `students`             | Student data, each linked with a `curriculum_version_id`                    |
| `curriculum_versions`  | Curriculum program that students follow                                     |
| `curriculum_units`     | Courses mapped to `semester_number` in each curriculum                      |
| `enrollments`          | Links students to semesters and tracks their study status                   |
| `course_offerings`     | Opened course classes for a specific semester                               |
| `course_registrations` | Records which class a student registers for                                 |

---

## 🚦 Execution Flow

### ✅ Step 1: Create Enrollments for Students

**URL:** `/semesters/{id}`  
**Feature:** Button [Generate enrollments for students]

#### Logic:
1. Filter students:
   - `students.status = 'active'`
   - Must have a valid `curriculum_version_id`
2. Determine `semester_number`:
   - If no prior enrollment ⇒ `semester_number = 1`
   - Else ⇒ `MAX(enrollments.semester_number) + 1`
3. Insert into `enrollments`:
   - `student_id`, `semester_id`, `semester_number`, `status = 'in_progress'`

---

### ✅ Step 2: Suggest Courses to Open

**URL:** `/semesters/{id}/registration`

#### Logic:
1. Based on the newly created `enrollments`
2. Query `curriculum_units` using each student's `curriculum_version_id` and `semester_number`
3. Group by `unit_id` and count estimated students per course

#### UI Table Suggestion:

| Unit Code | Unit Name      | Est. Students | Existing Classes | Action     |
|-----------|----------------|----------------|------------------|------------|
| CSC101    | Intro to CS    | 125            | 0                | [Open]     |
| MAT201    | Discrete Math  | 102            | 1                | [Open]     |

---

### ✅ Step 3A: Bulk Open Classes

- Admin selects multiple suggested units
- Clicks [Open Selected]
- Creates `course_offerings` for each selected `unit_id`

### ✅ Step 3B: Open Classes Individually

- Click [Open] on a row
- Fill in class info:
  - Lecturer, room, schedule, student capacity
- Save to `course_offerings`

---

### ✅ Step 4: Track Student Registrations

- After classes are opened, students can register (`course_registrations`)
- Admin can view stats:
  - Registered / Estimated student counts
  - Filter by `unit_id`, `lecturer_id`, `specialization_id`, etc.

---

## 🧱 Suggested Admin UI - Semester Page

### Tabs:

1. **Semester Info**
2. **Manage Enrollments**
3. **Suggest Courses to Open**
4. **Registration Statistics**

---

## 🏁 Notes

- Creating `enrollments` is required before opening courses
- Possible `enrollments.status` values: `in_progress`, `completed`, `dropped`
- Students on academic leave should not get new enrollments
- New students should start with `semester_number = 1`
