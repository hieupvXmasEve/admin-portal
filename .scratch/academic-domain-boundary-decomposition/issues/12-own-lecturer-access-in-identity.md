# Own Lecturer Access Grant and Faculty Access Eligibility

Status: completed

Portal impact: lecturer

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Separate Faculty Workforce employment/contract eligibility from Identity-owned lecturer authentication. Faculty Workforce supplies Faculty Access Eligibility with a reason; Identity stores the final Lecturer Access Grant and makes login/refresh decisions without querying Faculty persistence. Employment transitions synchronously revoke access when required and publish durable reconciliation evidence.

## Acceptance criteria

- [x] Lecturer login and token refresh read only Account Status and Lecturer Access Grant for authorization decisions.
- [x] Faculty Access Eligibility has one canonical vocabulary derived from valid employment and contract states.
- [x] Termination, suspension, and contract expiry synchronously revoke lecturer access through an Identity-owned command.
- [x] Leave and sabbatical preserve access by default unless an explicit policy changes the grant.
- [x] Failed or interrupted eligibility handoffs are recoverable through durable events/reconciliation without duplicate revocations.
- [x] Existing lecturer login, Google login, refresh, authorization errors, and API response shapes remain compatible.
- [x] Architecture tests reject Identity reads of Faculty persistence; focused tests and lecturer portal checks pass.

## Blocked by

- [Issue 01: Publish Academic lifecycle notifications through the shared Domain Event boundary](01-publish-academic-lifecycle-notifications-through-shared-boundary.md)
- [Issue 02: Own Institution Campus and Department through the Institution admin flow](02-own-campus-and-department-through-institution.md)

## Comments

- Completed 2026-07-18. Added Identity-owned lecturer access grants and idempotent handoff evidence; Faculty Workforce now emits canonical eligibility through a durable outbox, with hourly reconciliation for pending/failed delivery and contract-expiry drift.
- Verification: `./scripts/dev.sh artisan test --compact`, `./scripts/dev.sh npm run type-check`, Pint, focused Identity/architecture tests, lecturer auth route listing, and lecturer portal status inspection. The lecturer portal required no code change because the auth paths and response envelopes remain unchanged.
