# Updated Markdown content to reflect new UI flow for "Split into Sections"
updated_split_offerings_md = """
# 🧩 Course Offering Split Requirements (Post Registration Phase)

This document outlines the updated plan for splitting a single `course_offering` into multiple sections **from the admin course offering detail view**, based on student registrations.

---

## ✅ Context

During the registration phase, each course (`unit`) in a semester is initially represented by **one `course_offering`**, which serves as a placeholder for all student registrations.

After registration closes, the admin can:

- Split this single offering into multiple sections
- Distribute enrolled students evenly or conditionally into new sections
- Assign instructors, schedules, and rooms to each new section

---

## ✅ Goals

- Trigger the "Split into Sections" action directly from the `course_offerings` list view via `...` menu
- Perform actual splitting and customization inside a **dedicated course offering detail page**
- Ensure students are evenly or custom-assigned to newly created `course_offerings` (sections A, B, C…)

---

## 🖥️ Admin UI Workflow

### 1. **Course Offerings List Page (`/course-offerings`)**

- Display all existing `course_offerings`
- Each row should have:
  - `Split into Sections` action in the 3-dot dropdown menu
  - Clicking it navigates to a split management view:  
    `/course-offerings/:id/split`

---

### 2. **Split Sections Page (`/course-offerings/:id/split`)**

This is the main interface where the actual splitting happens.

- Display:
  - Course name, semester
  - Total number of enrolled students
- Inputs:
  - Number of desired sections OR students per section
  - Optional assignment mode (equal, by GPA, by campus)
- Auto-split preview table:
  - Show students distributed across sections (editable)
- Actions:
  - [Confirm & Create Sections] button

Upon confirmation:
- Create new `course_offerings` with unique `section_code` (e.g. A, B, C)
- Migrate `course_registrations` to point to new offerings
- Optionally:
  - Create empty `course_schedules` per new offering
  - Assign `instructor_id` or room placeholders

---

## 🛠 Database Requirements

### `course_offerings`

Ensure:
- Has a `section_code` column to distinguish sections
- Can have multiple offerings per unit/semester (A, B, C…)

### `course_registrations`

Update each affected student:
- Change `course_offering_id` to point to the newly assigned section

---

## ✅ Optional Enhancements

- Assign instructor and schedule per section after splitting
- Add filter to main offerings list to show only “parent” offerings or all sections
- Use temporary field (`virtual_section_code`) if working in dry-run mode

---

## ✅ Deliverables for Devs

- Admin action: `Split into Sections` button on course offering row
- Split view page (`/course-offerings/:id/split`)
- Logic to:
  - Auto-split based on user config
  - Create `course_offerings` with section codes
  - Update `course_registrations`
- (Optional) Create initial empty `course_schedules` for each new section
