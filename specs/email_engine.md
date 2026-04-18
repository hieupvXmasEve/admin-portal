# Email Engine Spec

Last updated: 2026-04-18 (rev 2)
Status: Proposed target design
Scope: New feature only, no legacy backfill

## 1. Goal

Build email delivery on top of `Notification V2`.

Principles:

* `noti_v2` is the only source of truth for notification runtime.
* Legacy standalone email flows are removed from business features.
* Email templates are content-only, not a second notification engine.
* No backfill, no migration of legacy behavior contracts, no dual-run requirement.

## 2. Non-Goals

* No new standalone email pipeline outside `noti_v2`.
* No admin-defined event types.
* No admin-defined database mapping rules.
* No campus-specific template override in phase 1.
* No backfill from old `email_templates` or old email logs into the new model.
* No attempt to make every notification editable by admin.

## 3. Source of Truth

Runtime source of truth remains:

* `notification_event_outbox`
* `notification_messages` — canonical for **realtime / in-app** channel only (see Section 3.1)
* `notification_deliveries`
* `type_key` from Notification V2

### 3.1 Role of `notification_messages.title` / `body`

`notification_messages.title` and `body` are written for the **in-app / realtime** channel.

They are **not** reused as email subject or body.

Email content is rendered separately by `EmailContentProvider` (code-managed) or `notification_templates` (template-managed) and stored in `notification_deliveries` before dispatch.

This separation exists because email requires subject + html + plain-text — a different shape from in-app title/body.

Current architecture references:

* [prd/notification-domain-modular-monolith.md](/Users/hunt2412/hieupvdev/project/swinx/prd/notification-domain-modular-monolith.md:1)
* [docs/system-architecture.md](/Users/hunt2412/hieupvdev/project/swinx/docs/system-architecture.md:61)

Email sending must continue to use campus-aware SMTP resolution via:

* `email_configurations`
* `EmailConfiguration::getActiveForCampus(?int $campusId)`

Relevant current code:

* [app/Models/EmailConfiguration.php](/Users/hunt2412/hieupvdev/project/swinx/app/Models/EmailConfiguration.php:106)
* [app/Jobs/SendSingleEmailJob.php](/Users/hunt2412/hieupvdev/project/swinx/app/Jobs/SendSingleEmailJob.php:78)

## 4. Content Modes

Notification content is split into 2 modes.

### 4.1 Code-managed

Default mode.

Characteristics:

* Subject/body are produced by backend code.
* No admin UI for editing content.
* Suitable for most notification types.

Examples:

* security/system notifications
* unstable business flows
* low-value/internal alerts

### 4.2 Template-managed

Restricted mode.

Characteristics:

* Only selected `type_key` values are editable by admin.
* Admin may edit subject/body/html only.
* Admin may only use variables whitelisted by backend.
* Backend still owns data contract and runtime rendering.

Examples for phase 1 candidates:

* `payment_reminder`
* `invoice_overdue`
* `tuition_due_notice`

## 5. Design Decision

Recommended model:

* Keep `NotificationTypeRegistry` as business contract owner.
* Extend `NotificationTypeRegistry` to include `content_mode` per `type_key` (see Section 5.1).
* Add a template content layer for only selected `type_key` values.
* Do not create a second DB-driven event catalog.

Reason:

* Keeps `noti_v2` authoritative.
* Avoids duplicate truth between code and DB.
* Allows controlled admin customization.
* Matches current project architecture better than the old spec.

### 5.1 NotificationTypeRegistry — content_mode extension

`NotificationTypeRegistry` is extended to carry `content_mode` per `type_key`.

Shape:

```php
[
    'type_key'       => 'payment_reminder',
    'channels'       => ['email', 'realtime'],
    'content_mode'   => 'template-managed',   // or 'code-managed'
    'variable_provider' => PaymentReminderVariableProvider::class, // null if code-managed
]
```

The admin metadata API reads from this registry — it is a thin adapter, not a separate data source:

```
NotificationTypeRegistry (source of truth)
    ↓
GET /api/admin/notification-types   (formats registry entries for UI)
```

No separate metadata table. Registry owns the data; the API formats it.

## 6. Data Model

## 6.1 Recommended table: `notification_templates`

This is a new table for Notification V2 content management.

Suggested columns:

* `id`
* `type_key`
* `channel` (`email` or `realtime`)
* `template_key`
* `name`
* `subject_template`
* `html_template`
* `text_template`
* `variables_schema` json
* `is_active`
* `version`
* `created_by`
* `updated_by`
* timestamps

Rules:

* Phase 1 uses one global template only.
* No `campus_id` override in template table yet.
* Active template uniqueness should be enforced per `type_key + channel`.

## 6.2 Why not reuse the old 3-table spec

Do not implement:

* `Notification_Events`
* `Event_Variables`
* `Notification_Templates`

Reason:

* `NotificationTypeRegistry` already plays the role of event catalog.
* Variable contracts belong to backend providers, not free-form DB metadata.
* Creating a second event metadata system will drift.

## 6.3 Legacy email tables

Legacy `email_templates` is not the source of truth for the new design.

For this feature, treat template-managed notification email as a new capability.

No backfill required.

## 7. Backend Ownership Boundaries

Backend owns:

* `type_key`
* which notification types are `code-managed` vs `template-managed`
* allowed channels
* allowed variables per editable notification type
* database queries and data shaping
* fallback and failure policy

Admin owns:

* wording
* subject text
* html/text email body
* activation/deactivation of supported templates

Admin does not own:

* event definitions
* recipient resolution
* channel policy
* database field mapping
* variable creation

## 8. Variable Contract

