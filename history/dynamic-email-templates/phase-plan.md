# Phase Plan - Dynamic Email Templates

**Mode:** `standard_feature`
**Source:** [CONTEXT.md](CONTEXT.md) (D1-D9), [approach.md](approach.md)
**Architecture / reality basis:** [discovery.md](discovery.md)

## Feature Summary

Replace the four hard-coded HTML email bodies under
`app/Modules/Notification/EmailContent/Types/` with database-backed templates that Super
Admins can edit per campus through a WYSIWYG screen. Backend owns the variable
allow-list per `type_key`; saves are rejected if a template uses unknown `{{var}}`. The
admin screen reuses the existing TipTap-based `EditorContent.vue`, ships a
server-rendered Preview pane and a Send-test button, and is gated to Super Admin only.

## Phase Overview

| Phase | What Changes | Why Now | Demo | Unlocks |
|---|---|---|---|---|
| **P1 - DB-backed render parity** | Create dedicated `notification_email_templates` table (`campus_id`, `type_key`, `subject`, `body_html`, `updated_by_user_id`, timestamps; UNIQUE `(campus_id, type_key)`); add `NotificationEmailTemplate` model + `HasTemplateRendering` trait + `NotificationTemplateTypeKey` enum; seed `existing_campuses x 4 type_keys` rows from current hard-coded HTML; introduce `DbEmailContentProvider`; rebind `EmailContentRegistry` (behind `config('notifications.use_db_templates')` flag with legacy classes as fallback); inject `campus_id` into the 4 finance Actions + `HandleOutboxEventAction::buildEmailData()`; add per-type `availableVariables()` via new `EmailVariableSchema` contract. | Land the storage swap behind no UI - if rendering diverges we catch it before users see anything. Try/catch fallback in `HandleOutboxEventAction::buildRenderedEmail()` plus the new feature flag give two layers of safety net. | Trigger each of the 4 reminder flows in staging. Each email body (subject + html) matches the legacy provider output byte-for-byte (whitespace-normalised) for a fixed fixture across all 4 type_keys; production reminders go out unchanged. | Authoring surface in P2 has a stable, tested storage layer to write into. |
| **P2 - Super Admin editor + preview + test send** | New Inertia screen (`/admin/notification-templates`) listing the 4 type_keys per campus, edit-only; `EditorContent.vue` body editor with variable picker driven by the BE allow-list; subject input with same picker; server-rendered Preview pane; "Send test" to the editing admin's email; `mews/purifier` sanitization on save; super-admin Policy + Gate. | Once parity is verified in P1, opening up edits is the visible value the user asked for. | Super Admin logs in, edits `payment_reminder` for one campus, inserts a new variable from the picker, clicks Preview (sample-data render), clicks "Send test" - email arrives in their inbox. Then the next batch reminder run for that campus uses the edited HTML; a non-super-admin user gets 403 on the same routes. | Closes the feature. Optional follow-up: delete legacy `EmailContent\Types\*` PHP HTML bodies (deferred to a separate cleanup task). |

## Order Check

- **P1 is the obvious first step:** swap storage without changing user-visible behaviour;
  every subsequent change layers on top of a verified provider.
- **P2 strictly builds on P1:** the admin editor writes to the same rows the production
  provider reads. Without P1 the editor would have no storage; without P2 the storage
  has no editor.
- **Not a technical-layer split:** each phase delivers an end-to-end observable change
  (parity demo, then authoring demo) - not "backend phase / frontend phase".

## Approval Summary

- **Current phase to prepare next:** P1 (DB-backed render parity). It is the smallest
  proof that the integration seam (`EmailContentRegistry` -> `DbEmailContentProvider`)
  works without altering the user-facing payload. Estimated touch: ~12-14 files plus 2
  migrations (CREATE TABLE + data backfill).
- **Picture after P1:** all four finance reminder paths render from
  `notification_email_templates` rows. The legacy `EmailContent\Types\*EmailContent.php`
  classes remain in the repo as the variable-schema source (via `availableVariables()`)
  and as the feature-flag fallback. No admin UI yet. Production output unchanged.
- **Deferred work (per CONTEXT.md):** every item in `## Deferred Ideas` plus the
  Phase 2 admin surface plus the post-P2 cleanup of legacy `EmailContent\Types\*`
  classes.

## Open Validating Questions (Block Bead Creation)

These come from [approach.md](approach.md) `## Validating Questions` and must clear
through `khuym:validating` before P1 execution beads are created:

1. Render parity test gives byte-equivalent output for all 4 type_keys against a fixed
   fixture.
2. Each of the 4 finance Actions can inject `campus_id` into `$data` without changing
   recipient resolution or transaction boundaries.
3. `mews/purifier` round-trips all four legacy bodies without losing inline `style=`
   attributes.
4. FE `EditorContent.vue` `commonVariables` prop can be fed by a single GET-per-type
   endpoint without refactor (P2-scope but worth confirming early so the BE contract
   is right in P1).

## Stop

Planning has chosen the smallest work shape. Approve it before current phase/work prep.
Tough work uses an epic map; beads wait until feasibility passes.
