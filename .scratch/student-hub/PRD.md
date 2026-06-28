# PRD: Student Hub — one student-centric operational console

Status: ready-for-agent

> Source: grilling session (grill-with-docs). Glossary terms in `CONTEXT.md` (**Student Hub, Student Action, Academic Progression, Placement, EGC, Decision**, plus Campus scoping, Actor, Case review section bundle). Decisions recorded in `docs/adr/0007`–`0009`. Use the glossary vocabulary throughout; respect those ADRs.

## Problem Statement

From the perspective of a **Cán bộ Đào tạo** (Academic Affairs officer) — the one role that owns a student's whole academic lifecycle:

To handle a single student today I hop across four unrelated top-level menus (Academic Operations, Student Services, Finance Office, Discounts & Funding). Nothing gives me *one* view of a student. The most important per-student jobs — status changes and EGC placement/progression — live on standalone pages that throw me out of the student I'm looking at, and the same work is duplicated as separate "report" pages, so I never know where I'm supposed to do what. The student detail experience is itself split in two (an orphan "Show" page and the Academic Summary). The Academic Summary hides GPA and Graduation, and its "Export" button does nothing. The vocabulary (action / decision / progression / placement) overlaps and confuses. Net effect: high cognitive load, constant loss of context, and duplicated surfaces that contradict each other.

## Solution

From the user's perspective: **open a student and do everything about their academic lifecycle in one place, without leaving.**

A single **Student Hub** (ADR-0007) replaces the scattered surfaces:

- The Hub is the existing academic-summary surface, upgraded into an **operational console**: I both *view* and *act* in place. The orphan Show page and the two standalone per-student pages (`/students/{id}/actions`, `/students/{id}/placement`) are retired/redirected into Hub tabs.
- A **persistent context bar** (photo, name, student id, status, intake, program/specialization) and quick actions stay on every tab.
- **Seven tabs:** Overview · Registrations · Scores & GPA · Attendance · **Lifecycle** · Graduation · Finance.
- The **Lifecycle** tab is a single chronological timeline merging Student Actions and EGC Academic Progression, with authorizing Decisions shown inline and EGC level/IELTS detail as a sub-panel (ADR-0009).
- **Finance is finance-aware, not finance-owning**: fees, gold wallet, and scholarships appear only as a read-only summary that deep-links to the Finance Office. No money operations happen in the Hub (ADR-0007).
- **Decisions** become first-class governance documents: one Decision can cover many Students, can be informational, and can authorize transitions in *both* lifecycle streams; required decisions can be **backfilled** and a **missing-decision report** surfaces what is still missing (ADR-0008).
- The aggregate audit/report pages stay as a **separate management area** for Directors/Heads, linking **one-way** into the relevant student's Hub tab.

## User Stories

**Finding and opening a student**

1. As an Academic staff member, I want to find a student by name, email, or student id from the student list, so that I can open their Hub quickly.
2. As an Academic staff member, I want to filter the student list by program, specialization, status, and intake, so that I can narrow to a cohort.
3. As an Academic staff member, I want to open a student from the list into their Hub, so that I land on one place that holds everything about them.
4. As an Academic staff member, I want there to be exactly one student-detail surface, so that I never wonder whether the "Show" page or the "Academic Summary" is the real one.

**Context bar and quick actions**

5. As an Academic staff member, I want a context bar (photo, name, student id, status, intake, program/specialization) pinned on every tab, so that I always know which student I am acting on.
6. As an Academic staff member, I want quick actions (login-as-student, edit, export) available from the context bar, so that common operations are one click from anywhere in the Hub.
7. As an Academic staff member, I want my act-capable controls to appear only if I have the right permission, so that read-only colleagues are not shown actions they cannot perform.

**Overview tab**

8. As an Academic staff member, I want an Overview that shows identity, contacts, holds, and academic info (campus, program, specialization, curriculum version), so that I get the whole picture without switching tabs.
9. As an Academic staff member, I want the useful fields from the retired Show page (emergency contacts, recent registrations, holds) folded into Overview, so that nothing is lost when Show goes away.
10. As an Academic staff member, I want academic-concern alerts (active holds, probation/warning/suspension) surfaced on Overview, so that I notice problems immediately.

**Registrations tab**

11. As an Academic staff member, I want to see the courses a student is registered in, per semester, with status, so that I understand their current load and history.
12. As an Academic staff member, I want each registration row to link to its **course offering (class)**, so that I can jump from the student into the class when needed.
13. As an Academic staff member, I want to filter registrations by academic year, semester, status, and retake, so that I can focus on a period.

**Scores & GPA tab**

14. As an Academic staff member, I want per-course scores with assessment breakdown for a student, so that I can review their results.
15. As an Academic staff member, I want GPA history and academic standing in the same tab, so that I no longer have to reach a hidden/commented-out GPA view.
16. As an Academic staff member, I want this tab to show only *this* student's grades, so that cohort-level grade statistics stay in the management Reports area, not mixed in here.

