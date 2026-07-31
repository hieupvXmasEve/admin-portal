---
title: Curriculum Setup
description: Building the academic framework — terms, programs, curriculum versions, units, modules, syllabus templates.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Semesters/Index.vue
  - resources/js/pages/Programs/Index.vue
  - resources/js/pages/CurriculumVersions/Index.vue
  - resources/js/pages/Units/Index.vue
  - resources/js/pages/Admin/Modules/Index.vue
  - resources/js/pages/Syllabus/TemplatesIndex.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (new "Doanh thu" revenue nav item under Finance Office) -- no update needed here. -->

**Curriculum Setup** prepares the underlying data. You do it **once and reuse it across terms** — it is not daily work.

If the data here is wrong or missing, opening classes, registering students, calculating GPA, and academic reporting are all affected.

## Academic Terms

**What it is for.** Declaring terms: code, start and end dates, and the course registration window. Everything else attaches to a term.

**Who can open it.** Anyone with permission to view terms — usually the academic office.

**Creating a term**

1. Go to **Academic Operations → Curriculum Setup → Academic Terms**.
2. Click **Add New Semester**.
3. Fill in the code, name, start date, end date, and the dates registration opens and closes.
4. Save.

**Adding a campus-specific schedule**

1. Open the term you just created.
2. Under **Campus schedules**, click **Add campus schedule**.
3. Choose the campus and enter the dates that apply to it.

**Notes**

- The term is shared university-wide; each campus may run to different dates, declared under **Campus schedules**.
- The registration window decides whether students can register at all. Getting it wrong is the single most common cause of "students cannot register".
- Changing the dates of a running term cascades into registration and fees. Think before editing.

**Next.** Course Offering List, GPA Management.

## Programs

**What it is for.** Declaring the majors and programs the university offers.

**Who can open it.** Anyone with permission to view programs.

**Steps**

1. Go to **Academic Operations → Curriculum Setup → Programs**.
2. Click **Add Program** to create one.
3. Fill in the details and save.
4. For existing programs use the row icons: **View program**, **Edit program**, **Delete program**.

**Note.** Do not delete a program that has enrolled students. If it is no longer recruiting, stop using it rather than deleting it.

**Next.** Curriculum Versions, Units.

## Curriculum Versions

**What it is for.** Each intake may follow a different curriculum. Each of those is a version.

**Who can open it.** Anyone with permission to view curriculum versions.

**Steps**

1. Go to **Academic Operations → Curriculum Setup → Curriculum Versions**.
2. Click **Add Curriculum Version** to create one.
3. For a small change to an existing version, use the **Duplicate curriculum version** icon and edit the copy.

**On screen.** Three cards at the top: total versions, versions currently **Active**, and **Inactive** ones.

**Note.** Editing an active version affects students already studying under it. The safe path is to duplicate, edit the copy, then switch over.

**Next.** Units, Course Registration.

## Units

**What it is for.** The unit catalogue: code, name, credits, prerequisites, and equivalent units.

**Who can open it.** Anyone with permission to view units.

**Steps**

1. Go to **Academic Operations → Curriculum Setup → Units**.
2. Click **Add Unit** to add one.
3. Declare prerequisites and equivalents where they apply.
4. Use the row icons to view, edit, or delete a unit.

**On screen.** Three cards at the top: total units, units **with prerequisites**, and units **with equivalents**.

**Notes**

- Prerequisites directly control whether a student is allowed to register for a unit.
- Equivalents apply when a student changes program, or retakes through another unit that has been recognised.
- The screen supports **bulk delete**. Check the selection carefully before confirming — it cannot be undone.

**Next.** Syllabus Templates, Course Offering List.

## Modules

**What it is for.** Grouping units into blocks of study within a program.

**Who can open it.** Anyone with permission to view modules.

**Steps.** Go to **Academic Operations → Curriculum Setup → Modules** to view and manage the module list.

**Next.** Programs, Units.

## Syllabus Templates

**What it is for.** Preparing reusable syllabus templates so nothing is written from scratch each term.

**Who can open it.** Anyone with permission to view syllabuses.

**Steps**

1. Go to **Academic Operations → Curriculum Setup → Syllabus Templates**.
2. Click **New Template**.
3. Write the template and save.
4. If the list looks wrong, click **Clear filters**.

**Next.** Course Offering List, Course Statistics.

## Check before opening classes

- The term exists and is the one you mean to open.
- The program and curriculum version match the intake.
- Units carry the information they need: credits, prerequisites.
- A syllabus template is ready for the units being opened.
- If students will retake, check retake fees in the Finance Office area.
