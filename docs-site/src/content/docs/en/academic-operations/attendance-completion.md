---
title: Attendance
description: Tracking attendance and the list of students who failed.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Attendance/Index.vue
  - resources/js/pages/FailedStudents/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Attendance** is where you check how students are attending and which cases need follow-up. Per-unit statistics (**Course Statistics**) moved to the **Course Delivery** group.

## Attendance Summary

**What it is for.** Looking up attendance records per student, per session.

**Who can open it.** Anyone with permission to view attendance.

**Steps**

1. Go to **Academic Operations → Attendance → Attendance Summary**.
2. Type in **Search students or sessions...** to find a student or a session.
3. Narrow further by status (**All Statuses**) or by how attendance was taken (**All Methods**).

**Notes**

- This screen is for looking up and reconciling. Day-to-day attendance is taken by lecturers in their own portal.
- When a student disputes an absence, check here before deciding.

**Next.** Warning Center, Failed Students.

## Failed Students

**What it is for.** Listing the students who did not pass a unit, and why.

**Who can open it.** Anyone with permission to view attendance.

**Steps**

1. Go to **Academic Operations → Attendance → Failed Students**.
2. Use **Filters** to narrow by term, program, or unit.
3. Check the list, then move to **Retake Registration** to open retakes.
4. Click **Clear Filters** to see the whole list again.

**On screen**

- Four cards at the top: **Total Failed Students**, **Total Failed Courses**, **Avg Attendance**, **Retake Eligible**.
- **Fail Reason Distribution** — the breakdown by reason.
- **Top 10 Failed Units** — the ten units with the most failures.

**Notes**

- Only students in **Retake Eligible** should be opened for a retake. Other cases follow academic regulations separately.
- **Top 10 Failed Units** is the starting point for a teaching quality review. Read it at the end of each term.

**Next.** Retake Registration, Finance Office.

## Common questions

| Question | Page to use |
| --- | --- |
| How many students completed this class? | Course Statistics (**Course Delivery** group) |
| Who is missing too many classes? | Attendance Summary |
| Which students need to retake? | Failed Students |
| Should we send a warning? | Warning Center |

## Operating notes

- **Attendance Summary** suits early tracking while the class is still running.
- **Failed Students** suits the point where end-of-term results exist.
- Investigating one specific unit? Use **Course Statistics** in the **Course Delivery** group.
