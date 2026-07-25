---
id: ADR-0042
title: "Admissions approval is atomic cross-context orchestration"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Admissions approval is atomic cross-context orchestration

Admissions owns Approve and its staff-authorized Application transition, but orchestrates Student Registry, Identity & Access, and Academic Progression & Lifecycle through Cross-context Command Contracts to create Student Identity and guardian relationships, the account and access grants, and Program Enrollment. Approval remains one transactional action from a pending Application to the complete admitted-student outcome; there is no second Swinx review step because the CRM screens upstream, and the CRM API cannot approve, reject, or revoke.

In the modular monolith these writes remain one database transaction; Admissions never writes another context's models directly. Approval facts are recorded on the Application and full diffs remain in the existing activity log rather than a duplicate status-events table. Revoke uses the same ownership boundaries while retaining ADR-0002's downstream-activity guard.
