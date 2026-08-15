---
phase: 10
title: "Notification/email: soak and drop tables"
status: pending
priority: P2
effort: "7-day soak + 0.5d migration"
dependencies: []
---

# Phase 10: Notification/email — soak and drop tables

> Merged verbatim from `plans/260807-0042-legacy-notificationemail-decommission/` phase 5 (its phases 1-4 completed & deployed; soak clock runs from that prod deploy). Independent of shim/auth phases — start the soak verification any time.

## Overview

After the decommission code-removal PRs (source-plan phases 1-4) have soaked in prod ≥7 days with no cleanup-attributable errors, back up and drop `notifications` and `email_templates`. Table drops are irreversible — separate final PR from code removal.

[Red-team F1 of source plan] `email_logs.template_id → email_templates` FK was severed in source phase 4, not here — re-verify before running. [Red-team F12] `mysqldump` is a compliance/audit artifact, NOT a rollback: by now nothing can read restored rows; the real abort path is the named PR-revert range.

## Requirements

- `mysqldump` backup of both tables on prod BEFORE drops: path OUTSIDE web docroot (host docroot `/www/wwwroot/` — nowhere near it), encrypted at rest (both tables carry PII), `0600` root-owned, documented retention/destruction date.
- Two SEPARATE drop migrations (one per table) so one failure doesn't strand the other mid-deploy.
- Re-verify `email_logs.template_id` FK gone: `SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME = 'email_templates'` → empty.
- 7-day prod soak, zero cleanup-attributable class-not-found/500 on parent/student portals + notification endpoints, PLUS browser-console JS check (Ziggy route-not-found never reaches server logs).
- Restore rehearsal into scratch schema with row-count/checksum comparison; named PR-revert range + named abort-decision owner documented before the drop.

## Related Code Files

- Create: migration `drop_notifications_table.php` + migration `drop_email_templates_table.php` — each with a real `down()` recreating the schema
- No other application code changes — pure schema migration

## Implementation Steps

1. Confirm decommission PRs in prod ≥7 days with clean logs (`ssh root@157.10.186.103`; app + web server logs) AND manual click-through admin nav / parent portal / student portal for JS console errors.
2. `mysqldump` both tables to docroot-safe path; encrypt; `0600` root; record destruction date.
3. Restore-rehearse into scratch schema; compare row counts/checksums.
4. Re-verify `email_logs.template_id` FK absent — if the FK-drop migration didn't take, STOP and fix first.
5. Write + review the two separate drop migrations (real `down()` each).
6. Run `notifications` drop on prod first; verify `SHOW TABLES`; monitor 24h; then run `email_templates` drop.
7. Verify `SHOW TABLES` lists neither table; `email_configurations`, `email_logs` (minus `template_id`), `user_email_preferences` still present.
8. Monitor logs + browser-console spot check 24-48h post-drop.

## Success Criteria

- [ ] Backup exists (docroot-safe, encrypted, 0600, destruction date) + restore-rehearsed with checksum match
- [ ] Named PR-revert range + abort-decision owner documented before drop
- [ ] FK confirmed absent before `email_templates` drop
- [ ] Two separate migrations, each with real `down()`
- [ ] `notifications`, `email_templates` absent on prod; sibling email tables intact
- [ ] Zero cleanup-attributable errors across soak + 24-48h post-drop

## Risk Assessment

High blast radius, irreversible. Drop `notifications` first, `email_templates` second (separate migrations). Do NOT compress the 7-day soak; any cleanup-attributable error during soak → fix root cause, restart soak clock.
