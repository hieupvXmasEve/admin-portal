---
id: ADR-0032
title: "Academic Period is institution-wide"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Academic Period is institution-wide

Swinx uses one institution-wide Academic Period identity for teaching, progression, reporting, Admissions intake, and Finance correlation instead of duplicating Semesters per campus. If campuses later require different operating dates or registration windows for the same period, Academic Catalog & Calendar may add a campus-specific schedule overlay while preserving the shared Academic Period identity.
