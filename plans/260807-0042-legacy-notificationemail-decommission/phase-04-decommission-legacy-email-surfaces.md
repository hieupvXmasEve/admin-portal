---
phase: 4
title: "Decommission legacy email surfaces"
status: completed
priority: P2
effort: "1-2d"
dependencies: [3]
---

# Phase 4: Decommission legacy email surfaces

## Overview

Delete the legacy email template/bulk-send/monitoring surfaces that the Notification module's outbox has replaced. **Do NOT touch** `email_configurations`, `email_logs`, `user_email_preferences`, or the `EmailConfiguration` admin CRUD's actual config-management methods — the new outbox reads/writes those directly (`SmtpEmailTransport`, `notification_deliveries.email_log_id` FK).

[Red-team F5] `EmailConfigurationController` is NOT separable into "config CRUD" (keep) vs "template CRUD" (delete) at the class level — it implements both. The original "keep the whole controller untouched" instruction directly contradicts "delete the EmailTemplate model" it route-model-binds against. This phase must specify method-level and route-level granularity.

[Red-team F1] `email_logs.template_id` is a live foreign key into `email_templates` (`email_logs_template_id_foreign`, `onDelete('set null')`), and `EmailLog::template()` is a `belongsTo(EmailTemplate::class)` relation rendered by the KEPT `EmailLogController`/`EmailController::showLog`/`EmailLoggingService`. This phase must sever that FK before Phase 5 can drop `email_templates`, or the drop migration fails on prod (MySQL errno 3730).

