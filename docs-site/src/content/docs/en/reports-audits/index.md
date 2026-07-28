---
title: Reports & Audits
description: University-wide reporting and reconciliation of academic data.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Admin/Reports/StudentActionsAudit.vue
  - resources/js/pages/Admin/Reports/StudentDecisions/Index.vue
  - resources/js/pages/Admin/Reports/AcademicProgressionAudit/Index.vue
  - resources/js/pages/Admin/Reports/AcademicProgressionAudit/MissingDocuments.vue
  - resources/js/pages/Admin/Reports/AcademicProgressionAudit/MissingDecisions.vue
  - resources/js/pages/Admin/Academic/Performance/Dashboard.vue
  - resources/js/pages/Academic/CourseRanking/Index.vue
  - resources/js/pages/Academic/Report/Index.vue
  - resources/js/pages/Admin/Reports/StudentLifecycleYearlyAnalysis/Index.vue
  - resources/js/pages/Admin/Reports/StudentLifecycleYearlyAnalysis/StudentStatusTable.vue
  - resources/js/pages/Academic/Report/StudentUnits/Index.vue
---

**Reports & Audits** is the management area: university-wide figures, and the gaps where data is missing.

Unlike **Student Services**, which handles one student at a time. Here each report row links **one way** into the relevant student record; there is no way back from the record to the report.

Two groups: **Lifecycle & Decisions** and **Academic Performance**.

## Lifecycle & Decisions

### Student Actions Audit

**What it is for.** Reviewing every action taken on student records: who did what, and when.

**Who can open it.** Anyone with permission to view student actions.

**Steps**

1. Go to **Reports & Audits → Lifecycle & Decisions → Student Actions Audit**.
2. Read the **Action Logs** table.
3. Filter by date, action type, or the person who acted.
4. Click **Reset all** to clear every filter.

**Note.** This screen answers "who changed this". Use it for disputes and internal review.

### Lifecycle Yearly Analysis

**What it is for.** Following each intake year by year: how many continue, defer, leave, or graduate. Click any figure in the tables to see the students behind it.

**Who can open it.** Anyone with permission to view student actions.

**On screen**

- **Students by cohort and semester** — one matrix table per intake: rows are statuses (enrolled, deferred, dropped, graduated...), columns are semesters. Any cell greater than zero is clickable; the selected cell is highlighted.
- **Movement by semester** — how many students entered fresh each semester and how many changed status.
- **Current status by cohort** — headcount, NE (not yet determined) count, and defer/drop/graduation rates for each intake as of the current semester.
- **Student list by semester** — the detail table, filterable by semester and status; it stays in sync with whichever matrix cell was clicked.

**Steps**

1. Go to **Reports & Audits → Lifecycle & Decisions → Lifecycle Yearly Analysis**.
2. Click a cell in the matrix (for example, the deferred count for cohort X in semester Y). The **Student list** dialog opens with exactly that list.
3. Or set **Semester** and **Status** manually in the **Student list by semester** panel below if you don't need a cohort filter.
4. Click **Clear cohort filter** to see all students in the semester, without the cohort restriction.
5. Click **Export Excel** to download the list currently shown (with the semester / status / cohort filters applied).

**Note.** A cohort's column only counts from the semester it actually entered; earlier semesters show `-`, not zero.

### Academic Progression

**What it is for.** Checking that students are placed in the right stage of study.

**Who can open it.** Anyone with permission to view student actions.

**On screen.** Summary cards: **Pre-Uni GC Placements**, **Intake Course Placements**, **Stage Changes**, and **IELTS Recorded**.

**Note.** Figures that disagree with reality usually mean records have not been updated, not that students are placed wrongly. Check the two screens below before concluding otherwise.

### Missing Documents

**What it is for.** Listing students whose IELTS certificate is still missing. The screen is titled **Missing IELTS Documents**.

**Steps**

1. Go to **Reports & Audits → Lifecycle & Decisions → Missing Documents**.
2. Read the **Students with Missing Documents** table.
3. Chase the students, or record the document if it was supplied but never entered.

**Note.** This list should reach zero before each progression review. Left to accumulate, it blocks stage transitions.

### Missing Decisions

**What it is for.** Listing stage transitions that have no decision attached.

**Steps**

1. Go to **Reports & Audits → Lifecycle & Decisions → Missing Decisions**.
2. Read the **Transitions Missing a Decision** table.
3. Attach the missing decision to each case.

**Note.** A missing decision is a gap in the formal record, not just missing data. Treat it as a priority.

### Student Decisions

**What it is for.** Looking up every decision issued for students.

**Steps**

1. Go to **Reports & Audits → Lifecycle & Decisions → Student Decisions**.
2. Narrow the list with the **Filter** panel.
3. Read the **Decision List** table and open a decision for its detail.

## Academic Performance

### Performance Dashboard

**What it is for.** A university-wide view of academic results. The screen is titled **Academic Performance**.

**Who can open it.** Anyone with permission to view grades.

**On screen.** Four cards: **Avg Semester GPA**, **Avg Cumulative GPA**, **At-Risk Students**, and **Completion Rate**.

**Note.** The figures are only correct once GPA has been finalised. Reading them earlier gives a half-finished picture.

### Course Ranking

**What it is for.** Comparing results across units and spotting unusually strong or weak ones.

**Who can open it.** Anyone with permission to view grades.

**Note.** Read it together with **Top 10 Failed Units** on the Failed Students screen when reviewing teaching quality at the end of a term.

### Academic Report

**What it is for.** Tables and charts for internal reporting.

**On screen.** **Grade Distribution** and **Grade Statistics**.

**Steps**

1. Go to **Reports & Audits → Academic Performance → Academic Report**.
2. Filter by term, program, or unit.
3. Click **Clear Filters** to see everything again.

### Student Completed Units

**What it is for.** Every student who has passed units, split into **GC** (general core) and **Major** groups, with unit and credit counts.

**Who can open it.** Anyone with permission to view academic reports.

**Steps**

1. Go to **Reports & Audits → Academic Performance → Student Completed Units**.
2. Search by **Student ID / Name** or filter by **Program**.
3. Read the **GC Units** and **Major Units** columns — each unit shows as a code chip; `—` means no units in that group yet.
4. Click **Export Excel** to download the list currently shown (with the active filters applied).

**Note.** The list only counts **passed** units; units still in progress or failed do not appear here.

## Common situations

| Situation | Page to use |
| --- | --- |
| "Who edited this student record?" | Student Actions Audit |
| Preparing the end-of-term review | Performance Dashboard → Academic Report → Course Ranking |
| Cleaning records before a progression review | Missing Documents → Missing Decisions → Academic Progression |
| Attrition figures by intake | Lifecycle Yearly Analysis |
| Checking which units a student has passed, GC or Major | Student Completed Units |
