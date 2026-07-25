---
id: ADR-0049
title: "Student Hub shows one unified lifecycle timeline"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Student Hub shows one unified lifecycle timeline

**Context.** A student's lifecycle is stored in two tables: status changes in `StudentActionLog` (defer, dropout, campus transfer, admission deferral, resume) and EGC progression in `AcademicProgressionEvent` (placement, English-level change, course-stage change, IELTS). But Academic Affairs staff think of these as one journey — they group academic defer together with the `intake_pre_uni_gc` / `intake_course` transitions as "status changes that need a decision," even though those transitions live in different tables.

**Decision.** The hub's **Lifecycle** tab presents a **single chronological timeline merged from both event tables**, with each authorizing Decision shown inline. EGC-specific detail (English-level history, IELTS records) is a secondary panel under the timeline, not a separate tab. This matches how staff reason about the student, rather than mirroring the storage split.

**Consequences.**

- The timeline is built by merging two sources ordered by event time; this is deliberate. A future reader should not "fix" it by splitting the tab back into two — the merge is the feature.
- Status-changing transitions surface in the main timeline regardless of which table backs them; non-status progression events (level change, IELTS) sit in the EGC detail panel.
- Pairs with ADR-0048: decisions render inline on the transitions they authorize.
