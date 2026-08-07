---
phase: 4
title: "Student notice: realtime + email deferral notification"
status: done
priority: P1
effort: "1d"
dependencies: [2]
---

# Phase 4: Student notice: realtime + email deferral notification

## Overview

When batch-studio skips a student's tuition for scholarship review, tell the
student: realtime bell + email, "your tuition for {semester} is on hold pending
scholarship review; nothing is owed yet". Mirror the shipped confirmation-email
pattern exactly (commit `41794129`), including opting into the email channel and
seeding a bilingual per-campus template.

## Requirements

- Functional:
  - New domain event `academic.scholarship_adjustment_tuition_deferred` (or a Finance-origin equivalent — see decision below), carrying: student name + code, target semester name, scholarship name under review, defer reason, dossier id + minutes_version (for dedup + action_url).
  - Delivered on realtime + email channels.
  - Idempotent: re-running the same batch does NOT re-notify the same student for the same (semester, dossier minutes_version).
- Non-functional:
  - Email template fields all resolvable; no money figures (no decision exists yet).
  - Event emission does not block or roll back charge generation.

## Architecture

**Who emits.** The skip happens inside Finance (`GenerateBatchChargesAction`),
but the notification vocabulary + factory live in Academic
(`AcademicLifecycleEventFactory`, `academic.*` event names, EventIntentMapper).
Two options:

- **Option A (recommended, KISS):** Phase 2 returns the `deferred_scholarship_review`
  student list in stats; the batch-studio controller/orchestrator (Finance HTTP
  layer that already owns the run) calls an **Academic-owned publisher** through
  a Shared contract (e.g. extend/reuse `PendingScholarshipAdjustmentReader`
  domain or add a small `ScholarshipDeferralNotifier` Shared contract) that
  builds the event via `AcademicLifecycleEventFactory` and publishes it. Keeps
  Finance out of Academic's notification vocabulary.
- **Option B:** Finance emits a `finance.*` event; add a new EventIntentMapper
  entry + template. More surface, and splits scholarship notices across two
  namespaces. Rejected unless red-team surfaces a boundary problem with A.

**Event fields.** Extend the `AcademicLifecycleEventFactory` pattern from
`scholarshipAdjustmentConfirmationRequested` (line ~176). The factory needs the
scholarship name — resolve from the student's `StudentScholarshipAward`
(available in the batch loop's eager-loaded `scholarshipAward.scholarshipDefinition`).
Pass name/code/semester/scholarship-name/reason explicitly so the template does
not re-query.

**Dedup.** `deduplicationKey = [type, dossier, id, student, id, minutes_version]`
(same shape as the confirmation event). The domain-event pipeline already dedups
on this key → re-running the batch with the dossier unchanged is a no-op. If the
dossier minutes are edited (version bump), a fresh notice is legitimate.

**Template registration (mirror `41794129`):**
- `app/Modules/Notification/Enums/NotificationTemplateTypeKey.php`: add a case (e.g. `ScholarshipAdjustmentTuitionDeferred`) + its sample-data entry (~line 106).
- `app/Modules/Notification/Support/EventIntentMapper.php`: map the new event name → intent (~line 68).
- `app/Modules/Notification/Support/NotificationEmailTemplateProvisioner.php`: add the bilingual HTML template block (~line 374) with fields `{{student_name}}`, `{{student_code}}`, `{{semester_code}}`, `{{scholarship_name}}`, `{{deferral_reason}}`, `{{action_url}}`.
- New seed migration mirroring `..._seed_scholarship_confirmation_email_template.php` (bilingual, per-campus).
- Event opts into the email channel explicitly (academic events default realtime-only).

## Related Code Files

- Modify: `app/Modules/Academic/Progression/Support/AcademicLifecycleEventFactory.php` (new factory method)
- Create: `app/Modules/Academic/Progression/Actions/ScholarshipAdjustment/PublishTuitionDeferredNotificationAction.php` (mirror `PublishConfirmationRequestNotificationAction`)
- Create/Modify: Shared contract for the notifier if Option A (Finance → Academic publisher)
- Modify: `app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php` or its HTTP orchestrator (invoke publisher for the deferred list, post-commit)
- Modify: `app/Modules/Notification/Enums/NotificationTemplateTypeKey.php`
- Modify: `app/Modules/Notification/Support/EventIntentMapper.php`
- Modify: `app/Modules/Notification/Support/NotificationEmailTemplateProvisioner.php`
- Create: `database/migrations/..._seed_scholarship_tuition_deferred_email_template.php`

## Implementation Steps

1. Decide Option A vs B (default A). Pin the exact Shared contract + owning module.
2. Add the factory method with all fields + dedup key; opt into email channel.
3. Add the publisher action; invoke it post-commit for the `deferred_scholarship_review` list (never inside the charge transaction).
4. Register type key + sample data + EventIntentMapper entry.
5. Add the provisioner template block + seed migration (bilingual, per-campus).
6. Run the seed via `./scripts/dev.sh artisan migrate`; verify `/admin/notification-templates` lists it.
7. Tests: skip → one realtime + one email queued with correct fields; re-run batch → no duplicate; minutes version bump → new notice.

## Success Criteria

- [x] Skipped student receives realtime + email with all fields populated (no raw `{{...}}`, no money figure).
- [x] Re-running the batch does not re-notify (dedup on dossier minutes_version).
- [x] Template appears in `/admin/notification-templates`, editable, bilingual, per-campus.
- [x] Notice emission is post-commit and never rolls back generation.

## Risk Assessment

- **Risk:** Finance reaching into Academic's event factory violates the boundary. **Mitigation:** Option A routes through a Shared contract; Phase 5 boundary test covers it.
- **Risk:** email fires inside the charge transaction → partial send on rollback. **Mitigation:** publish after commit (existing `publishAfterCommit` pattern).
- **Risk:** scholarship name unresolved for edge awards → blank field. **Mitigation:** factory falls back to a generic phrase; template copy already states "no decision yet".
- **Risk (open):** if a dossier is opened (candidacy) but a batch runs before any notice — acceptable; the notice is per-skip, and Phase 3 keeps them mutually exclusive going forward.