**Attendance tab**

17. As an Academic staff member, I want an attendance summary per course with the ability to drill into detail, so that I can assess engagement.

**Lifecycle tab (Student Actions + Academic Progression + Decisions)**

18. As an Academic staff member, I want one chronological Lifecycle timeline that merges status actions and EGC progression transitions, so that I see the student's journey the way I actually think about it, not split across two tables.
19. As an Academic staff member, I want to record a Student Action (defer, resume, dropout, campus transfer, admission deferral) from the Lifecycle tab, so that I never leave the student to change their status.
20. As an Academic staff member, I want to manage EGC placement and progression (initialize placement, record IELTS, change English level, transition into intake_course) from the same tab, so that the EGC pathway is handled in context.
21. As an Academic staff member, I want EGC level and IELTS history shown as a detail panel under the timeline, so that the main timeline stays about transitions while the level detail is still available.
22. As an Academic staff member, I want a transition that requires a Decision to be recordable *without* a Decision attached yet, so that I can change a student's status when the signed quyết định has not been entered.
23. As an Academic staff member, I want to attach (backfill) the authorizing Decision to a transition later, so that the paperwork can follow the operational change.
24. As an Academic staff member, I want each transition to show its authorizing Decision inline, so that I can see provenance at a glance.
25. As an Academic staff member, I want EGC-only progression to apply only to students whose status is `intake_pre_uni_gc`, so that the EGC controls do not appear for students outside EGC.

**Decisions**

26. As an Academic staff member, I want a Decision to cover many students at once, so that one quyết định (e.g. deferring N students) is entered once and linked to all of them.
27. As an Academic staff member, I want to attach a student to a Decision without creating any status change, so that purely informational decisions can be recorded.
28. As an Academic staff member, I want a Decision to be usable to authorize transitions in either lifecycle stream, so that intake_gc / intake_course progression and status actions can both cite the same document.
29. As a Director or Head, I want a missing-decision report listing transitions that require a Decision but still lack one, so that staff can backfill and the record becomes complete.

**Graduation tab**

30. As an Academic staff member, I want graduation requirements and progress in a visible tab, so that I can assess completion without typing a URL to a hidden view.

**Finance tab (read-only)**

31. As an Academic staff member, I want a read-only finance summary (fees, gold wallet, scholarships) in the Hub, so that I have financial context for the student.
32. As an Academic staff member, I want a deep link from the finance summary into the Finance Office, so that anyone who needs to act on money goes to the surface that owns it.
33. As an Academic staff member, I want the Hub to never let me mutate money, so that finance ownership and audit stay with the Finance module.

**Export**

34. As an Academic staff member, I want the Export action to actually produce an academic summary export, so that the dead "coming soon" button becomes useful.

**Reports / management area (separate)**

35. As a Director or Head, I want the aggregate audits (student-actions audit, academic-progression audit and its missing-documents view, lifecycle-yearly, performance dashboard, course ranking, academic report) grouped in one management area, so that oversight work is separate from per-student work.
36. As a Director or Head, I want to click a row in any report and land on that student's Hub at the relevant tab, so that I can drill from a pattern to an individual.
37. As a Director or Head, I do not need a link from the Hub back into the audits, so that the per-student surface stays focused (the Hub already holds the full per-student timeline).

**Other roles and navigation**

38. As a non-Academic-Affairs staff member, I want to see only a few basic read-only student fields, so that I am not exposed to operational controls I should not use.
39. As any staff member, I want a single "Student" entry in the navigation that leads to the Hub, so that student work is no longer scattered across four top-level menus.

**Retiring old surfaces**

40. As an Academic staff member, I want old bookmarks to `/students/{id}/actions` and `/students/{id}/placement` to redirect into the corresponding Hub tab, so that the consolidation does not break my links.
41. As a platform operator, I want the orphan Show page removed, so that there is no second, divergent student-detail surface to maintain.

## Implementation Decisions

