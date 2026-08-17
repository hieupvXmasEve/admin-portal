---
title: Students
description: Student records, enrolment, academic holds, and admission applications.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Students/Index.vue
  - resources/js/pages/Students/enrollments/Index.vue
  - resources/js/pages/Academic/PlacementWorklist/Index.vue
  - resources/js/pages/StudentApplications/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Students** is where you work with **one student at a time**: their record, their enrolment status, and their admission application.

Contrast with **Reports & Audits**: that area shows university-wide figures; this one shows individual people.

## Students

**What it is for.** Looking up, adding, and editing student records. This is the door into a student's full profile.

**Who can open it.** Anyone with permission to view students.

**Steps**

1. Go to **Students → Students**. The screen is titled **Students Management**.
2. Find the student with the search box or the filters.
3. Click the row to open the full record.
4. Click **Đặt lại** (Reset) to clear every filter.

**Exporting the list**

1. Choose a format in **Select format**.
2. Choose the range in **Select scope** — only the filtered rows, or everything.
3. Confirm to download the file.

**Notes**

- The student record is the cross-reference point for everything: grades, attendance, GPA, fees, decisions.
- The list is filtered to the selected campus. If a student is missing, check the `Campus:` line.
- Export defaults to the current filters. A short export is usually a filter left switched on.

**Next.** Course Registration, Warning Center, Finance Office.

## Enrollments & Holds

**What it is for.** Managing a student's enrolment status per term, and the cases placed on academic hold.

**Who can open it.** Anyone with permission to view students.

**Steps**

1. Go to **Students → Enrollments & Holds**. The screen is titled **Student Enrollments & Holds Management**.
2. Read the **Student Enrollments** table to see who is enrolled in which term.
3. Filter to the group you need to handle.
4. Open a row to view or change its status.

**Notes**

- A student on hold usually cannot register for units. When someone reports "I cannot register", check this screen before checking the registration window.
- Holds are often caused by unpaid fees. Reconcile with the **Finance Office** area before lifting one.
- Hold alerts also appear on the Dashboard.

**Next.** Finance Office, Course Registration.

## Placement Worklist

**What it is for.** The list of admitted students the Academic department has not yet classified: into EGC (with an English level) or straight into their major (IELTS at or above the threshold).

**Who can open it.** Anyone with permission to change student status (classify).

**Steps**

1. Go to **Students → Placement Worklist**. The list only shows unclassified students of the selected campus.
2. Find a student with the search box (code / name) or the **Program** filter.
3. Click **Classify** on a row to open the classification dialog.
4. Pick the **Semester**. If the student has IELTS, switch it on and enter the score; at or above the threshold they enter their major directly. Without IELTS (or below the threshold), pick the **English Level** from the placement test.
5. Confirm. A classified student disappears from the list.

**Notes**

- Classifying here is exactly the "Initialize placement" action on the Lifecycle tab of the student record — same process, different entry point.
- Each student can only be classified once. Later corrections use the update actions on the Lifecycle tab.

**Next.** Students (full record), Course Registration.

## Student Applications

**What it is for.** Processing admission applications: reviewing documents, approving or rejecting.

**Who can open it.** Anyone with permission to view student applications.

**Steps**

1. Go to **Students → Student Applications**.
2. Find an application with **Search name, email, code…**, or filter by **Status** and **Intake**.
3. Click a file name in the application to open the attached document.
4. Approve the application, or reject it.

**When rejecting**

1. Enter the reason in **Explain the reason for rejection**.
2. Click **Confirm rejection**.

**Notes**

- The rejection reason is stored and may be sent to the applicant. Write it clearly enough that they know what to supply.
- Check the attached documents carefully before approving. Once approved, the application moves on through admission.
- Filter by **Intake** so applications from different rounds do not get mixed together.

**Next.** Students.

## Common situations

| Situation | Order of work |
| --- | --- |
| A student cannot register for units | Enrollments & Holds → Finance Office → Academic Terms |
| You need the full picture on one student | Students → open the record |
| Processing a new admission round | Student Applications, filtered by Intake |
| Classifying newly admitted students (EGC / straight to major) | Placement Worklist → Classify |
| Exporting a student list for a report | Students → choose format and scope |
