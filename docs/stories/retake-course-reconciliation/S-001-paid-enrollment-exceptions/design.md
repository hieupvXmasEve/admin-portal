# Design

## Domain Model

Keep the existing retake lifecycle status, but stop treating it as the only UI
truth:

- `approved`: academic approval exists; payment flow may not have started.
- `payment_pending`: retake charge exists but is not fully paid.
- `paid`: charge is fully paid but the retake registration is not actively linked
  to a class roster.
- `enrolled`: payment is recorded and a class registration has been linked at
  least once.
- `cancelled`: registration was cancelled before payment completion.

Add a derived reconciliation state for staff operations:

- `awaiting_payment`: no full payment yet.
- `paid_unassigned`: fully paid, no `course_registration_id`; waits for staff to
  add the student to a class. The system may link an existing class registration
  after staff adds it, but must not create one automatically.
- `enrolled_active`: linked `course_registrations` row exists, matches student and
  offering, and is active for the class roster.
- `enrollment_removed`: retake registration says `enrolled`, but the linked
  course registration is dropped, withdrawn, deferred, deleted, or otherwise not
  roster-active.
- `link_mismatch`: linked course registration exists but points at a different
  student, offering, or non-retake state.
- `manual_hold`: staff acknowledged the exception and intentionally kept it
  unresolved.

Payment and enrollment rules:

- A paid retake registration without any prior class link stays paid/unassigned
  until staff manually adds the student to a class.
- A paid retake registration with an existing active target `CourseRegistration`
  can be linked and marked `is_retake = true`.
- A registration that was once enrolled and later removed must be routed to a
  manual exception. The system should require staff to choose whether to re-add,
  move class, keep on hold, cancel/adjust finance, or link a correct existing
  registration.

## Application Flow

Backend structure:

- Add `ListRetakeCourseRegistrationsQuery` under `app/Modules/Academic/Queries/`
  so the controller no longer builds table state inline.
- Extend the row DTO with `payment_state`, `enrollment_state`,
  `operation_state`, `available_actions`, and `exception_summary`.
- Keep `RetakeRegistrationPaymentSyncer` as the cross-module contract Finance
  calls after DNG webhook/payment allocation.
- Introduce `ReconcileRetakeRegistrationEnrollmentAction` in
  `app/Modules/Academic/Actions/`.

Reconciliation modes:

- `sync_safe`: record fully paid retake registrations and link only when a
  matching active `CourseRegistration` already exists.
- `detect_exceptions`: detect unsafe cases and open/update an exception without
  changing class membership.
- `resolve_manual`: perform staff-selected resolution with reason, user id, and
  audit metadata.

Manual resolution actions:

- `auto_link_target`: reuse the target course registration and link it; never
  create the class registration from payment sync.
- `link_existing`: link a selected active `CourseRegistration` for the same
  student/unit/semester.
- `readd_to_class`: create a new active `CourseRegistration` after a previous one
  was dropped/withdrawn/deleted; requires reason.
- `move_to_offering`: create/link registration in another offering of the same
  unit/semester; requires reason.
- `hold`: keep exception open or acknowledged with reason.
- `cancel_or_adjust`: do not mutate finance directly from the retake screen;
  route staff to the finance charge/invoice workflow.

## Interface Contract

Admin web routes:

- `GET /retake-course`: returns registrations plus derived reconciliation fields.
- `POST /retake-course/{registration}/sync`: runs safe sync for one registration;
  it may mark the registration paid and link an existing class registration, but
  it does not create a new class registration.
- `POST /retake-course/{registration}/resolve-enrollment-exception`: applies a
  staff-selected manual resolution.

Controller rules:

- Controllers validate requests, call Actions/Queries, and return Inertia
  responses or redirects with `Inertia::flash()`.
- Use FormRequest classes for resolution actions.
- Do not call Finance Eloquent internals from Academic except through current
  model relationships and the shared payment sync contract.

## Data Model

Recommended persistent exception table:

`course_retake_enrollment_exceptions`

- `id`
- `course_retake_registration_id`
- `issue_type`: `paid_unassigned`, `enrollment_removed`, `link_mismatch`,
  `target_offering_invalid`, `auto_enroll_failed`
- `status`: `open`, `acknowledged`, `resolved`, `ignored`
- `severity`: `high`, `medium`, `low`
- `detected_at`
- `last_seen_at`
- `resolved_at`
- `resolved_by_user_id`
- `resolution_action`
- `resolution_reason`
- `metadata` JSON
- timestamps

Indexes:

- Unique open issue per `course_retake_registration_id + issue_type + status`
  where possible, or application-level idempotency if the database cannot express
  a partial unique index.
- `status, issue_type, detected_at` for queue filters.
- `course_retake_registration_id` for detail drawer lookup.

The derived state can still be computed live for each row. The table exists for
acknowledgement, ownership, history, and manual resolution audit.

## UI / Platform Impact

Retake-course list should stop showing one overloaded status badge. Display two
separate chips:

- Payment: `Chờ thanh toán`, `Đã thanh toán`, `Đã hủy`, `Lệch dữ liệu`.
- Class: `Chờ staff thêm lớp`, `Đang trong lớp`, `Đã rời lớp`, `Cần kiểm tra`.

Primary operation state can be shown as a concise badge:

- `Chờ thanh toán`
- `Đã thu - chờ thêm lớp`
- `Đã ghi danh`
- `Cần xử lý`
- `Đã hủy`

UX additions:

- Summary strip above the table: `Chờ thanh toán`, `Đã thu - chờ thêm lớp`,
  `Đang trong lớp`, `Cần xử lý`.
- Quick filter: `Đã thu tiền nhưng chưa trong lớp`.
- Row action buttons with icons and tooltips:
  - rescan/link for `paid_unassigned` when staff has already added a class
  - alert/review for `enrollment_removed` and `link_mismatch`
  - cancel only where cancellation is legally valid
- Detail drawer with timeline:
  - retake registration created
  - charge created
  - payment received
  - class linked
  - class removed/mismatch detected
  - manual resolution
- Resolution modal requiring a reason for `readd_to_class`, `move_to_offering`,
  `hold`, and any finance-directed outcome.

## Observability

- Log reconciliation runs with checked, synced, opened exception, resolved, and
  failed counts.
- Use existing auditable models for changes to `CourseRetakeRegistration` and
  `CourseRegistration`.
- Persist exception metadata with previous and new `course_registration_id`,
  registration status, offering id, acting user id, and selected resolution.
- Artisan command should support `--dry-run`, `--student-code`, and
  `--registration-id`.

## Alternatives Considered

1. Auto-create or auto-readd every paid retake registration that is not in class.
   - Rejected because removal from class may be intentional and should not be
     reversed without academic approval, and class placement is a staff-owned
     academic action.
2. Only compute derived state in the table, with no persistent exception record.
   - Useful as phase 1, but insufficient for acknowledgement, ownership, and
     resolution audit.
3. Put all cases into Finance billing exceptions.
   - Rejected as the primary UX because this is not only a billing issue; the
     staff decision is academic enrollment resolution.
