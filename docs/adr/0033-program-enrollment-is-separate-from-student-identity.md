---
id: ADR-0033
title: "Program Enrollment is separate from Student Identity"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Program Enrollment is separate from Student Identity

Program, Curriculum Version, Intake Academic Period, and their lifecycle history belong to an Academic Progression & Lifecycle-owned Program Enrollment rather than Student Identity. Swinx initially enforces at most one primary active Program Enrollment per Student, while the aggregate preserves transfer and historical enrollments without expanding the shared Student reference.
