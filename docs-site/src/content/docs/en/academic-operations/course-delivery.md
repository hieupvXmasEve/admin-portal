---
title: Course Delivery
description: Opening classes, scheduling, registration, retakes, resits, unit statistics, and the Canvas link.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/CourseOfferings/Index.vue
  - resources/js/pages/ClassSchedule/Index.vue
  - resources/js/pages/CourseRegistrations/Index.vue
  - resources/js/pages/Academic/RetakeCourse/Index.vue
  - resources/js/pages/Academic/ExamResit/Index.vue
  - resources/js/pages/Academic/ExamResit/Schedule/Index.vue
  - resources/js/pages/CourseStatistics/Index.vue
  - resources/js/pages/Admin/Canvas/Courses/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- Course Statistics moved here from Attendance & Completion; Canvas Integrations moved here from Administration, retitled Canvas Settings, plans/260816-2125-sidebar-menu-ia-restructure/). -->

**Course Delivery** is the main work at the start of a term: opening classes, scheduling, registering students, and handling retakes and resits.

## Course Offering List

**What it is for.** Opening course offerings for a term: which unit, which term, which delivery mode, taught by whom.

**Who can open it.** Anyone with permission to view course offerings.

**Steps**

1. Go to **Academic Operations → Course Delivery → Course Offering List**.
2. Click **Create Course Offering**.
3. Choose the unit, the term, the mode and the remaining details, then save.
4. To find an existing offering, type in **Search courses...**, or use the **Filters** panel — by module (**All Modules**), level (**All Levels**), type (**All Types**), status (**All Statuses**), or delivery mode (**All Modes**).

**On screen.** Two cards at the top: **Total Offerings** and **Active Offerings**.

**Notes**

- Offerings must exist **before** registration opens for students.
- The list is filtered to the selected campus by default. If an offering you expect is missing, check the `Campus:` line in the top left corner.

**Next.** Course Statistics, Class Schedule, Course Registration, Attendance Summary.

## Course Statistics

**What it is for.** Seeing how each unit is doing in a term: enrolment, attendance rate, overall results.

**Who can open it.** Anyone with permission to view attendance.

**Steps**

1. Go to **Academic Operations → Course Delivery → Course Statistics**.
2. Choose the term in **Select semester**.
3. Find the unit using **Unit code or name...**.

**Note.** You must choose a term first, otherwise the screen shows nothing.

**Next.** Failed Students (in the **Attendance** group).

## Class Schedule

**What it is for.** Scheduling sessions for each offering: date, time, room.

**Who can open it.** Anyone with permission to view course offerings.

**Steps**

1. Go to **Academic Operations → Course Delivery → Class Schedule**.
2. Use the filter panel to pick a term, an offering, or a date range.
3. View the schedule as a calendar or a grid.
4. Click a session to open the edit panel on the right, then save.

**Notes**

- Check for room clashes and lecturer clashes before saving.
- Moving a session that has already happened will distort its attendance data.

**Next.** Attendance Summary.

## Course Registration

**What it is for.** Recording which students take which offering in the term.

**Who can open it.** Anyone with permission to view course registrations.

**Steps**

1. Go to **Academic Operations → Course Delivery → Course Registration**.
2. Use **Filters** to narrow by term, offering, or student.
3. Click **View** for details, **Edit** to change, **Delete** to cancel a registration.
4. When the list is still empty, click **Register First Student** to add one.

**On screen.** Three cards at the top: **Total Registrations**, **Active Registrations**, **Pending Registrations**.

**Notes**

- Cancelling a registration affects the student's fees. Handle the money side in the **Finance Office** area.
- The **Pending** figure is outstanding work. Clear it before the term starts.

**Next.** Student list, Attendance Summary.

## Retake Registration

**What it is for.** Registering students to retake a unit they failed.

**Who can open it.** Anyone with permission to view retake registrations.

**Steps**

1. Go to **Academic Operations → Course Delivery → Retake Registration**.
2. Filter to find the student or unit you need.
3. Record the retake registration.
4. Click **Xóa bộ lọc** (Clear filters) if the list is not showing what you expect.

**Notes**

- The list of students eligible to retake comes from the **Failed Students** screen.
- Retakes usually incur a fee. Check it in the **Finance Office** area.

**Next.** Failed Students, Finance Office.

## Thi lại — Exam resit

**What it is for.** Building the resit list and tracking its results.

**Who can open it.** Anyone with permission to view exam resits.

**Steps**

1. Go to **Academic Operations → Course Delivery → Thi lại**.
2. Filter by term, unit, or student.
3. Create the resit round and select the students taking part.
4. After the exam, record the results to close the round.

**Next.** Lịch thi lại.

## Lịch thi lại — Resit schedule

**What it is for.** Setting exam slots and rooms, and assigning invigilators for a resit round.

**Who can open it.** Anyone with permission to manage the resit schedule.

**Steps**

1. Go to **Academic Operations → Course Delivery → Lịch thi lại**.
2. Click **Tạo ca phòng thi** (Create exam slot) to open the **Ca phòng thi mới** panel.
3. Enter the time and room, then click **Tạo ca** (Create slot).
4. Click **Thêm ca thi** (Add slot) for further slots in the same round.
5. Click **Phân công coi thi** (Assign invigilators) to staff each slot.

**Note.** Book exam rooms in **Campus** first, so they do not clash with other activities.

## Canvas Courses

**What it is for.** Linking a Portal course offering to the matching course in Canvas, the online learning system.

**Who can open it.** Anyone with permission to view the Canvas integration.

**Steps**

1. Go to **Academic Operations → Course Delivery → Canvas Courses**.
2. Check the **Integration Status** panel to confirm the connection is working.
3. Select the offering to link and click **Map Course**.
4. To link several at once, use **Select All** and **Deselect All**.
5. If the connection is faulty, click **Manage Integrations** or **Go to Integrations** to open **Canvas Settings**.

**Note.** A wrong link sends students into the wrong online class. Check the offering code and the term before confirming.

**Next.** Canvas Settings.

## Canvas Settings

**What it is for.** Configuring the connection to Canvas (API keys, endpoint). Previously lived under Administration; now sits next to Canvas Courses since both share the same permission (`view_canvas_integration`) and both serve the academic team, not system administrators.

**Who can open it.** Anyone with permission to view the Canvas integration.

**Steps.** Go to **Academic Operations → Course Delivery → Canvas Settings**.

**Next.** Canvas Courses.

## Suggested flow

```text
Course Offering List
  -> Class Schedule
  -> Course Registration
  -> Canvas Courses
  -> Attendance Summary
```

When a class has students who failed:

```text
Course Statistics
  -> Failed Students
  -> Retake Registration
  -> Finance Office if the retake fee needs handling
```
