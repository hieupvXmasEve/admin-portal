---
title: Faculty
description: Lecturer records, teaching hours, and results for the classes they teach.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Lectures/Index.vue
  - resources/js/pages/Lectures/TeachingHours.vue
  - resources/js/pages/Lectures/LecturerGpa.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- Faculty & Teaching folded into Academic Operations as "Faculty"; page moved from faculty-teaching/index.md, plans/260816-2125-sidebar-menu-ia-restructure/). -->

**Faculty** (inside **Academic Operations**) covers the teaching staff: who teaches, how many hours, and how their classes performed.

## Lecturer List

**What it is for.** Looking up and managing lecturer records.

**Who can open it.** Anyone with permission to view lecturers.

**Steps**

1. Go to **Academic Operations → Faculty → Lecturer List**. The screen is titled **Lecturers**.
2. Filter by term (**All Semesters**), program (**All Programs**), status (**All Statuses**), or contract type (**All Types**).
3. Click a row to open the lecturer record.

**Notes**

- Contract type (full-time, visiting) affects how teaching hours are counted on the next screen.
- A lecturer must exist here before they can be assigned to a course offering.

**Next.** Lecturer Hours, Course Offering List.

## Lecturer Hours

**What it is for.** Counting each lecturer's teaching hours for a term or a date range. The screen is titled **Lecturer Teaching Hours Report**.

**Who can open it.** Anyone with permission to view lecturers.

**Steps**

1. Go to **Academic Operations → Faculty → Lecturer Hours**.
2. Choose a term in **Select semester**, or enter a range in **From date** and **To date**.
3. Read the table, and open a lecturer for the class-by-class breakdown.

**Notes**

- Hours are counted from the scheduled sessions. A session missing from **Class Schedule** is not counted.
- These figures usually feed payment. Reconcile against the actual teaching record before signing off.
- Moving sessions after hours are signed off will distort that term's figures.

**Next.** Class Schedule.

## Lecturer GPA

**What it is for.** The average results of the classes each lecturer taught.

**Who can open it.** Anyone with permission to view lecturers **and** to view aggregate survey results.

**Steps**

1. Go to **Academic Operations → Faculty → Lecturer GPA**.
2. Choose a term in **Select semester**.
3. Read the results per lecturer.

**Notes**

- The figures only mean anything once the term's GPA is finalised.
- Low class results do not by themselves indicate poor teaching: unit difficulty, intake strength, and class size all affect them. Read alongside **Course Ranking** and **Course Statistics** before drawing conclusions.
- This is sensitive data about people. Use it only within the scope you are permitted.

**Next.** Course Ranking, Survey Results.

## Common situations

| Situation | Order of work |
| --- | --- |
| Calculating teaching payment at term end | Class Schedule → Lecturer Hours |
| Preparing teaching assignments for a new term | Lecturer List → Course Offering List |
| Reviewing teaching quality | Lecturer GPA → Course Ranking → Survey Results |