Each template-managed `type_key` must have a backend variable provider registered in `NotificationTypeRegistry`.

Provider responsibilities:

* declare allowed variables
* provide sample data for preview
* build runtime data from DB/domain models

Suggested interface:

```php
interface NotificationVariableProvider
{
    public function allowedVariables(): array;
    public function sampleData(): array;
    public function buildData(array $context): array;
}
```

Registration in `NotificationTypeRegistry`:

* Each `type_key` that is `template-managed` must declare a provider class.
* Registry exposes the provider at runtime for validation and rendering.
* Provider lookup: `NotificationTypeRegistry::getVariableProvider(string $typeKey): NotificationVariableProvider`

Example output:

```json
[
  { "key": "student_name", "label": "Student name", "required": true, "example": "Nguyen Van A" },
  { "key": "invoice_number", "label": "Invoice number", "required": true, "example": "INV-2026-001" },
  { "key": "amount_due", "label": "Amount due", "required": true, "example": "12,500,000 VND" },
  { "key": "due_date", "label": "Due date", "required": true, "example": "2026-04-30" }
]
```

## 9. Validation Rules

When admin saves a template:

* Parse all `{{variable}}` placeholders.
* Reject any variable not in provider whitelist.
* Reject invalid syntax.
* Reject empty subject/body for active templates.

When runtime renders a template:

* Provider builds context from DB.
* Engine substitutes only allowed variables.
* Extra unused fields in context are ignored.

## 10. Frontend / Admin Requirements

Admin UI is only for template-managed notification types.

Required behavior:

* Show list of editable `type_key` values from backend metadata API.
* Show variable list returned by backend for selected `type_key`.
* Allow click-to-insert variable into editor.
* Provide preview with backend sample data.
* Save template content only for supported notification types.

Phase 1 simplification:

* Single global template per supported `type_key + channel`
* No campus template branching

## 11. Runtime Flow

For all business features:

1. Feature publishes `DomainEventEnvelope`
2. Notification V2 writes to outbox
3. Outbox pipeline resolves recipient + channel
4. For each delivery:
   * if content mode is `code-managed`, use backend-built title/body
   * if content mode is `template-managed`, resolve template and render from provider data
5. Email channel sends using `email_configurations` of the target campus

High-level flow:

```text
Feature
  -> DomainEventEnvelope
  -> notification_event_outbox
  -> NotificationIntent
  -> notification_messages / notification_deliveries
  -> Email template resolver (only for template-managed type_key)
  -> Render subject/body
  -> SMTP config by campus
  -> Send email
```

## 12. Failure Policy

Template-managed email must not break the main business flow.

Rules:

* If no active template exists for a template-managed type:
  * skip email delivery
  * write audit log / delivery failure reason to `notification_deliveries`
* If template render fails:
  * mark email delivery failed in `notification_deliveries`
  * do not fail the whole notification event pipeline
* Realtime delivery may continue independently when enabled

## 12.1 Retry Strategy

Email delivery failures use Laravel's built-in job retry mechanism.

**Content is rendered before dispatch and stored — retries resend stored content, not re-render.**

Rationale: finance notifications must be idempotent. Re-rendering on retry risks sending a different amount or due date if underlying data changed between attempts.

Flow:

```text
OutboxProcessor
  -> render subject / html / text from provider or template
  -> write rendered_subject, rendered_html, rendered_text to notification_deliveries
  -> dispatch SendNotificationEmailJob (receives delivery_id, not provider reference)

SendNotificationEmailJob
  -> load rendered content from notification_deliveries
  -> send via SMTP
  -> update status = sent / failed
```

Policy:

* On SMTP / transport failure: retry up to **3 times** with exponential backoff (30s, 90s, 270s).
* On template render failure or missing template: **do not retry** — mark delivery `failed` with reason; no job dispatched.
* On permanent failure (all retries exhausted): mark `notification_deliveries.status = failed`, store last SMTP error.
* De-duplication: check `notification_deliveries.status = sent` for the same `message_id` before sending to prevent duplicate sends on retry.

`notification_deliveries` additional columns required:

* `rendered_subject` text
* `rendered_html` longtext
* `rendered_text` text
* `last_error` text nullable

## 13. SMTP Configuration Rule

Email sending must always resolve SMTP config from current notification campus.

Policy:

* use `message.campus_id`
* call `EmailConfiguration::getActiveForCampus($campusId)`
* fall back to global config only if campus-specific config is absent

Template content is global in phase 1.
SMTP config remains campus-aware.

## 14. Migration / Cutover Policy

This spec assumes a clean cutover path for new work.

Rules:

* New notification features must use `noti_v2`
* New business flows must not call legacy direct email APIs
* No backfill from legacy email template system
* No compatibility promise for old standalone email flows in this spec

## 15. Initial Scope Recommendation

Phase 1:

* keep most notification types `code-managed`
* enable `template-managed` only for a very small allowlist
* use one global template per supported `type_key`
* keep SMTP campus-aware

Recommended phase 1 editable types:

* `payment_reminder`
* `invoice_overdue`
* `tuition_due_notice`

Everything else:

* still rendered from backend code

## 16. Success Criteria

* Notification V2 remains the only runtime notification engine
* Admin can edit approved email templates without changing backend code
* Invalid variables are rejected at save time
* Email uses correct campus SMTP config at send time
* Missing template does not break business flow
* No new feature sends mail directly outside `noti_v2`

## 17. Explicit Rejections

Do not do these in this phase:

* DB-driven event definition system
* admin-defined variable mapping to database fields
* campus-specific template override
* backfill from old email template records
* full replacement of all notification content with editable templates
