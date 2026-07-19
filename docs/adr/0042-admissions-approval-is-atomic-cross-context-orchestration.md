# Admissions approval is atomic cross-context orchestration

**Status:** accepted (refines ADR-0001)

Admissions owns Approve and its staff-authorized Application transition, but orchestrates Student Registry, Identity & Access, and Academic Progression & Lifecycle through Cross-context Command Contracts to create Student Identity and guardian relationships, the account and access grants, and Program Enrollment. In the modular monolith these writes remain one database transaction; Admissions never writes another context's models directly, and Revoke uses the same ownership boundaries while retaining ADR-0002's downstream-activity guard.
