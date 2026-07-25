---
id: ADR-0035
title: "Course Results and Transcript Entries have separate owners"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Course Results and Transcript Entries have separate owners

Course Delivery & Assessment owns Course Offerings, rosters, attendance, component scores, grading rules, and each finalized Course Result. Academic Progression & Lifecycle consumes finalized or recalculated Course Results to own Transcript Entries, GPA, Academic Standing, best-attempt, and graduation decisions; it does not read the gradebook's internal tables to derive those records.
