# Overview

## Current Behavior

`CourseRetakeRegistration.status` is currently used as a single display state for
both payment and class enrollment. This works for the happy path, but it becomes
ambiguous when a student has paid the retake fee while class linking did not
complete, or when the student was once linked to a class and was later removed.

Examples seen during investigation:

- A fully paid retake registration can remain `payment_pending` if payment
  allocation did not trigger retake enrollment sync.
- A registration can be `paid` with `course_registration_id = null` after payment
  was recorded but class linking stopped before completion.
- A registration can be `enrolled` while the linked `course_registrations` row is
  later dropped, withdrawn, mismatched, or missing. This case must not be silently
  auto-added again because the removal may have been an intentional academic
  decision.

## Target Behavior

Retake-course operations should distinguish payment collection from active class
membership:

- Payment state tells staff whether the retake charge is unpaid, fully paid,
  cancelled, or inconsistent.
- Enrollment state tells staff whether the student is actively in the target
  class, waiting for class linking, or needs manual academic review.
- Paid-but-unlinked cases wait for staff class placement; once staff adds the
  class registration, the system can link the paid retake registration.
- Previously enrolled but removed/inactive/mismatched cases are surfaced as
  exceptions for staff decision, not silently re-added.

## Affected Users

- Academic admin/staff managing `/retake-course`.
- Finance operations staff reconciling paid retake fees.
- Campus operations staff who need to know whether a paid student is actually in
  the class roster.

## Affected Product Docs

- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/code-standards.md`
- `docs/rules/backend.md`
- `docs/rules/frontend.md`

## Portal Impact

None. This story affects admin/staff web screens and internal reconciliation
logic only. It does not change `/api/v1/student/*` or `/api/v1/lecturer/*`
contracts.

## Non-Goals

- Do not auto-create class registrations from a payment event.
- Do not auto-readd students who were intentionally removed from a class.
- Do not change DNG payment provider semantics.
- Do not expose these internal reconciliation states to student or lecturer
  portals in this story.
- Do not replace the full billing exceptions system.
