# Project Overview and PDR

Last updated: 2026-03-04  
Owner: Platform Team  
Status: Current-state baseline (evidence-first)  
Source of truth: code in `app/`, `routes/`, `resources/` + scout reports in `plans/reports/`

## 1) Product Summary

Swinx is a multi-role university operations system delivered as:

- Web admin/staff app (Laravel + Inertia)
- Student API surface (`/api/v1/student/*`)
- Lecturer API surface (`/api/v1/lecturer/*`)
- Parent-linked access to selected student API routes

Core active domains:

- Identity
- Academic
- Finance
- Notification (Phase 1 foundation)

## 2) Stakeholders and Ownership

- Product owner: internal operations and academic administration
- Engineering owner: Platform Team
- Documentation owner: Platform Team (update with each route/contract/auth change)

## 3) Functional Requirements (Current State)

### FR-01 Authentication and Actor Segmentation

Code baseline:

- Sanctum token auth is active for student/lecturer v1 APIs.
- Actor policies are enforced via `api.actor` middleware for:
    - `student_or_parent`
    - `parent`
    - `lecturer`
- Parent proxy access uses `either:parent.student.access,student.api.auth` on student routes.

Acceptance criteria:

- Protected student and lecturer route groups reject unauthenticated requests.
- Actor mismatch fails authorization.

### FR-02 Token Lifecycle Hardening

Code baseline:

- Identity login/refresh actions issue 8-hour tokens (`now()->addHours(8)`).
- Lecturer refresh endpoint is in protected middleware group.
- Parent refresh endpoint exists in protected group.
- Refresh flow in current controllers issues new token then deletes current token.

Acceptance criteria:

- `/api/v1/student/auth/refresh`, `/api/v1/lecturer/auth/refresh`, `/api/v1/student/parent/auth/refresh` require auth.
- Refresh returns a new token and revokes the old one.

### FR-03 Student Action Import (Academic)

Code baseline (`app/Modules/Academic/routes/web.php`):

- Import page
- Template download
- Preview import
- Execute import

Behavior baseline:

- Anti-tamper preview/execute token + file hash + `shared_upload_record_id` matching.
- admission-deferral action type excluded from import flow.
- Append rules limited to same student + same action type + same period.
- Per-row transaction on execution.

Acceptance criteria:

- Tampered payloads are rejected at execute step.
- Unsupported action type is blocked from import path.

### FR-04 Student Decisions Registry and Action Linking

Code baseline:

- Web registry routes exist under `/reports/student-decisions` in `app/Modules/Academic/routes/web.php` (`index`, `show`, `store`, `update`).
- Decision records are stored in `student_decisions` (`decision_name`, `decision_number`, `decision_signer`, `issued_at`, `expires_at`, `upload_record_id`, `changed_by_user_id`).
- `student_action_logs.decision_id` is a nullable FK to `student_decisions.id` with `nullOnDelete`.
- Student action create/update requests validate `decision_id` with `exists:student_decisions,id`.
- Student action read paths and decision registry queries include decision linkage metadata.
- Student decision index query contract accepts `search`, `issued_from`, `issued_to`, `per_page`, `page`, `sort`, and `direction`.
- Sorting is allowlisted to `decision_number`, `decision_signer`, `issued_at`, and `expires_at` with sanitized defaults (`sort=issued_at`, `direction=desc`, `page=1`).
- Student decision detail linked-actions pagination follows shared server-table keys (`per_page`, `page`) with compatibility aliases (`linked_per_page`, `linked_page`).

Acceptance criteria:

- Staff can register and update student decisions from the Academic report surface.
- Student action logs can be linked to a registry decision via optional `decision_id`.
- Registry screens show linked action and linked student counts per decision.

### FR-05 API Surface Consistency

Code baseline:

- Student and lecturer v1 routes are mostly consistent with sanctum + actor + logging.
- Finance module API routes currently use `web` + `auth` middleware.
- Root `/api/system-config*` routes are public in `routes/api.php`.

Acceptance criteria:

- Document auth behavior by route group as implemented.
- Do not claim unified API auth model while drift remains.

### FR-06 Notification V2 Foundation (Outbox + Ops Monitoring)