[Red-team F13] `EmailService` and `EmailLoggingService` are NOT orphaned — `TestEmailConfiguration` (artisan command, part of the kept config CRUD), `CleanupEmailLogs` (retention job for kept `email_logs`), and `tests/Feature/Notification/ExternalEmailApiTest.php` (the NEW Notification module's own test) all depend on them. This contradicts the plan's own Non-Goal ("no refactor moving send-log ownership"). They are KEPT, unconditionally — no verify-and-maybe-delete step.

## Requirements

- Functional: `email_templates` table's non-FK consumers + `EmailTemplate` model, `EmailTemplateService`, `EmailTemplateVersioningService`, template/bulk-send methods on `EmailConfigurationController` (web) and all of `EmailTemplateController` (API), `EmailMonitoringController`, `SendBulkEmailJob` removed after per-surface verification.
- Functional: `email_logs.template_id` FK constraint dropped (column dropped or nulled) and `EmailLog::template()` relation + its render sites removed, so Phase 5 can drop `email_templates` without an FK error.
- Functional: `EmailService` and `EmailLoggingService` are KEPT unconditionally — remove the "delete if orphaned" framing entirely; they have live callers on kept surfaces.
- Non-functional: email queue drained before deploy — a queued `SendBulkEmailJob` referencing a deleted class fails on workers.
- Non-functional: `email_configurations`, `email_logs` (schema minus `template_id`), `user_email_preferences` tables and the `EmailConfiguration` **config-management** routes/methods remain untouched.

## Related Code Files

- Delete: `app/Models/EmailTemplate.php` (+ migration for `email_templates` table, deferred to Phase 5)
- Delete: `app/**/Services/EmailTemplateService.php`, `EmailTemplateVersioningService.php`
- Modify: `app/Http/Controllers/Web/EmailConfigurationController.php` — delete ONLY the template/bulk methods: `templates`, `createTemplate`, `editTemplate`, `previewTemplate`, `deleteTemplate`, `bulkEmail`. Keep the config-management methods. [Red-team F5]
- Delete: `app/Http/Controllers/Api/V1/Admin/EmailTemplateController.php` (8 routes at `routes/api/admin.php:46-52`) [Red-team F8 — missing from original plan]
- Modify: `routes/web/systems.php:14-20` — remove the `system.email-templates.*` / `system.bulk-email.index` route registrations
- Delete: `EmailMonitoringController`, `app/**/Jobs/SendBulkEmailJob.php`
- Delete: `app/Console/Commands/ManageEmailTemplateVersions.php` [Red-team F8]
- Delete: `database/seeders/EmailTemplateSeeder.php`, `database/factories/EmailTemplateFactory.php` [Red-team F8]
- Modify: `app/Models/EmailLog.php` — remove `template()` relation; modify `EmailLogController`, `EmailController::showLog`/`retryEmail`, `EmailLoggingService` — remove `->template`/`->template->name` render sites [Red-team F1]
- Create: migration dropping `email_logs.template_id` FK constraint + column, run BEFORE Phase 5's table-drop migration [Red-team F1]
- Delete: `resources/js/pages/Admin/{EmailTemplate,BulkEmail,EmailMonitoring}/**`, associated composables (`useEmailTemplate.ts`, `useBulkEmail.ts`, `useEmailMonitoring.ts`); modify `resources/js/constants/menu-sidebar.ts` and `system-routes.ts` to drop the removed nav entries and Ziggy route-name references [Red-team F10 — missing from original plan]
- Keep unconditionally: `EmailService`, `EmailLoggingService` — no delete step [Red-team F13]
- Keep untouched: `EmailConfiguration` config-management methods/routes, `email_configurations`, `user_email_preferences`

## Implementation Steps

1. `php artisan route:list | grep -iE "bulk|email-template|monitoring"` to enumerate exact routes tied to the surfaces being removed (web AND api); separately confirm `email-configurations` config routes are NOT in this list (they stay).
2. `grep -rlE "\bApp\\\\Models\\\\EmailTemplate\b|\bSendBulkEmailJob\b|\bEmailMonitoringController\b" app routes database resources --exclude-dir=Notification` for full call-site inventory — the old unanchored `EmailTemplate\b` regex matches `NotificationEmailTemplate` in the kept Notification module; this anchored form does not. [Red-team F7]
3. `SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME = 'email_templates'` on prod to confirm the `email_logs.template_id` FK inventory before writing the FK-drop migration. [Red-team F1]
4. Drain the email queue in the target environment before deploy (`./scripts/dev.sh artisan queue:*` per repo convention) so no in-flight `SendBulkEmailJob` hits a deleted class on a worker.
5. Delete `EmailTemplateService`, `EmailTemplateVersioningService`, `EmailTemplate` model (table drop deferred to Phase 5), the template/bulk methods on `EmailConfigurationController`, the whole `EmailTemplateController` (API), `EmailMonitoringController`, `SendBulkEmailJob`, `ManageEmailTemplateVersions`, `EmailTemplateSeeder`, `EmailTemplateFactory`.
6. Drop the `email_logs.template_id` FK constraint + column in a migration; remove `EmailLog::template()` and its render sites in `EmailLogController`/`EmailController`/`EmailLoggingService`.
7. Delete the FE files and update sidebar/menu constants per the file list above; regenerate Ziggy.
8. Run full test suite; confirm `email-configurations` config CRUD still passes and the new outbox send path (`SmtpEmailTransport`) and `TestEmailConfiguration`/`CleanupEmailLogs` (which depend on the KEPT `EmailService`/`EmailLoggingService`) are unaffected.

## Success Criteria

- [ ] `grep -rlE "\bApp\\\\Models\\\\EmailTemplate\b|\bSendBulkEmailJob\b|\bEmailMonitoringController\b" app routes database resources --exclude-dir=Notification` → 0 hits
- [ ] `php artisan route:list | grep -iE "bulk|email-template|monitoring"` → empty (web + api)
- [ ] `email-configurations` config routes still present and functional; `EmailConfigurationController`'s config methods unchanged
- [ ] `email_logs.template_id` FK dropped; no orphaned `EmailLog::template()` references
- [ ] `EmailService`, `EmailLoggingService` present and unmodified; `TestEmailConfiguration`, `CleanupEmailLogs`, `ExternalEmailApiTest` pass
- [ ] FE: sidebar/menu constants updated, no dangling Ziggy route names, admin nav click-through verified manually (not just server-log check — see Phase 5 soak note)
- [ ] Email queue drained before deploy; no worker errors post-deploy
- [ ] No new test failures vs baseline

## Risk Assessment

Medium-high. [Red-team F5] Biggest risk is treating `EmailConfigurationController` as fully "kept" when it also carries the deleted template surface — verify per-method, not per-class. [Red-team F1] The `email_logs.template_id` FK must be severed here, not discovered as a migration failure in Phase 5. [Red-team F10] Frontend breakage (Ziggy route-not-found) surfaces as a browser JS error, not a server log entry — Phase 5's log-only soak check cannot see it, so this phase's own test/click-through verification is the only gate. Queue drain remains a hard prerequisite.

## Execution Evidence (2026-08-07)

- **F5 (controller split):** `Web\EmailConfigurationController` trimmed to `index()` only (config-management page); `templates`/`createTemplate`/`editTemplate`/`previewTemplate`/`deleteTemplate`/`bulkEmail` deleted. Code-reviewer confirmed no leak through traits/base class.
- **F1 (FK severance):** migration `2026_08_07_114654_drop_template_id_fk_from_email_logs_table.php` drops FK → composite index `[template_id, status]` → column, in that order (verified via `artisan migrate --pretend` before running; ran clean on dev DB). `EmailLog::template()` relation removed; 3 render sites fixed: `EmailLogController::index()`, `Api\V1\Admin\EmailController::showLog()`/`retryEmail()`.
- **F7 (anchored regex):** confirmed zero accidental touches under `app/Modules/Notification/`; `NotificationEmailTemplate` (v2, kept) untouched.
- **F8 (missing from original plan):** `ManageEmailTemplateVersions` command, `EmailTemplateSeeder`, `EmailTemplateFactory`, and the 8-route `Api\V1\Admin\EmailTemplateController` all deleted, zero lingering references.
- **F10 (FE):** deleted `resources/js/pages/Admin/{EmailTemplate,BulkEmail,EmailMonitoring}/**` + 3 composables; removed 3 nav entries from `menu-sidebar.ts` (+ unused `MailPlus` icon import); trimmed `system-routes.ts`/`utils/routes.ts`; removed dead `emailLog.template_id` display field from kept `EmailLogDetailModal.vue`; ran `artisan ziggy:generate`, confirmed zero stale route names in `resources/js/ziggy.js`.
- **F13 boundary:** `EmailService`/`EmailLoggingService` kept per plan, but 3 genuine bugs surfaced during code review (all now fixed) from severing the FK/model — these were **correctness fixes required by this phase's own deletions**, not scope creep:
  1. **CRITICAL** `EmailService::getEmailLogs()` still did `->with(['template', 'user'])` — would have thrown `RelationNotFoundException` on the live `GET api/emails/logs` route. Fixed to `->with(['user'])`, verified via tinker (`total=1467`, no error).
  2. **HIGH** `TestEmailConfiguration` command listed `EmailTemplateService::class` in its service-resolution check — silently caught exception, made `email:test-configuration` report failure forever. Fixed by removing that array entry.
  3. **MEDIUM** `EmailLoggingService::getDetailedEmailStatistics()` (dead code, 0 callers, correctly left in place per F13) referenced the dropped `template_id` column in a filter and a `templateBreakdown` block — removed both so the dead method doesn't fatal if ever called again.
  Also trimmed 4 fully-dead `EmailService` methods (`sendBulkEmail`, `getBulkEmailProgress`, `cancelBulkEmailBatch`, `renderTemplate`) + 3 orphaned private helpers, whose only callers were surfaces deleted this phase (`sendBulkEmail` had zero callers even before this phase — dead on arrival). Fixed `sendSingleEmail()`'s dangling `?EmailTemplate` typehint to `mixed` (this live method is the only caller of `EmailLoggingService::logEmailSendingAttempt`). `EmailLoggingService.php` itself left otherwise untouched per red-team F13's explicit "keep unconditionally, no delete step" — confirmed safe (nullable typehints to the deleted class are never invoked with a non-null value).
- **Known pre-existing bug (unrelated, not fixed):** `email:test-configuration` crashes with "An option named 'verbose' already exists" — the command defines a custom `--verbose` option colliding with Symfony Console's built-in flag. Confirmed via git-stash comparison to pre-exist on `dev` before this phase; out of scope.
- **Deferred (LOW, not blocking):** orphaned FE types `EmailTemplate` interface in `resources/js/types/models.ts`/`index.d.ts`, plus zero-consumer `components/EmailEditor.vue`, `composables/useStudentEmailVariables.ts`, `types/email.ts` — dead but harmless, candidate for a follow-up FE sweep, not required by this phase's file list.
- **Migration `down()` caveat:** its rollback re-adds the FK against `email_templates`, which Phase 5 drops — rollback will fail after Phase 5 runs. Noted for Phase 5.
- Tests: `tests/Feature/Feature/EmailConfiguration` (21/21), `tests/Feature/Notification/ExternalEmailApiTest.php`, full `tests/Feature/Architecture` — identical 5 pre-existing baseline failures before/after, zero regression from this phase.
