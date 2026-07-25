---
id: ADR-0034
title: "Account, Program Enrollment, and Study Stage are separate state machines"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Account, Program Enrollment, and Study Stage are separate state machines

Swinx does not use one Student status to represent authentication, enrollment lifecycle, and academic progression. Identity & Access owns Account Status, Academic Progression & Lifecycle owns Program Enrollment Status and Study Stage, and Student Identity carries none of those states; cross-context gates ask the owning context instead of interpreting a shared enum.
