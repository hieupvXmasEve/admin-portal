---
title: First steps
description: Signing in, choosing a campus, reading the main screen, and the actions shared by every list page.
source:
  - resources/js/pages/Auth/Login.vue
  - resources/js/pages/SelectCampus.vue
  - resources/js/pages/Dashboard/Dashboard.vue
  - resources/js/components/AppSidebar.vue
  - resources/js/constants/menu-sidebar.ts
---

This chapter gets you into the system and explains the layout. Read it once and every later chapter will make sense.

## Signing in

**What it is for.** Getting into the system with the account the university issued you.

**Steps**

1. Open the Portal address your university provided.
2. Click **Sign in with Google**.
3. Choose the university email account issued to you.

**Notes**

- The system **only signs in through Google**. There is no separate password field.
- An error message showing an email address means that address has not been granted access yet. Ask an administrator to add the account.
- Signing in with a personal account is the most common mistake. Check the email shown in the top right corner.

## Choosing a campus

**What it is for.** Selecting the campus you work at. Everything shown afterwards — students, course offerings, rooms, fees — belongs to the selected campus.

**Steps**

1. After signing in, the **Select Your Campus** screen lists the campuses you may access.
2. Click the card for the campus you work at. The selected card gets a blue border.
3. Confirm to enter the system.
4. To change it, click **Clear Selection** and pick another campus.

**Notes**

- If you have access to only one campus, the system selects it for you.
- The active campus is always shown under the system name in the top left corner, on the `Campus: ...` line.
- Entering data under the wrong campus is hard to undo, because the records are attached to that campus. Always check the `Campus:` line before creating anything.

## Screen layout

The screen has three parts:

- **Left column — the menu.** Every function, grouped by kind of work.
- **Top bar.** The name of the open screen and the path back.
- **Middle.** The working area: summary cards, filters, data tables.

The main menu groups:

| Menu group | Work it covers |
| --- | --- |
| Overview | Summary screen |
| Academic Operations | Curriculum, terms, course offerings, attendance, grades |
| Student Services | Student records, enrolment, decisions |
| Reports & Audits | Reports and data reconciliation |
| Faculty & Teaching | Lecturers and teaching hours |
| Finance Office | Fees: generation, collection, reconciliation |
| Discounts & Funding | Scholarships, tuition plans, vouchers |
| Forms & Quality | Forms and surveys |
| Campus Operations | Rooms, bookings, events, clubs |
| Communications | Email and notifications |
| Administration | Users, permissions, system configuration |

**Important.** The menu reflects your permissions. Colleagues may see more or fewer items than you. A missing item means access has not been granted, not that the system is broken.

## The Dashboard

**What it is for.** A quick read on the selected campus.

**Who can open it.** Every signed-in user.

**What it shows**

- Totals: students by status, lecturers, number of programs, and rooms that are free, in use, or under maintenance.
- The current term: code, start and end dates, the registration window, and whether registration is open or closed.
- Alerts to act on: students on academic hold, attendance problems, program changes.
- A chart of students by program.

**Note.** The alerts are only a starting point. The actual work happens in the academic and student areas.

## Your account

**Steps**

1. Click your name at the **bottom of the left menu column**.
2. Choose what you need: profile, settings, or sign out.

**Notes**

- Portal has no password change screen. Your password belongs to your Google account; change it there.
- On a shared computer you must **sign out**, not just close the browser.

## Actions repeated on most screens

List screens in Portal share a few controls:

- **Search box** — type a name or code to narrow the list.
- **Filters** — restrict by term, status, program, and so on.
- **Clear filters** — return the list to its original state when the data you expect is missing.
- **Summary cards above the table** — totals that follow the current filters.
- **Icons at the end of each row** — view, edit, or delete that record.

**Note.** An empty list is usually a leftover filter, not missing data. Click **Clear filters** before reporting a problem.
