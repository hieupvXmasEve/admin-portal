---
id: ADR-0043
title: "Cross-context interactions use contracts, events, or projections"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Cross-context interactions use contracts, events, or projections

An immediate decision or atomic state change uses a narrow synchronous Query or Command Contract owned by the receiving context; a post-commit reaction uses a durable Domain Event and outbox; reporting, dashboards, and AI use owner-provided readers or rebuildable Cross-context Read Projections. Contexts do not import another context's Eloquent models or concrete Actions, projections never decide hard business gates, Notification owns delivery rather than source business rules, and AI remains a read-only consumer rather than a source of truth.
