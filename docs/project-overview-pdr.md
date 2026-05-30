# Project Overview and PDR

Last updated: 2026-03-26  
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

### FR-08 Finance Settlement and Discount Allocation

Code baseline:

- Settlement runtime now uses `payment_applications` as the canonical line-level cash application ledger.
- Invoice discount headers live in `invoice_discounts`; line-level discount truth lives in `discount_allocations`.
- `payment_allocations` is no longer part of live settlement behavior.
- `finance/operations/settlement` is the student-centric settlement worklist for bulk/per-student apply.
- Voucher and scholarship migration commands now backfill `invoice_discounts` and `discount_allocations` using `oldest_line_first`.
- Charge generation no longer creates invoices for zero-amount tuition terms.

Acceptance criteria:

- Cash application, unapplied cash, and outstanding amounts are derived from `payment_applications`.
- Discounted net due is derived from `discount_allocations`, not header-only discount presence.
- Settlement apply works oldest invoice first, then oldest line first.
- Generate-charge preview and execution skip zero-amount tuition terms and reuse empty draft invoices when appropriate.

### FR-09 Course Survey Result Download

Code baseline:

- Admin survey result routes under `/forms/admin/results` include aggregate viewing and aggregate download for a single form target:
    - `GET /forms/admin/results/{target}/aggregate`
    - `GET /forms/admin/results/{target}/aggregate/download`
- Aggregate download uses `view_survey_results_aggregate`, matching the existing aggregate page authorization.
- The download exports course/run metadata, overall KPIs, section summaries, and per-question aggregate results without adding raw student identifiers.

Acceptance criteria:

- Staff with aggregate-result permission can download the class survey aggregate workbook from the aggregate result page.
- Staff without aggregate-result permission are denied access to the download route.
- Exported survey result workbooks do not include student ids or student names.

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

### FR-07 Academic Progression Logging and Student-Facing Notifications

Code baseline:

- EGC level changes now log through `academic_progression_events` with `ENGLISH_LEVEL_CHANGED` for both manual placement updates and auto progression after completed EGC courses.
- EGC to major transitions now log through `academic_progression_events` with `COURSE_STAGE_CHANGED`.
- `student_changes` is retained as generic field-diff audit but is not the canonical history for academic progression.
- New student-facing academic notifications are expected to publish through Notification V2 only; current complete-course and course-stage transition flows publish domain events to `notification_event_outbox` and persist to `notification_messages` / `notification_deliveries`.
- EGC to major financial transition uses `students.intake_major` as the semester milestone for tuition generation; `gc_to_course_transition_semester` is not part of the active billing path.

Acceptance criteria:

- Level/stage history can be reconstructed from `academic_progression_events` without depending on `student_changes`.
- New academic notifications for students are persisted through Notification V2 tables, not legacy `notifications`.
- Tuition transition logic reads `students.status` and `students.intake_major`.

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
- Legacy notification surfaces still exist in repo, but new academic student-facing flows are expected to use Notification V2 only.
- Finance dashboard and remaining finance reports still require final convergence on fully derived settlement math for all legacy snapshots.

## 7) Success Metrics (Current-State Tracking)

- Documentation freshness: core docs updated when route/auth/contracts change.
- Security posture: count of unresolved high-risk auth/deploy issues trends down.
- Delivery posture: CI workflows re-enabled with required checks.
- Contract stability: fewer literal URL hotspots in frontend and fewer route drift incidents.

## 8) Version History

- 2026-05-31: Added course survey class aggregate result download route and workbook contract.
- 2026-03-23: Documented academic progression logging baseline (`academic_progression_events` for level/stage changes), V2-only student-facing academic notifications for new flows, and `intake_major` as the canonical EGC to major tuition milestone.
- 2026-03-26: Added finance settlement v2 baseline (`payment_applications`, `invoice_discounts`, `discount_allocations`), settlement worklist, voucher/scholarship backfill commands, and zero-amount tuition generation rule.
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
