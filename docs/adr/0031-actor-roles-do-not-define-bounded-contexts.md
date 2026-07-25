---
id: ADR-0031
title: "Actor roles do not define bounded contexts"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Actor roles do not define bounded contexts

Student, guardian (currently exposed as parent), and lecturer are Actor roles, not top-level bounded contexts or portal-shaped modules. Identity & Access owns accounts and access grants, Student Registry owns Student Identity and guardian relationships, Faculty Workforce owns faculty employment profiles, and Academic contexts own study lifecycle, delivery, and course assignments; this avoids rebuilding cross-domain God Models around each portal user.
