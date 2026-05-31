# Exec Plan

## Goal

Make `/retake-course` operationally clear and safe by separating paid status
from active class membership, syncing paid fees without creating class
registrations automatically, and surfacing previously removed class enrollments
as manual exceptions.

## Scope

In scope:

- Derived payment/enrollment/operation states for retake registration rows.
- Safe reconciliation action for fully paid registrations, without creating class
  registrations automatically.
- Manual exception detection for enrolled registrations whose class link is no
  longer roster-active.
- Staff UI for viewing and resolving retake enrollment exceptions.
- Audit metadata for manual resolution.

Out of scope:

- Student or lecturer portal changes.
- DNG provider protocol changes.
- Automatic class registration creation from payment sync.
- Automatic re-add after a prior class removal.
- Full replacement of Finance billing exception pages.

## Risk Classification

Risk flags:

- Data model.
- Audit/security.
- Public contracts.
- Existing behavior.
- Weak proof.
- Multi-domain.

Hard gates:

- Audit-sensitive operations around paid fees and class membership.

## Work Phases

1. Discovery.
   - Confirm current removal flows for `CourseRegistration` and whether rows are
     soft-deleted, hard-deleted, or status-mutated.
   - Confirm existing permissions for retake management and whether a new
     resolve permission is needed.
2. Backend design implementation.
   - Add query DTO fields for derived states.
   - Add reconciliation Action and exception persistence.
   - Add single-registration sync and manual resolution Actions.
3. UI implementation.
   - Split status display into payment chip and class chip.
   - Add summary strip, quick filter, detail drawer, and resolution modal.
4. Data repair/reconciliation.
   - Run dry-run command to count current exceptions.
   - Sync safe cases.
   - Leave removed/mismatched cases open for staff decision.
5. Verification.
   - Run targeted Academic retake tests, DNG retake regression tests, Pint, and
     frontend lint/format checks.
6. Harness update.
   - Record traces, acceptance evidence, and any ADR if the behavior is accepted.

## Stop Conditions

Pause for human confirmation if:

- Staff want automatic re-add after class removal.
- Paid cancellation/refund should happen from Academic instead of Finance.
- New permissions or audit retention requirements differ from existing policy.
- Current data shows hard-deleted course registrations that cannot be linked to
  an auditable prior decision.
