---
title: Forms & Quality
description: The form library, form runs, survey results, and the staff query inbox.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Forms/Admin/Index.vue
  - resources/js/pages/Forms/Runs/Index.vue
  - resources/js/pages/Forms/Admin/results/Index.vue
  - resources/js/pages/Forms/Queries/Inbox.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (new "Doanh thu" revenue nav item under Finance Office) -- no update needed here. -->

**Forms & Quality** collects information from students: teaching quality surveys, registration forms, requests.

Three things to keep apart:

| Term | Meaning |
| --- | --- |
| **Form** | The question design. Built once, used many times. |
| **Run** | One occasion of sending that form to a group, over a set period. |
| **Result** | The answers collected from a run. |

Do not create a new form each term. Create a new **run** from the form you already have.

## Forms Library

**What it is for.** Creating and managing forms. The screen is titled **Forms Management**.

**Who can open it.** Anyone with permission to view forms.

**Steps**

1. Go to **Forms & Quality → Forms Library**.
2. Create a form, or open an existing one to edit.
3. Click **Clear** to reset the filters.

**Notes**

- Editing a form while a run is in progress makes the answers inconsistent. If the questions must change, create a new form.
- Name forms clearly enough that a colleague knows what each is for.

## Runs

### Runs List

**What it is for.** Seeing which runs are open and which have closed. The screen is titled **Form Runs**.

**Steps**

1. Go to **Forms & Quality → Runs → Runs List**.
2. Narrow the list with the **Filters** panel.
3. Click **Clear** to see everything again.
4. Open a run to check its response progress.

### Create Run

**What it is for.** Opening a new run.

**Steps**

1. Go to **Forms & Quality → Runs → Create Run**.
2. Choose the form to send.
3. Choose the recipient group and the period it stays open.
4. Save to start the run.

**Notes**

- Check the recipient group carefully before saving. Notifications sent to the wrong group cannot be recalled.
- A short window produces a low response rate. For end-of-term surveys, open it before students finish their exams.

## Surveys

### Survey Results

**What it is for.** Reading the answers collected from runs.

**Who can open it.** Anyone with permission to view aggregate survey results.

**Steps.** Go to **Forms & Quality → Surveys → Survey Results** and choose the run.

**Note.** Teaching survey results are sensitive. Share them only within the scope you are permitted.

### Program Stats

**What it is for.** Figures aggregated by program rather than by individual run.

**Next.** Lecturer GPA, Course Ranking.

## Queries

### Staff Inbox

**What it is for.** Receiving and handling requests and questions students submit through forms.

**Who can open it.** Anyone with permission to review forms.

**Steps**

1. Go to **Forms & Quality → Queries → Staff Inbox**.
2. Open each request and read it.
3. Reply, or pass it to the responsible team.

**Note.** This is a shared inbox, not a personal one. Mark items as handled so colleagues do not duplicate the work.

## Common situations

| Situation | Order of work |
| --- | --- |
| End-of-term teaching survey | Forms Library (pick the form) → Create Run → Survey Results |
| Checking response rate on an open run | Runs List → open the run |
| Summarising feedback by program | Program Stats |
| Handling student questions | Staff Inbox |
