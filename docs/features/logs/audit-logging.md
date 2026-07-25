---
title: Audit Logging Operations
status: current
type: runbook
scope: Shared model audit logging operations
last_verified: "2026-07-25"
owner: Platform Team
audience:
  - administrators
  - developers
  - support engineers
---

# Audit Logging Operations

## Scope

This runbook covers the shared model activity log built on
`spatie/laravel-activitylog`. Domain-specific ledgers and audit stores, such as
Finance ledger entries, DNG webhook evidence, Notification Ops, and MCP traces,
remain separate authorities.

## Model logging contract

Models that extend `App\Models\AuditableModel` log dirty changes to selected
fillable fields. The base class:

- supports minimal, standard, and comprehensive field selection;
- logs dirty values only;
- skips empty change sets;
- excludes common credential fields;
- adds campus and change context;
- derives a campus-aware log name.

Authentication models use the equivalent
`StudentAuditableModel` and `UserAuditableModel` bases.

Executable owners:

- `app/Models/AuditableModel.php`
- `app/Models/StudentAuditableModel.php`
- `app/Models/UserAuditableModel.php`
- `app/Support/CampusLogContext.php`
- `database/migrations/2025_08_07_210147_create_activity_log_table.php`
- `database/migrations/2025_08_07_210148_add_event_column_to_activity_log_table.php`

Extending an auditable base is not permission to log secrets. A model with
additional credentials, tokens, raw identity documents, or sensitive payloads
must override its logged or excluded fields.

## Campus behavior

The base resolves campus context in this order:

1. the model's `campus_id`;
2. the selected campus in the session;
3. the authenticated actor's current campus context.

The activity-log page filters non-system administrators to accessible campus
log names. System administrators can optionally select a campus.

Owners:

- `app/Http/Controllers/Web/ActivityLogController.php`
- `routes/web/systems.php`
- `resources/js/pages/systems/ActivityLogs.vue`

An operation without resolvable campus context may not appear in a
campus-specific view. When implementing a background job, carry the required
campus and actor context explicitly or use the domain's dedicated audit logger.

## Adding model audit coverage

1. Confirm the shared activity log is the correct audit surface for the
   business action.
2. Extend the appropriate auditable base.
3. Review every fillable field for secrets and high-risk personal data.
4. Override the logging level or field methods when the default is too broad.
5. Add a useful identifier and custom description only when the base fallback
   is ambiguous.
6. Exercise create, update, and no-change updates in a focused test.
7. Confirm campus context and actor attribution in the stored activity.

Do not add model logging as a substitute for an immutable money ledger,
provider inbox, or security event trail.

## Operator workflow

Open the system activity log and filter by:

- free-text description or log name;
- subject type;
- event;
- campus when system-wide access permits it.

For an incident, preserve the activity row, related domain record, application
log correlation, and dedicated audit evidence together. The activity log
records model changes; it does not prove that an external provider accepted an
operation.

## Diagnosis

### Expected change is absent

- Confirm the model extends an auditable base.
- Confirm the changed field is included in the selected logging level.
- Confirm it is not excluded.
- Confirm the update actually made the model dirty.
- Check whether the activity exists under another campus-aware log name.
- Check whether the write bypassed Eloquent model events.

### Activity is visible in the wrong campus

Inspect the model's `campus_id`, selected-campus session, and actor context at
write time. Fix the producer; do not rewrite historical log names solely to
make the UI filter pass.

### Sensitive data appears

Treat it as an exposure. Stop logging the field at the model boundary and
follow the repository's incident procedure for already-persisted data. Do not
copy the sensitive value into a bug report or new log message.

## Verification

Use the focused test for the model or action being changed. For a manual
read-only check, use the system activity-log page and verify actor, subject,
event, campus log name, and changed properties.
