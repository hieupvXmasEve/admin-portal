---
phase: 5
title: "Soak and drop tables (MOVED → 260815-1320 phase 10)"
status: done
priority: P2
effort: "7-day soak + 0.5d migration"
dependencies: [4]
---

# Phase 5: Soak and drop tables

## Overview

After code-removal PRs (Phases 1-4) are deployed and soaked for 7 days with no cleanup-attributable errors, back up and drop the `notifications` and `email_templates` tables. Table drops are irreversible — kept as a separate final PR from code removal, per the advice report's explicit "split code from table-drop migrations" rule.

[Red-team F1] The `email_logs.template_id → email_templates` FK is severed in Phase 4, not here — by the time this phase runs, `DROP TABLE email_templates` should have no inbound FK. Re-verify before running.

[Red-team F12] A `mysqldump` is a backup, not a rollback. By this phase, the models/scopes/trait/read-paths that could read a restored table were deleted 4+ PRs and 7+ days earlier — restoring rows gives you data nothing can read. Treat the dump as a compliance/audit artifact, not a recovery mechanism; the real "rollback" is reverting the PR range, which must be named.

## Requirements

- Functional: `mysqldump` backup of `notifications` and `email_templates` taken on prod before the drop migrations run, to a specific path OUTSIDE any web-served docroot, encrypted at rest (both tables carry PII — `notifications` joins to `User`/`Student`/`Lecture` identities), access-restricted (`0600`, root-owned), with a defined retention/destruction date.
- Functional: two separate drop migrations (one per table), not one combined migration — so a failure on one doesn't strand the other mid-deploy.
- Functional: re-verify (do not just trust Phase 4's work) that `email_logs.template_id` FK is gone via `SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME = 'email_templates'` → empty, before running the `email_templates` drop.
- Non-functional: 7-day prod soak with zero cleanup-attributable class-not-found/500 errors on parent-portal, student-portal, and notification endpoints, PLUS a browser-console/JS-error check for the FE surfaces from Phase 4 (Ziggy route-not-found errors don't reach server logs — see Phase 4 risk note).
- Non-functional: restore rehearsal into a scratch schema (not prod) with row-count/checksum comparison, done at least once before the real drop — a named PR-revert range and a named decision owner for aborting the drop, documented before this phase starts.

## Related Code Files

- Create: migration `drop_notifications_table.php` and migration `drop_email_templates_table.php` (two files, naming per repo migration convention) — each with a real `down()` that recreates the table schema (not just a comment)
- No other application code changes in this phase — pure schema migration

## Implementation Steps

1. Confirm Phases 1-4 have been in prod for ≥7 days with clean logs (`ssh root@157.10.186.103`, check application + web server logs for class-not-found or 500s tied to deleted classes/tables) AND a manual click-through of admin nav / parent portal / student portal for JS console errors (server logs alone miss Ziggy failures).
2. `mysqldump` the `notifications` and `email_templates` tables on prod to a specific backup path outside the web docroot (this host's docroot is `/www/wwwroot/` — do not write anywhere near it), encrypt the dump, set `0600` root-owned permissions, and record a destruction date.
3. Restore-rehearse the dump into a scratch schema; compare row counts/checksums against the source tables to confirm the backup is actually valid before relying on it.
4. Re-verify `email_logs.template_id` FK is gone (`information_schema.KEY_COLUMN_USAGE` query) — if Phase 4's FK-drop migration didn't run or didn't take, STOP and fix that first.
5. Write and review two separate drop migrations (one per table), each with a real `down()`.
6. Run the `notifications` drop migration on prod first; verify `SHOW TABLES` and monitor for 24h before running the `email_templates` drop migration.
7. Verify `SHOW TABLES` no longer lists `notifications`/`email_templates`.
8. Monitor logs (server + a manual browser-console spot check) for 24-48h post-drop for any missed reference.

## Success Criteria

- [ ] `mysqldump` backup exists at a docroot-safe path, encrypted, `0600`, with a documented destruction date; restore-rehearsed into a scratch schema with row-count/checksum match
- [ ] Named PR-revert range and named decision owner for the abort path, documented before the drop runs
- [ ] `email_logs.template_id` FK confirmed absent before the `email_templates` drop
- [ ] Two separate drop migrations, each with a real `down()`
- [ ] `SHOW TABLES` on prod: `notifications`, `email_templates` absent
- [ ] `email_configurations`, `email_logs` (schema minus `template_id`), `user_email_preferences` still present
- [ ] Zero cleanup-attributable errors (server logs AND manual browser-console check) in the 7-day soak window and 24-48h post-drop

## Risk Assessment

High blast radius. [Red-team F1] Combining both table drops into one migration means a failure on `email_templates` (e.g. an FK Phase 4 missed) can strand the migration mid-run with `notifications` already gone — split into two migrations and drop `notifications` first. [Red-team F12] The mysqldump is not a functional rollback once downstream code is already deleted; the real recovery path is reverting the PR range, which must be pre-named, not improvised during an incident. Do not compress the 7-day soak. If any cleanup-attributable error surfaces during soak, fix the root cause and restart the soak clock before proceeding to the drop.
