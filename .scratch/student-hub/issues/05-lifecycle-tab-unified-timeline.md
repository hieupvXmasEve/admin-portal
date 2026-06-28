# Hub Lifecycle tab: unified timeline + in-place actions & EGC placement/progression

Status: ready-for-agent

## Parent

`.scratch/student-hub/PRD.md`

## What to build

The Lifecycle tab as a single chronological timeline merging the two lifecycle streams, with in-place actions. Respect ADR-0009 (unified timeline) and ADR-0008 (Decision model).

End-to-end behavior:

- One chronological **timeline merged from `StudentActionLog` and `AcademicProgressionEvent`**, ordered by event time, with authorizing **Decisions shown inline** on the transitions they authorize. EGC English-level and IELTS history render in a **sub-panel** under the timeline, not in the main timeline.
- Record **Student Actions** (defer, resume, dropout, campus transfer, admission deferral) in place.
- Manage **EGC placement/progression** (initialize Placement, record IELTS, change English level, transition into `intake_course`) in place. EGC controls appear **only for Students whose status is `intake_pre_uni_gc`**.
- A requires-decision transition can be recorded **without** a Decision and **backfilled** later (uses the model from issue 02); transitions still missing their required Decision are visibly flagged.
- The retired standalone routes `/students/{id}/actions` and `/students/{id}/placement` **redirect** into this tab.

## Acceptance criteria

- [ ] The Lifecycle tab shows one chronological timeline merged from Student Actions + Academic Progression, ordered by event time.
- [ ] Authorizing Decisions render inline on the transitions they authorize; EGC level/IELTS history is a sub-panel.
- [ ] Staff can record Student Actions and EGC placement/progression in place without leaving the Student.
- [ ] EGC controls appear only for `intake_pre_uni_gc` Students.
- [ ] A requires-decision transition records with no Decision and can be backfilled; the missing state is visible.
- [ ] `/students/{id}/actions` and `/students/{id}/placement` redirect into the Lifecycle tab.
- [ ] Tests at the Action seam (record action/progression, soft requires-decision, EGC gating) and Query seam (merged timeline ordering).

## Blocked by

- `.scratch/student-hub/issues/01-hub-shell-overview-and-role-aware-rendering.md`
- `.scratch/student-hub/issues/02-decision-model-and-missing-decision-report.md`
