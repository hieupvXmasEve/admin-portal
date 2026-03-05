# PRD: Notification Domain (MVP Internal - Simple Mode)

## 1) Problem Statement

Current behavior:

- Email sending is handled manually per feature.
- Push notifications are handled via a separate socket flow.

Issues:

- High coupling and poor maintainability.
- Each feature re-implements notification logic.
- Campus-based email configuration is not standardized.

Goal: centralize notification logic in one place, keep implementation simple for an internal project, and avoid over-engineering.

## 2) Product Principles (KISS / YAGNI / DRY)

- No microservices.
- No complex rule engine.
- No outbox/event bus in this phase.
- Use hardcoded rule mapping plus one central service.
- All features must use a single entrypoint for notifications.

## 3) Goals

- Decouple Notification from business features.
- Support two channels: email + push socket.
- Support campus-based email config with multiple configs per campus, but only one active config per campus at a time.
- Let end users enable/disable notifications by event and channel.
- Use plain text email body first to reduce template complexity.

## 4) Non-Goals

- No SMS/WhatsApp.
- No dynamic rule-builder UI.
- No free-form DSL condition editor for admins.
- No old data migration.

## 5) Scope

### In Scope

- Central `NotificationService` with internal API: `notify(eventKey, context)`.
- Hardcoded rules file: `config/notification-rules.php`.
- Event-to-channel mapping.
- Plain text email subject/body with dynamic interpolation in code.
- Simple condition checks in code/config (example: `absence_percent >= 20`).
- Active campus email configuration.
- User preference on/off.
- Basic delivery logs (success/fail + reason).

### Out of Scope

- Complex non-technical rule editor.
- Multi-step workflow approvals.
- Advanced dead-letter dashboard.

## 6) Proposed Architecture (MVP)

### 6.1 Architecture Decision

Use **Modular Monolith + Central Notification Service + Hardcoded Rules**.

Why:

- Fast to implement, low risk.
- Easy for internal team to operate.
- No over-engineering.

### 6.2 High-level Flow

1. Feature calls `NotificationService::notify(eventKey, context)`.
2. Service reads rule from `config/notification-rules.php`.
3. Service evaluates simple event condition.
4. Service resolves campus + channel + text builder.
5. Service checks user preference (on/off).
6. Service sends via email adapter or socket push adapter.
7. Service writes delivery logs.

## 7) Data Model (MVP)

Prefer existing tables, add only minimal fields/tables.

### 7.1 Campus Email Config

- Reuse `email_configurations`.
- Ensure fields exist: `campus_id`, `is_active`.
- Required constraint: only one active config per campus.

### 7.2 Email Content (MVP Plain Text)

- Do not depend on `email_templates` in phase 1.
- Build text directly from `event_key` + `context` (example: student name, course, absence rate).
- Use a small helper to keep formatting consistent and avoid repeated text blocks.

### 7.3 User Preference

- Reuse `user_email_preferences` if suitable, or create minimal `notification_preferences`:
    - `user_id`
    - `event_key`
    - `channel` (`email`/`push`)
    - `is_enabled`

### 7.4 Delivery Log

- Reuse `email_logs` for email channel.
- Push can log to a lightweight `notification_logs` table later if needed, or use application logging first.

## 8) Hardcoded Rule Config (Core of MVP)

File: `config/notification-rules.php`

Each rule includes:

- `event_key`
- `channels`
- `subject_text` (or `subject_key`)
- `body_text` (or `body_key`)
- `condition` (callable or simple params)
- `respect_user_preference` (true/false)
- `enabled`

Internal example:

- `attendance.low` sends when `absence_percent >= threshold_percent`.
- `threshold_percent` can be loaded from simple campus config.

Query flow examples (student/user ask and reply):

- `query.created.by_student`:

    - Purpose: notify responsible user/staff when a student creates a new query.
    - `subject_text`: `[New Query] {student_name} - {query_title}`
    - `body_text`:
      `Student {student_name} created a new query.`
      `Title: {query_title}`
      `Content: {query_preview}`
      `Please respond at: {query_url}`

- `query.created.by_user`:

    - Purpose: notify student when user/staff creates a new query for the student.
    - `subject_text`: `[New Notification] {query_title}`
    - `body_text`:
      `You have a new query from {sender_name}.`
      `Title: {query_title}`
      `Content: {query_preview}`
      `View details: {query_url}`

- `query.replied.to_student`:

    - Purpose: notify student when user/staff replies.
    - `subject_text`: `[Query Reply] {query_title}`
    - `body_text`:
      `{replier_name} replied to your query.`
      `Reply: {reply_preview}`
      `View full thread: {query_url}`

- `query.replied.to_user`:
    - Purpose: notify user/staff when student replies.
    - `subject_text`: `[Student Reply] {student_name} - {query_title}`
    - `body_text`:
      `Student {student_name} replied to the query.`
      `Reply: {reply_preview}`
      `View full thread: {query_url}`

Recommended dynamic variables for query events:

- `student_name`, `sender_name`, `replier_name`
- `query_title`, `query_preview`, `reply_preview`
- `query_url`, `campus_name`

## 9) Permissions (Simple RBAC)

### Admin / Staff

- `notification.manage_configs`: manage campus SMTP config.
- `notification.manage_event_mapping`: manage event-to-channel mapping.

### End-user

- `notification.self_manage_preferences`: enable/disable personal notification preferences.

Note: in MVP, admins cannot create custom rule formulas; they can only enable/disable hardcoded rules.

## 10) Functional Requirements

- FR-01: Features must call `notify(eventKey, context)`; no direct mail/socket calls.
- FR-02: `attendance.low` supports percentage-based condition (hardcoded).
- FR-03: Email uses active SMTP config for the target campus.
- FR-04: Users can enable/disable notifications by event/channel.
- FR-05: Admin can enable/disable events by campus/channel.
- FR-06: Failed sends are logged for manual retry.
- FR-07: Support baseline query events: student creates, user creates, user replies, student replies.

## 11) Non-Functional Requirements

- Implementable in 1-2 sprints.
- Easy to read and debug.
- Incremental rollout without breaking other modules.

## 12) Rollout Plan

### Phase 1

- Build `NotificationService` + email/push adapters.
- Add `config/notification-rules.php`.
- Wire first rule: `attendance.low`.

### Phase 2

- Add campus event-channel mapping.
- Add user preference on/off UI.

### Phase 2.1 (Optional)

- Only if non-technical content editing is required, re-introduce `email_templates`.

### Phase 3

- Move existing manual notification flows to `notify()` gradually.
- Block new feature code from calling mail directly.

## 13) Risks and Mitigation

- Hardcoded rules are harder for non-technical staff to change -> accepted in MVP; changes via deployment.
- Limited long-term flexibility -> keep clean entrypoint so future upgrade is easy.
- Wrong campus config -> enforce validation + test email before activation.

## 14) Success Metrics

- > = 80% of new notification flows go through `NotificationService` after phase 1.
- 0 new features send mail directly.
- `attendance.low` works correctly with campus threshold and dynamic text.
- User preference on/off works as expected.
