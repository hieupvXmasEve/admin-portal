---
title: "Legacy Notification/Email Decommission"
description: "Staged removal of legacy notification/email surfaces + dead-code cleanup, replacing the 'zero-risk grep deletion' framing that scout disproved"
status: in_progress
priority: P1
effort: "3-5 PRs, few days + 7-day soak"
tags: [cleanup, notifications, email, finance-events-deferred]
created: 2026-08-07
---

# Legacy Notification/Email Decommission

## Overview

Source: `plans/reports/advise-260807-0019-dead-code-cleanup.md` (ak:advise interview, 5 questions).

Original ask: "delete dead code, zero risk, not refactor" — drop `notifications` + `email_*` tables, 4 Finance events, 0-caller services. Scout proved 3 of 4 claims wrong before any deletion happened:
- `email_*` tables power the NEW notification outbox (`SmtpEmailTransport` reads `email_configurations`, writes `email_logs`; `notification_deliveries.email_log_id` FKs to it) — must NOT drop those 3 tables.
- Legacy `notifications` table has live prod paths (parent portal unread count, welcome-students write, manual-notify write) — user chose outright deletion (product change) over porting.
- Finance events are 3 (not 4), fire in live money-path code, tested — user deferred this decision, out of scope for this plan.

Real task: **deliberate staged decommission** of legacy notification/email surfaces, split into safe-first PRs, with product sign-off gated before the notifications feature-removal phase.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Delete 6 dead services (`config/migration_debt_paths.php` frozen list) with debt-inventory updated | P1 |
| 2 | Delete dispatcher-less legacy Academic event chains after crontab verification | P1 |
| 3 | Decommission legacy `notifications` table + all read/write call sites (after product sign-off) | P1 |
| 4 | Decommission legacy email template/bulk-send/monitoring surfaces (keep config/log/preferences tables) | P2 |
| 5 | Drop `notifications` + `email_templates` tables after 7-day soak, with backup | P2 |

## Non-Goals

- Finance events (`ChargeFullySettled`, `InstallmentPushed`, `InstallmentPushFailed`) — explicitly deferred by user. Not touched in this plan.
- Porting parent-portal unread count / welcome notifications to `NotificationMessage` — user chose delete-outright over port.
- Any refactor moving SMTP config / send-log ownership into the Notification module — correctly excluded as out of scope (that's a real refactor, not cleanup).

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Delete dead services](./phase-01-start.md) | Completed |
| 2 | [Phase 2: Delete dead event chains](./phase-02-delete-dead-event-chains.md) | Completed |
| 3 | [Phase 3: Decommission legacy notifications](./phase-03-decommission-legacy-notifications.md) | Pending |
| 4 | [Phase 4: Decommission legacy email surfaces](./phase-04-decommission-legacy-email-surfaces.md) | Pending |
| 5 | [Phase 5: Soak and drop tables](./phase-05-soak-and-drop-tables.md) | Pending |

## Success Criteria

