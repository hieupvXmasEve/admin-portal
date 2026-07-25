---
id: ADR-0037
title: "Guardian relationship is independent of portal access"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Guardian relationship is independent of portal access

Approving an Application preserves every Guardian as a Student Registry-owned Student–Guardian Relationship even when the Guardian has no email or account. Identity & Access separately owns the optional Guardian Access Grant and its access level, so creating, revoking, or changing portal access never creates or deletes the underlying family or responsibility relationship.
