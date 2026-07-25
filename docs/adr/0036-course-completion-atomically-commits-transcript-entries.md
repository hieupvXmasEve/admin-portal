---
id: ADR-0036
title: "Course Completion atomically commits Transcript Entries"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Course Completion atomically commits Transcript Entries

Finalize and Recalculate synchronously pass Course Result DTOs from Course Delivery & Assessment to Academic Progression & Lifecycle and commit the corresponding Transcript Entries in the same database transaction as Course Completion. A failure rolls back the offering transition; notifications and integration events use post-commit outbox delivery, and eventual consistency is allowed only with an explicit pending-transcript lifecycle if the contexts later become separate services.
