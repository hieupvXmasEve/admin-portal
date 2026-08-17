---
title: Page flow map
description: How the academic screens connect along the real working sequence.
source:
  - resources/js/constants/menu-sidebar.ts
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

This page describes how staff move between screens. Use it when you know what you need to do but not where to begin.

## Overall flow

```text
Curriculum Setup
  -> Course Delivery
  -> Attendance
  -> Grades & Performance
  -> Students / Finance when records or fees need handling
```

## Preparing academic data

| Step | Page | Expected result |
| --- | --- | --- |
| 1 | Academic Terms | A term exists for opening classes and finalising GPA |
| 2 | Programs | A program exists to attach a curriculum to |
| 3 | Curriculum Versions | A curriculum version is in effect |
| 4 | Units | Units carry credits and prerequisites |
| 5 | Syllabus Templates | A syllabus is ready for the classes to come |

When this is done, move to **Course Offering List** to open classes.

## Running classes

| Step | Page | Expected result |
| --- | --- | --- |
| 1 | Course Offering List | Offerings are open for the term |
| 2 | Class Schedule | Sessions have a date, time, and room |
| 3 | Course Registration | Students are enrolled in the offering |
| 4 | Canvas Courses | The offering is linked to its online course, if needed |
| 5 | Attendance Summary | Attendance can be followed while the class runs |

If students fail, go to **Failed Students** and then **Retake Registration**.

## Managing risk

| Signal | Page to check | Page to act on |
| --- | --- | --- |
| Frequent absence | Attendance Summary | Warning Center |
| Weak class results | Course Statistics | Failed Students |
| GPA below threshold | GPA History | Warning Center |
| Needs to retake | Failed Students | Retake Registration |
| Needs to resit | Thi lại | Lịch thi lại |

## Closing the term

| Step | Page | Expected result |
| --- | --- | --- |
| 1 | Course Statistics | Class data and results have been checked |
| 2 | GPA Management | GPA for the term is finalised |
| 3 | GPA History | Finalised results can be looked up |
| 4 | Warning Center | Students needing action have been identified |

University-wide reports live in the **Reports & Audits** menu group.

## When to leave the academic area

| From | Go to | Why |
| --- | --- | --- |
| Course Registration | Students | Check the student record before registering |
| Failed Students | Students | See one student's grades, attendance, and GPA |
| Retake Registration | Finance | Handle the retake fee |
| Lịch thi lại | Campus | Book exam rooms and avoid clashes |
| Warning Center | Students | Record the decision taken on a student |

Canvas Courses and Faculty now both live inside **Academic Operations** (Faculty was folded in from the old Faculty & Teaching group) — checking the lecturer assigned to a class no longer means leaving the academic area.

## Choosing a starting point

| Your question | Open |
| --- | --- |
| "I need to open classes for the new term" | Course Offering List |
| "Has this student registered yet?" | Course Registration |
| "Who is missing too many classes?" | Attendance Summary |
| "Which students failed?" | Failed Students |
| "I need to finalise GPA for this term" | GPA Management |
| "Who is on academic warning?" | Warning Center |
| "I need to set up resit slots" | Lịch thi lại |
