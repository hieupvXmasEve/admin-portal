# advise-state
phase: advise (complete)
input: "Xoá dead code - zero risk, không phải refactor: (1) bảng/model stale notifications (thay bởi notification_messages) + email_* legacy — grep read-path, drop model + migration drop bảng, 1 PR mỗi cái; (2) 4 Finance events không ai consume; (3) trong 49 app/Services, service 0 caller → xoá"
flags: none (report only)

## scout-findings
- **notifications legacy NOT dead**: still WRITTEN by `app/Console/Commands/SendWelcomeStudents.php` (Notification::create); read via `app/Traits/HasNotifications.php` relation on User/Student/Lecture models (overrides Notifiable); `app/Events/NotificationBroadcast.php` used by the model. New path = `app/Modules/Notification` (NotificationMessage) with its own API controllers. Legacy `routes/api/v1/student.php` notifications routes → need check which controller.
- **email_* NOT dead**: LIVE admin API routes in `routes/api/admin.php` (EmailConfigurationController, EmailController, EmailTemplateController); EmailService (34K) used by EmailMonitoringController, SendBulkEmailJob, TestEmailConfiguration. Dropping = feature removal, not dead-code deletion.
- **Finance events: only 3 exist, not 4** (ChargeFullySettled, InstallmentPushed, InstallmentPushFailed). All actively DISPATCHED from live money-path code (SettleInstallmentFromDngAction, PushNextInstallmentAction/Job) + covered by tests/Feature/Finance/InstallmentEventsTest.php. No listeners registered — fired-but-unconsumed extension points. Deleting = editing live money code + deleting tests, not file deletion. One Finance listener exists: ProvisionBillingAccountForRegisteredStudent.
- **app/Services: ZERO services with literal 0 callers.** Six with only `config/migration_debt_paths.php` reference (effectively dead code-wise): BillingCycleService, HoldsManagementService, ScheduledNotificationService, StudentCodeGenerationService, UserExcelExportService, UserExcelImportService. BUT repo has a debt mechanism: `config/migration_debt_paths.php` 'frozen_services' list + `app/Support/MigrationDebt/MigrationDebtGuard.php` + arch test `tests/Feature/Architecture/MigrationDebtInventoryTest.php` — deletions must update the inventory.
- **Old app/Events chain half-dead**: AcademicHoldPlaced, EnrollmentConfirmed, GradePublished have NO dispatchers (dead chains, listeners registered in EventServiceProvider). AssessmentDeadlineApproaching dispatched by ScheduledNotificationService (itself dead) + ScheduleAssessmentReminders command (not found in schedule). CourseRegistrationOpened only dispatched by dead ScheduledNotificationService.
- FE has NotificationPopper.vue / NotificationItem.vue — which API they hit not yet verified. Prod data volume in notifications/email_* tables unknown (dev DB check only would not prove prod).

## qa-log
- Q1: What outcome drives cleanup (noise vs decommission vs finance-prep)? -> A1: Full decommission — willing to take feature-removal scope (email_* admin, legacy notifications) with product sign-off, not just the 6 dead services.
- Q2: Old email admin still used by staff, or replaced by Notification module? -> A2: Confident Notification module (notification_email_templates, outbox) fully replaced it; old email_* stack abandoned.
- POST-A2 SCOUT (critical): Notification module itself DEPENDS on legacy email tables. `app/Modules/Notification/Support/SmtpEmailTransport.php` reads EmailConfiguration::getActiveForCampus() for SMTP creds AND writes EmailLog rows; `NotificationDelivery` has email_log_id FK belongsTo(EmailLog); `GetEventNotificationPreferencesQuery` reads UserEmailPreference. So email_configurations, email_logs, user_email_preferences CANNOT be dropped — the NEW system runs on them. Only old email_templates (replaced by notification_email_templates) + old admin controllers/EmailService/bulk-send are candidates. EmailConfiguration admin CRUD is likely how staff configure SMTP for the new outbox → the admin routes for it must stay too.
- Q3: Accept reduced email scope (keep 3 live tables + EmailConfiguration CRUD)? -> A3: Accepted. Delete only: email_templates table, EmailController bulk-send, EmailMonitoringController, EmailTemplateService/Versioning, SendBulkEmailJob — each verified before removal.
- POST-A3 SCOUT: legacy `notifications` table has MORE live paths than assumed: `app/Modules/Identity/Queries/GetParentContextQuery.php:28` (parent portal unread count — live prod read), `app/Actions/Notification/{Get,MarkAllAsRead,SendManual}NotificationsAction` (reads + writes), `SendWelcomeStudents` command (writes welcome notification per student). Decommission requires migrating parent portal + manual-notify + welcome onto NotificationMessage first.
- Q4: Port-then-drop vs defer vs rename? -> A4: Neither port nor defer — DELETE outright: remove SendWelcomeStudents command AND the parent-portal unread-count read code. Features themselves considered unwanted, not just storage. NOTE for advice: parent-portal unread-count removal is a product/UX change, not dead-code removal — flag plainly; also verify whether parent portal FE displays that count (contract break risk) and whether welcome notifications must be re-provided via new module later.

- Q5: Finance events keep/delete? -> A5: Deferred by user ("làm sau") — out of scope this round, keep as-is, decision unresolved.

## reframing-draft
problem: cleanup of suspected-dead legacy surfaces (notifications, email_*, Finance events, zero-caller services) — but scout shows 3 of 4 claims are NOT zero-risk deletions
requirements: TBD
goals: TBD
non-goals: TBD
constraints: repo has migration-debt freeze mechanism; prod system with real finance data; ponytail/YAGNI culture

## next
Q1: what outcome drives this — noise reduction vs actual feature decommission? Grounded in finding that email_* has live routes and Finance events are live money-path dispatches.
