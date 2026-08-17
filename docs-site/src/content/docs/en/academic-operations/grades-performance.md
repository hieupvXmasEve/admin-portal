---
title: Grades & Performance
description: Finalising GPA, looking up past GPA, following students on academic warning, and reviewing scholarship adjustments.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Admin/Academic/Gpa/Index.vue
  - resources/js/pages/Admin/Academic/Gpa/History.vue
  - resources/js/pages/Academic/Warnings/Index.vue
  - resources/js/pages/ScholarshipAdjustments/Index.vue
  - resources/js/pages/ScholarshipAdjustments/Show.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Grades & Performance** is where results are closed out: calculating GPA, looking up past results, spotting students at academic risk, and reviewing scholarship adjustments for students who failed a unit.

University-wide reports — Performance Dashboard, Academic Report, Course Ranking — live in the separate **Reports & Audits** menu group, not here.

## GPA Management

**What it is for.** Finalising the grade point average of students for a term.

**Who can open it.** Anyone with permission to view grades.

**Steps**

1. Go to **Academic Operations → Grades & Performance → GPA Management**.
2. In the **Semester Finalization** panel, choose the term to close.
3. Check the figures, then confirm.

**Notes**

- Finalising GPA is a decisive moment: scholarships, academic warnings, and graduation review all read from it.
- Finalising before all grades are entered produces the wrong GPA. Confirm with the teams entering grades before you press it.

**Next.** GPA History, Warning Center.

## GPA History

**What it is for.** Looking up GPA finalised in earlier terms.

**Who can open it.** Anyone with permission to view grades.

**Steps**

1. Go to **Academic Operations → Grades & Performance → GPA History**.
2. Find the student in **Search student...**.
3. Narrow further by **Semester**, **Program**, or **Academic Standing**.

**Note.** Use this screen when answering a grade dispute or confirming results from a past term.

**Next.** Warning Center, the student record.

## Warning Center

**What it is for.** Seeing students currently under warning, in two kinds:

- **Academic Standing Warnings** — triggered by academic results.
- **Attendance Warnings** — triggered by absence.

**Who can open it.** Anyone with permission to view both attendance and grades.

**Steps**

1. Go to **Academic Operations → Grades & Performance → Warning Center**.
2. Read both panels and check the students needing action.
3. Move to the student record to log what was done.

**Notes**

- Warnings follow the grade and attendance data, so review them after each GPA finalisation.
- Review them regularly rather than waiting for the end of term.

**Next.** Attendance Summary, Students.

## Scholarship Adjustments

**What it is for.** Reviewing students who failed a unit, logging their interview, and deciding whether their scholarship is reduced for the following term. This is a **Progression Action** — it sits next to Warning Center because it handles a consequence of academic results, not a charge Finance manages directly.

**Who can open it.** Anyone with permission to view/process scholarship adjustment cases (`view_scholarship_adjustment`, `approve_scholarship_adjustment`).

**Step-by-step guide:** [Scholarship Adjustments](/en/academic-operations/scholarship-adjustments/) (finding students, scheduling the interview, confirmation, the decision, applying it, and restoring the next term).

## Checks after finalising GPA

```text
GPA Management
  -> GPA History
  -> Warning Center
```

If the figures look wrong:

```text
GPA History
  -> the student's academic record
  -> Course Statistics
```
