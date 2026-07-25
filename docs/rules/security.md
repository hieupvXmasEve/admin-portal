---
title: Security and Authorization Rules
status: active
owner: Platform Team
last_verified: 2026-07-25
scope: engineering-rules
applies_to:
  - app
  - routes
  - config/permission.php
---

# Security and Authorization Rules

Authentication establishes identity. A Gate checks a general permission; a
Policy checks whether that identity may act on a specific record. Campus scope
and actor context are additional boundaries, not substitutes for authorization.

## Gates and policies

| Scenario | Control |
|---|---|
| Route or menu without a resource instance | Gate or permission middleware |
| Action on a bound resource | Policy |
| Broad actor/campus context | Middleware |
| Business precondition after access is granted | Action/domain rule |

- Gates remain simple permission checks. They do not query domain data, accept
  models, or make business decisions.
- Policies may check ownership, campus, record state, and general permissions.
- Controllers authorize resource actions through the policy mechanism rather
  than direct `Gate::allows()` calls.
- UI visibility improves usability but never replaces backend enforcement.
- Keep permission definitions centralized in `config/permission.php`.

## API baselines

- Student and Lecturer v1 APIs use Sanctum plus their actor middleware chain.
- Parent access to Student APIs uses the explicit parent-proxy middleware path.
- Preserve the existing token-refresh rotation contract unless an intentional
  auth migration changes it with targeted compatibility tests.
- Server-to-server admissions ingestion uses a dedicated Sanctum service
  account ability, rate limiting, allowlisting, and audit as described in
  ADR-0004.
- Existing Finance API groups that use `web` plus `auth` are documented
  exceptions; do not silently copy that pattern to new APIs.

## Boundary rules

- Validate and authorize campus scope at the request boundary and again where a
  cross-campus operation could be constructed.
- Never trust a client-provided actor, campus, permission, price, grade, or
  lifecycle decision without server-side resolution.
- Credentials and provider secrets never enter logs, flash data, client props,
  or audit payloads.
- Validate webhook authenticity before queueing or applying provider state.
- Use named, revocable abilities for machine access rather than broad user
  tokens.

## Review checklist

- Is every route protected by the correct authentication and actor middleware?
- Does a route with a model instance reach a Policy check?
- Are campus and ownership checks enforced server-side?
- Are validation and authorization distinct from the business rule?
- Could the response or audit trail expose secrets or unauthorized fields?