Code baseline:

- Notification domain foundation is implemented in `app/Modules/Notification` with outbox persistence in `notification_event_outbox`.
- Phase 1 message store uses `notification_messages` with canonical `recipient_user_id` and optional `campus_id`.
- Recipient targets are resolved to user ids before persistence; unresolved/campus-mismatch targets are excluded.
- Operational command `notifications:process-outbox` exists and is scheduled every minute (`routes/console.php`).
- Phase 1 is clean-slate only: no legacy `notifications` table history backfill.

Ops monitoring routes (`routes/web/notifications.php`):

- `admin.notifications.ops.outbox` — list outbox records with status/event_type/date filters
- `admin.notifications.ops.outbox.detail` — single outbox record detail view
- `admin.notifications.ops.outbox.retry` — retry failed/stuck outbox records
- `admin.notifications.ops.deliveries` — list deliveries with channel/status filters
- `admin.notifications.ops.deliveries.retry` — retry failed deliveries
- `admin.notifications.ops.messages` — list messages with recipient/status filters

Acceptance criteria:

- Outbox records can be processed through `notifications:process-outbox` into message/delivery records.
- Campus-mismatched targets are rejected during recipient resolution.
- New V2 records are keyed by `recipient_user_id`; non-user targets do not bypass resolution.
- Documentation and implementation do not claim legacy backfill in Phase 1.
- Admin users can monitor outbox/messages/deliveries through ops pages.
- Failed outbox records and deliveries can be retried via ops routes.

## 4) Non-Functional Requirements

- Security:
    - middleware/policy-based authorization
    - explicit actor segregation for major public API surfaces
- Maintainability:
    - modular + shared-layer hybrid architecture documented and enforced by standards
- Reliability:
    - deployment and CI risks tracked as current-state gaps
- Documentation quality:
    - evidence-first claims only
    - explicit owner/status/last-updated discipline

## 5) Technical Constraints

- PHP `^8.2`, Laravel `^12.0`
- Node/npm frontend toolchain
- Sanctum for token auth surfaces
- MySQL/MariaDB + Redis expected by active workflows

## 6) Current Risks

- Public `system-config` endpoints allow unauthenticated read/write/upload at `/api/system-config*`.
- Finance API auth style drift (`web` + `auth`) vs actor-based API model.
- CI workflows are present but disabled (commented out).
- Scripts and compose paths are inconsistent across repo.
- Deployment artifacts include credentials/secrets exposure risk.
- Frontend still has literal URL pockets despite heavy route helper usage.
- Notification history is split between legacy and V2 stores until a later migration phase.

## 7) Success Metrics (Current-State Tracking)

- Documentation freshness: core docs updated when route/auth/contracts change.
- Security posture: count of unresolved high-risk auth/deploy issues trends down.
- Delivery posture: CI workflows re-enabled with required checks.
- Contract stability: fewer literal URL hotspots in frontend and fewer route drift incidents.

## 8) Version History

- 2026-03-04: Expanded FR-06 with Notification V2 ops monitoring routes (outbox/messages/deliveries) and retry capabilities.
- 2026-03-03: Added Notification V2 Phase 1 foundation requirements (outbox pipeline, campus isolation, canonical `recipient_user_id`, no legacy backfill) and command baseline `notifications:process-outbox`.
- 2026-02-27: Documented student decision index sort/direction/page contract and sanitized sort allowlist defaults.
- 2026-02-27: Documented student decision detail pagination migration to shared server-table query keys with backward-compatible aliases.
- 2026-02-26: Added Student Decisions registry + `student_action_logs.decision_id` linkage baseline and acceptance criteria.
- 2026-02-25: Updated with Phase 1 + 1.5 scout/doc-reader context; aligned auth hardening and open risk statements.
- 2026-02-23: Initial baseline version.

## Unresolved Questions

- Should `/api/system-config*` be moved behind admin auth/authorization?
- Should Finance module APIs migrate to Sanctum + actor middleware?
- Which API surfaces are external client contracts vs internal web AJAX contracts?
- Should `EitherMiddleware` be replaced with explicit route group middleware composition?
