# 📘 Syllabus UI Requirements

## 1. Overview

Each unit can have multiple syllabi, with each syllabus corresponding to a specific semester. The UI should allow users to easily view, create, update, and manage these syllabi in an intuitive and structured way.

---

## 2. Syllabus Listing per Unit

### ✅ UI Display Table Format

| Version | Semester  | Active? | Total Hours | Hours/Session | Actions      |
|---------|-----------|---------|-------------|----------------|--------------|
| v1.0    | SUM2024   | ✅       | 120         | 3              | View · Edit  |
| v2.0    | FALL2024  | ✅       | 120         | 3              | View · Edit  |
| v1.0    | FALL2023  | ❌       | 100         | 2              | View         |

- **Version:** The version of the syllabus.
- **Semester:** The semester when the syllabus becomes effective.
- **Active:** Indicates whether the syllabus is currently in use.
- **Actions:** Options to view or edit the syllabus.

---

## 3. Syllabus Detail View

When the user clicks **View** or **Edit**, show the full details of the syllabus:

### 📄 Basic Info

- **Version**
- **Effective From Semester**
- **Description**
- **Total Hours**
- **Hours Per Session**
- **Status (Active/Inactive)**

---

### 🧮 Assessment Components

Display a table of all assessment components in the syllabus.

| Name              | Type            | Weight | Required for Final Exam? | Details       |
|-------------------|------------------|--------|----------------------------|----------------|
| Midterm Quiz      | quiz             | 10.00  | No                         | [View Details] |
| Final Exam        | exam             | 50.00  | Yes                        | -              |
| Project Phase 1   | project          | 20.00  | No                         | [View Details] |
| Weekly Activities | online_activity  | 20.00  | No                         | -              |

---

### 🔍 Component Details

Show when clicking "View Details" for assessment components with sub-tasks.

#### Example: Project Phase 1

| Sub-task             | Weight |
|----------------------|--------|
| Proposal Submission  | 5.00   |
| Prototype            | 7.50   |
| Presentation         | 7.50   |

---

## 4. Actions & Controls

- [ ] **Add new syllabus**: Button to add a new syllabus for the current unit.
- [ ] **Mark as active**: Checkbox or toggle to set a syllabus as active.
- [ ] **Delete syllabus**: Allow deletion if not currently in use.
- [ ] **Only one active syllabus per unit per semester.**

---

## 5. Filters and Search

Allow filtering of syllabi by:

- [ ] Semester (e.g., SUM2025)
- [ ] Active status (Active / Inactive)
- [ ] Version (e.g., v1.0, v2.0)

---

## 6. UX/UI Suggestions

- Use color badges to indicate syllabus status:
  - ✅ `Active`: Green badge
  - ❌ `Inactive`: Gray badge
- Add label: `Currently in use` for active syllabi in the current semester.
- Follow consistent version naming convention such as: `v1.0`, `v2.0`, or `2024.1`, `2024.2`.

---

## 7. Optional Enhancements

- [ ] Allow cloning a syllabus from a previous version.
- [ ] Export syllabus as PDF or Excel.

---