- **Ownership.** The Academic module owns the Hub. Reads come from module **Queries**; writes go through module **Actions**. The Finance module exposes a **read-only student finance summary** consumed by the Hub through a cross-module read contract — no Eloquent joins across the Academic/Finance boundary (CONTEXT.md conventions; ADR-0007).
- **Hub surface.** The Hub is the upgraded academic-summary surface. Tabs: Overview, Registrations, Scores & GPA, Attendance, Lifecycle, Graduation, Finance, plus a persistent context bar. Each tab's data is produced by a Query and composed into Inertia props per the existing per-tab academic-summary route pattern.
- **Retire / redirect.** Remove the orphan `Show` page (fold its useful fields into Overview). Redirect the two standalone per-student routes (actions, placement) into the Lifecycle tab.
- **Lifecycle timeline (ADR-0009).** The Lifecycle tab is a single chronological view merged from `StudentActionLog` and `AcademicProgressionEvent`, ordered by event time, with Decisions inline. EGC English-level changes and IELTS records render in a sub-panel, not the main timeline.
- **Decision model (ADR-0008).** Add a **Decision ↔ Student many-to-many roster** (a decision covers many students; this also represents informational decisions). `AcademicProgressionEvent` gains a nullable authorizing-decision reference; `StudentActionLog` keeps its existing one. The event reference is authorization provenance; the roster is the document's coverage list.
- **Requires-decision rule (soft).** The set `{defer, resume, dropout, campus_transfer, intake_gc (→ intake_pre_uni_gc), intake_course}` requires a Decision *eventually*, but a transition may be recorded without one. A **missing-decision report** lists transitions in the set that still lack a Decision (mirrors the existing missing-documents report). `admission_deferral` and pure progression records (English-level change, IELTS) never require a Decision.
- **Enabled views.** Turn on the GPA and Graduation tabs (currently commented out). Make Export a real academic-summary export action.
- **Finance summary.** Read-only fees, gold, scholarships summary + deep link into Finance Office. No mutation paths in the Hub.
- **Registrations → class.** Each registration row exposes its course-offering reference so the UI links into the class.
- **Reports area.** Keep the aggregate audits as a separate management menu group; each report row deep-links one-way into the student's Hub tab. Add the missing-decision report to this area.
- **Navigation.** Introduce a single "Student" nav entry to the Hub and consolidate the previously scattered student items; management reports live under the management group.
- **Permissions.** Hub view gated by `view_student_summary`; act capabilities gated by `change_student_status` / `view_student_action` (existing gates). Other roles get a reduced read-only field set.
- **Data migration.** Backfill the Decision↔Student roster from existing `StudentActionLog.decision_id` links; keep those links intact; add the decision reference column to progression events.

## Testing Decisions

- **What a good test is.** Tests assert external behavior (inputs → resulting records, query outputs, enforced rules), never implementation details. UI rendering is not unit-tested — the FE has only `vue-tsc`/ESLint — so correctness rides on the Query data contracts plus the existing academic-summary endpoint smoke test; the Vue is verified manually/visually. Feature tests must set the session `_token` (CSRF is active in feature tests).
- **Seam 1 — lifecycle write (Actions).** `RecordStudentActionAction` and the parallel progression action are the single seam for write rules: a requires-decision transition succeeds with no Decision and then appears in the missing-decision query; attaching a Decision removes it; a Decision links to many students; EGC/eligibility hard rules are unchanged. Prior art: `tests/Feature/Academic/StudentActionEgcDeferBlockTest.php`, `tests/Feature/Academic/StudentDecisionBulkLinkTest.php`.
- **Seam 2 — Hub reads (Queries).** Per-tab read queries plus the **new unified lifecycle-timeline query** (correct merged ordering across both event tables), registrations exposing the class link, and the read-only finance summary. The missing-decision report is tested at this seam too (mirror the missing-documents query). Prior art: `ListStudentActionLogsQuery`, `tests/Feature/Academic/GetStudentAttendanceQueryTest.php`, `tests/Feature/Academic/GetStudentFeeSummaryQueryTest.php`, `tests/Feature/Academic/StudentAcademicSummaryScoresTest.php`.
- **Modules tested.** Academic (hub reads + lifecycle writes + decision model). Finance is touched only through its read-only summary contract (assert it returns a summary; no mutation).

## Out of Scope

- Any money operation inside the Hub — recording payments, allocations, adjustments stay in the Finance Office.
- A per-student timetable / session calendar in the admin Hub — that belongs to the student and lecturer portals (separate Nuxt apps), which already have it.
- Redesigning the internals of the aggregate reports — only their **separation** into a management area, the **one-way** link into the Hub, and the **new missing-decision report**.
- The student / lecturer / parent portals (separate Nuxt apps).
- A new Vue component test harness.
- The AI Staff Copilot itself — the Hub mirrors its **Case review section bundle** vocabulary, but the copilot behavior is unchanged.

## Further Notes

- The Hub deliberately mirrors the AI **Case review section bundle** (CONTEXT.md) so the UI and the Staff Copilot speak the same student vocabulary.
- The missing-decision report intentionally mirrors the existing **missing-documents** report pattern under academic-progression — reuse its shape.
- Respect `docs/adr/0007` (single console + finance boundary + reports one-way), `0008` (Decision model + soft requires-decision), `0009` (unified lifecycle timeline).
- Likely natural issue seams for `/to-issues`: (a) Decision model + migration + missing-decision report; (b) Lifecycle tab unified timeline (absorb actions + placement pages); (c) Hub shell + context bar + Overview (absorb Show); (d) enable GPA/Graduation + Export; (e) Registrations→class + Scores/Attendance polish; (f) Finance read-only summary contract; (g) Reports management-area regroup + nav consolidation + redirects.
