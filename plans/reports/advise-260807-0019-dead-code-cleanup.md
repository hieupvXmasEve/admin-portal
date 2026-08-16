# Advise — Legacy notification/email decommission (was: "zero-risk dead code deletion")

Date: 2026-08-07 | Interview: 5 Qs | State: `advise-260807-0019-dead-code-cleanup-state.md`

## Reframing (confirmed via interview)

**Problem (reframed).** Original ask was "delete dead code — zero risk, not refactor": drop `notifications` + `email_*` tables, 4 Finance events, 0-caller services. Scout proved 3 of 4 claims wrong: email tables power the NEW notification outbox; legacy `notifications` has live prod read/write paths (parent portal, welcome command, manual notify); Finance events (3, not 4) fire in live money code. Real task = **deliberate decommission of legacy notification/email surfaces**, part deletion, part feature removal.

**Exact requirements.**
1. Delete 6 services referenced only by `config/migration_debt_paths.php`: BillingCycleService, HoldsManagementService, ScheduledNotificationService, StudentCodeGenerationService, UserExcelExportService, UserExcelImportService — plus debt-inventory update so `MigrationDebtInventoryTest` stays green.
2. Delete dispatcher-less legacy event chains: `AcademicHoldPlaced`, `EnrollmentConfirmed`, `GradePublished` (zero dispatchers) + their listeners + `EventServiceProvider` entries; `CourseRegistrationOpened` (only dispatcher is dead service #1); `AssessmentDeadlineApproaching` + `ScheduleAssessmentReminders` command **after verifying prod crontab does not run it**.
3. Decommission legacy `notifications`: delete `SendWelcomeStudents` command (write path), delete parent-portal unread-count read (`GetParentContextQuery:28`), delete `app/Actions/Notification/*`, `HasNotifications` trait usage (User/Student/Lecture), `NotificationBroadcast`, `Notification` model, then drop table. User chose DELETE over port — features deemed unwanted, not just storage.
4. Reduced email scope (user accepted): **KEEP** `email_configurations`, `email_logs`, `user_email_preferences` + `EmailConfiguration` admin CRUD (the new outbox's `SmtpEmailTransport` reads/writes them; `notification_deliveries.email_log_id` FK). **DELETE** (each verified first): `email_templates` table + `EmailTemplate` model, `EmailTemplateService`, `EmailTemplateVersioningService`, `EmailController` bulk-send, `EmailMonitoringController`, `SendBulkEmailJob`, and `EmailService`/`EmailLoggingService` if their remaining callers all die with the above.

**Goals.** Legacy surfaces gone from routes/models/services; test suite at-or-better than baseline; zero prod errors attributable to the cleanup 7 days post-deploy.

**Non-goals.** Finance events (ChargeFullySettled, InstallmentPushed, InstallmentPushFailed) — **explicitly deferred by user, unresolved, keep as-is**. No porting to NotificationMessage. No Notification-module refactor to own its config/log tables.

**Constraints.** Prod system, real finance data. Debt-freeze mechanism (`MigrationDebtGuard` + arch test) must be updated with deletions. Finance test baseline: 26 pre-existing failures — don't chase them. One PR per surface. Table drops are irreversible.

## Verdict

The idea is right; the framing was dangerous. "Grep read-path → zero risk" would have dropped `email_configurations` and `email_logs` — the tables the NEW outbox sends through — and silently killed the parent portal's unread count. As a **staged decommission** with per-surface verification it is worth doing and mostly cheap (~3-5 PRs, few days). Two items are product changes wearing a dead-code costume: parent-portal unread count and welcome notifications. Get product sign-off on those two before merging PR3, or you'll relearn the FIN-REV lesson that "nobody uses it" is a claim, not a fact.

## What you should do

1. Ship the truly-safe deletions first (services, dead event chains) — they need no sign-off.
2. Verify before each delete with more than grep: `php artisan route:list`, prod server crontab (commands can be cron'd outside `routes/console.php`), FE (`resources/js`, student-nuxt) endpoint usage, queue payloads (drain `SendBulkEmailJob` before deploy — serialized jobs referencing deleted classes fail on workers), morph strings (`notifiable_type='student'` is data, grep misses it).
3. Split code-removal PRs from table-drop migrations. Code first; drop tables only after ≥1 clean week in prod. `mysqldump` the tables to backup before the drop migration runs.
4. Update `config/migration_debt_paths.php` in the same PR as each service deletion; run `tests/Feature/Architecture/MigrationDebtInventoryTest.php`.
5. Get explicit product sign-off on: (a) parents lose the unread-notification count, (b) new students stop receiving welcome notifications. One sentence each, in the PR description.

## What you shouldn't do

- Don't drop `email_configurations`, `email_logs`, `user_email_preferences` — new outbox breaks (verified: `SmtpEmailTransport.php`, `NotificationDelivery.php` FK).
- Don't touch the 3 Finance events this round (user-deferred; they're dispatched in money paths with tests).
- Don't bundle table-drop migrations with code-removal PRs — kills your rollback story.
- Don't trust grep alone for "0 callers" — this codebase resolves via container, morphs, crontab, and queued class strings.
- Don't delete `EmailService` (34K) until EmailController/EmailMonitoringController/SendBulkEmailJob/TestEmailConfiguration are all confirmed gone — it dies last, as a consequence.

## What could be better / more efficient

1. **Cheapest 80%**: PR1+PR2 (services + dead events) delete real weight with zero product risk. If time is short, stop there.
2. Rename-instead-of-drop (`zz_legacy_notifications`) buys an instant undo for near-zero cost if you're nervous; straight drop after backup is fine given welcome-message data is low-value.
3. A follow-up (not now): move SMTP config + send-log ownership into the Notification module so `app/Models/Email*` legacy naming disappears — that's the refactor you correctly excluded.

## My take and how to get there

Do it in the PR order below. The user's delete-not-port call on parent-portal count and welcome messages is theirs to make — I'd have ported the unread count (it's ~5 lines onto NotificationMessage) since parents currently see it; recording as noted trade-off, not blocking.

## Benefits

- ~10+ dead/legacy classes, 5 controllers/jobs, 2 tables, several routes gone → less noise for devs and LLM navigation.
- Debt inventory (`migration_debt_paths.php`) shrinks honestly instead of freezing corpses.
- One notification system instead of two half-systems; future work targets NotificationMessage only.

## Trade-offs

- Parent portal loses unread count (product change, user accepted) — parents may notice.
- Welcome notifications cease with no replacement (user accepted); re-adding later = new NotificationMessage type, small.
- Old notification/welcome history destroyed on table drop (mitigated by dump backup).
- Finance events stay unresolved — dispatch code fires into the void until the deferred decision lands.
- Verification work (crontab, FE, queues) is the real cost; skipping it re-creates the "zero risk" fallacy.

## Work checklist

- [ ] PR1: delete 6 frozen services; update `config/migration_debt_paths.php`; `MigrationDebtInventoryTest` green.
- [ ] PR2: verify prod crontab has no `ScheduleAssessmentReminders`/related entries; delete 5 legacy events + 5 listeners + `EventServiceProvider` entries + `ScheduleAssessmentReminders`.
- [ ] Product sign-off (written, 2 lines): drop parent unread count + welcome notifications.
- [ ] PR3: delete `SendWelcomeStudents`, `app/Actions/Notification/*` (+ their controllers/routes), parent-portal unread read in `GetParentContextQuery`, `HasNotifications` trait from User/Student/Lecture, `NotificationBroadcast`, `Notification` model; check `resources/js` NotificationPopper + student-nuxt hit only the NotificationMessage API.
- [ ] PR4: verify then delete `EmailTemplate` model + `EmailTemplateService` + `EmailTemplateVersioningService` + `EmailController` bulk-send + `EmailMonitoringController` + `SendBulkEmailJob` (+ `EmailService`/`EmailLoggingService` if orphaned); drain email queue before deploy; keep EmailConfiguration CRUD routes.
- [ ] 7-day soak: watch prod logs (`ssh root@157.10.186.103`) for class-not-found / 500s on parent portal + notification endpoints.
- [ ] PR5: `mysqldump` `notifications` + `email_templates` on prod → migration dropping both tables (drop `notification_deliveries` FK-orphan check first is N/A — FK is to email_logs, which stays).

## Success metrics

- `grep -r "App\\Models\\Notification\b" app routes` → 0 hits (excluding Modules/Notification).
- `grep -rl "EmailTemplate\b\|SendBulkEmailJob\|EmailMonitoringController" app routes` → 0 hits.
- `php artisan route:list | grep -iE "bulk|email-template|monitoring"` → empty; `email-configurations` routes still present.
- `config/migration_debt_paths.php` frozen_services no longer lists the 6 deleted services; arch tests pass.
- Full test suite: failures ≤ pre-cleanup baseline (Finance baseline 26).
- Prod: 0 cleanup-attributable errors over 7 days; parent portal + notification popper functional.
- `SHOW TABLES` on prod: `notifications`, `email_templates` absent; `email_configurations`, `email_logs`, `user_email_preferences` present.

## Unresolved questions

1. Finance events (keep vs delete + is "Batch D" still planned) — deferred by user.
2. Do parent-portal FE clients render the unread count today? If yes, FE change needed in PR3 (contract break otherwise).
3. Is `email_logs` retention needed for compliance/audit (it's the send audit trail)? Assumed yes → kept.