- [ ] `grep -rn -- "->notifications()\|insteadof Notifiable\|DatabaseNotification" app` → 0 hits (was: `App\Models\Notification\b` — too narrow, can't see the `Notifiable` fallback; [Red-team F2])
- [ ] `grep -rlE "\bApp\\\\Models\\\\EmailTemplate\b|\bSendBulkEmailJob\b|\bEmailMonitoringController\b" app routes database resources --exclude-dir=Notification` → 0 hits (was: unanchored `EmailTemplate\b`, self-matches the kept `NotificationEmailTemplate`; [Red-team F7])
- [ ] `php artisan route:list | grep -iE "bulk|email-template|monitoring"` → empty (web + api); `email-configurations` config routes still present
- [ ] `config/migration_debt_paths.php` frozen_services no longer lists the 6 deleted services; `MigrationDebtInventoryTest` passes
- [ ] `email_logs.template_id` FK dropped before `email_templates` table drop; `EmailService`/`EmailLoggingService` kept and functional
- [ ] Full test suite: failures ≤ pre-cleanup baseline (Finance baseline 26 pre-existing)
- [ ] Prod: 0 cleanup-attributable errors (server logs AND manual browser-console check) over 7 days post each PR; parent portal, student portal, notification popper, and admin nav functional
- [ ] `SHOW TABLES` on prod: `notifications`, `email_templates` absent; `email_configurations`, `email_logs`, `user_email_preferences` present

## Open Questions

1. Finance events keep-vs-delete (and is "Batch D" still planned) — deferred by user, tracked outside this plan.
2. ~~Do parent-portal FE clients render the unread count today?~~ Resolved by red-team: yes, AND a second student-portal read path exists (`EloquentStudentPortalContextReader`) that the original plan missed entirely — both are now in Phase 3's scope and sign-off.
3. ~~Is `notifications`' PII content subject to a retention obligation before Phase 5's drop?~~ Resolved (Validation Session 1): no retention needed, deletion approved as-is.
4. Is prod's `NOTIFICATION_V2_WRITE_MODE` actually `v2`? [Red-team F3] Phase 3's blast radius depends on this and it was never checked before red-team. Now a blocking Phase 3 step 1.
5. ~~Does prod grant unauthenticated-tier users access to `routes/web/systems.php:14-20`?~~ Resolved (Validation Session 1): accepted as a known, time-boxed risk — the route is deleted by Phase 4 anyway; not worth a separate out-of-band fix given the short plan timeline.

## Red Team Review

### Session — 2026-08-07
**Findings:** 13 (13 accepted, 0 rejected)
**Severity breakdown:** 6 Critical, 6 High, 1 Medium
**Reviewers:** Security Adversary, Failure Mode Analyst, Assumption Destroyer, Scope & Complexity Critic (Full tier, all 4 lenses — 5 phases)

| # | Finding | Severity | Disposition | Applied To |
|---|---------|----------|-------------|------------|
| 1 | `email_logs.template_id` FK blocks Phase 5 drop | Critical | Accept | Phase 4, Phase 5 |
| 2 | `Notifiable insteadof` — trait removal fatals or silently swaps schema | Critical | Accept | Phase 3, plan.md |
| 3 | `NOTIFICATION_V2_WRITE_MODE` flag — legacy action deletion breaks endpoint regardless of mode | Critical | Accept | Phase 3 |
| 4 | Second live read path (StudentRegistry) missing from plan | Critical | Accept | Phase 3 |
| 5 | `EmailConfigurationController` "keep untouched" contradicts "delete EmailTemplate model" | Critical | Accept | Phase 4 |
| 6 | Phase 1 rationale ("frozen = known dead") factually inverted | Critical | Accept | Phase 1 |
| 7 | Success-criteria regex `EmailTemplate\b` self-matches kept module | High | Accept | Phase 4, plan.md |
| 8 | Phase 4 undercounts consumers by 6-9 files (API controller, command, seeder, factory, Vue) | High | Accept | Phase 4 |
| 9 | Crontab check targets nonexistent `Kernel.php` + wrong grep string; event misclassified | High | Accept | Phase 2 |
| 10 | Frontend (Vue/Ziggy/sidebar) entirely absent from Phase 3/4 | High | Accept | Phase 3, Phase 4, Phase 5 |
| 11 | Queue drain missing from Phase 2 (5 queued listeners) | High | Accept | Phase 2 |
| 12 | Phase 5 backup ≠ rollback; mysqldump of PII with no location/encryption/retention control | High | Accept | Phase 5 |
| 13 | `EmailService`/`EmailLoggingService` conditional-delete contradicts Requirements + Non-Goals, has live keep-surface callers | Medium | Accept | Phase 4 |

### Whole-Plan Consistency Sweep
- Files reread: plan.md, phase-01-start.md, phase-02-delete-dead-event-chains.md, phase-03-decommission-legacy-notifications.md, phase-04-decommission-legacy-email-surfaces.md, phase-05-soak-and-drop-tables.md
- Decision deltas checked: 13 (all findings above)
- Reconciled stale references: plan.md Success Criteria regexes (2), plan.md Open Questions (2 resolved/expanded, 2 new), Phase 3↔Phase 4 FK ownership boundary (`email_logs.template_id` now explicitly Phase 4's job, Phase 5 only re-verifies), Phase 4↔Phase 5 migration split (was 1 migration for 2 tables, now 2 migrations, Phase 4 also gets its own FK-drop migration), Phase 2 file classification (`AssessmentDeadlineApproaching` moved from unconditional-delete to conditional, matching Phase 1's "confirmed dead" pattern)
- Unresolved contradictions: 0

## Validation Log

### Session 1 — 2026-08-07
**Trigger:** Post-red-team `/ak:plan validate` — Red Team Review section already existed with verification evidence, so the automatic verification pass (Step 2.5) was skipped per the validate workflow's guard; questions targeted genuine remaining decision points instead.
**Questions asked:** 4

#### Questions & Answers

1. **[Scope]** Route `DELETE /systems/email-templates/{template}` has no `can:` gate (any authenticated user can call it) — red-team flagged this alongside Phase 4 but called it a pre-existing gap, out of scope. How to handle?
   - Options: Split into a separate ticket, fix now | Skip — route is being deleted anyway (Recommended by user) | Add the gate to Phase 4 as a stopgap
   - **Answer:** Skip — route sắp bị xóa (accept as a known, time-boxed risk since Phase 4 deletes the route)
   - **Rationale:** The exposure window is bounded by this plan's own timeline; a separate out-of-band fix would cost more than the residual risk given the route disappears within the plan's execution.

2. **[Risk]** `notifications` table (dropped in Phase 5) contains PII (full_name, student_id, email via morph). Is there a retention/compliance requirement for notification history, or is deletion fine?
   - Options: No retention needed, deletion OK (Recommended by user) | Need longer retention (compliance) | Unknown, defer
   - **Answer:** Không cần, xóa (no retention needed, deletion approved)
   - **Rationale:** Resolves Open Question 3 — Phase 5 can proceed with its planned mysqldump-and-destroy-after-retention-date approach without an additional compliance hold.

3. **[Scope]** Phase 1 (services) and Phase 2 (event chains) are both zero-risk, no soak/sign-off needed. Merge into one PR to reduce ceremony, or keep separate?
   - Options: Keep 2 separate PRs (Recommended by planner) | Merge into 1 PR
   - **Answer:** Ghép thành 1 PR (merge into one PR)
   - **Rationale:** User prioritizes less ceremony over independent revertability for these two zero-risk, unrelated-domain phases; both share "no soak needed" status so the safety profile is unaffected by merging.

4. **[Assumptions]** Phase 3 requires "written product sign-off" — who actually signs it (loses parent/student unread count + welcome notification)?
   - Options: User signs off directly (Recommended by user) | Needs a separate product/PM approver
   - **Answer:** Tôi (user) ký trực tiếp (user signs off directly)
   - **Rationale:** No separate product function exists for this decision; the user is the accountable decision-maker for Phase 3's product/UX trade-off.

#### Confirmed Decisions
- Authz gap on `routes/web/systems.php:14-20`: accepted as time-boxed risk, no separate fix — Phase 4 deletes the route anyway
- `notifications` PII retention: not required — Phase 5's dump-with-retention-date approach is sufficient, no compliance hold
- Phase 1 + Phase 2: ship as a single PR (structural note only — phase files stay separate for traceability, but the PR/merge boundary is combined)
- Phase 3 product sign-off: the user is the sign-off authority; no external product approver blocks this phase

#### Action Items
- [x] Resolve Open Question 3 (PII retention) — done above
- [x] Resolve Open Question 5 (authz gap) — done above
- [ ] When executing, ship Phase 1 + Phase 2 as one combined PR per this session's decision

#### Impact on Phases
- Phase 1 & Phase 2: add a shipping note — combined into a single PR (see below)
- plan.md Open Questions: 3 and 5 marked resolved

### Whole-Plan Consistency Sweep (Session 1)
- Files reread: plan.md, phase-01-start.md, phase-02-delete-dead-event-chains.md, phase-03-decommission-legacy-notifications.md, phase-04-decommission-legacy-email-surfaces.md, phase-05-soak-and-drop-tables.md
- Decision deltas checked: 4 (all answers above)
- Reconciled: plan.md Open Questions 3 & 5 marked resolved; Phase 1 and Phase 2 overview sections annotated with the combined-PR shipping note; no other phase content referenced the authz gap or PII retention topics, so no further propagation needed
- Unresolved contradictions: 0

<!-- slug: legacy-notificationemail-decommission -->
