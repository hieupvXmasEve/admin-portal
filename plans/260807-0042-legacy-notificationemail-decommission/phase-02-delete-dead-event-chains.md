---
phase: 2
title: "Delete dead event chains"
status: completed
priority: P1
effort: "0.5-1d"
dependencies: [1]
---

# Phase 2: Delete dead event chains

## Overview

Delete dispatcher-less Academic event chains and their listeners. Requires a prod crontab check first — grep alone cannot see cron-invoked commands.

<!-- Updated: Validation Session 1 - ship together with Phase 1 as a single PR (both zero-risk, no soak needed) -->

## Requirements

- Functional: dead events, their listeners, and `EventServiceProvider` entries removed.
- Non-functional: prod crontab confirmed clear of `ScheduleAssessmentReminders` (or equivalent) before deleting that command.

## Related Code Files

- Delete: event classes — `AcademicHoldPlaced`, `EnrollmentConfirmed`, `GradePublished` (zero dispatchers), `CourseRegistrationOpened` (only dispatcher is a Phase 1 dead service)
- Delete (conditional, see step 2): `AssessmentDeadlineApproaching` — [Red-team F9] has TWO live dispatch sites in `ScheduleAssessmentReminders::handle()`, not zero. Only delete if step 2 confirms the command is not cron/schedule-invoked.
- Delete: matching listener classes for each event above
- Delete: `ScheduleAssessmentReminders` command (only after crontab check)
- Modify: `app/Providers/EventServiceProvider.php` (or module-local equivalent) — remove entries for the deleted events/listeners

## Implementation Steps

1. `grep -rn "AcademicHoldPlaced\|EnrollmentConfirmed\|GradePublished\|CourseRegistrationOpened" app routes config` — confirm zero dispatch sites left after Phase 1 removes `CourseRegistrationOpened`'s only dispatcher. Separately confirm `AssessmentDeadlineApproaching`'s only dispatch sites are inside `ScheduleAssessmentReminders.php`.
2. [Red-team F9] This repo has NO `app/Console/Kernel.php` (Laravel 11-style) — the schedule lives in `routes/console.php`. Run: `grep -n "Schedule::" routes/console.php`, `./scripts/dev.sh artisan schedule:list` on prod, AND SSH prod (`ssh root@157.10.186.103`) `crontab -l` — grep the crontab for the artisan **signature** (e.g. `email:schedule-assessment-reminders`, check the command's actual `$signature` property), not the PHP class name, since cron invokes signatures. Also check any supervisor/systemd timers. Do not delete `ScheduleAssessmentReminders`, `AssessmentDeadlineApproaching`, or its listener if any of these show it's still invoked.
3. [Red-team F11] Drain the queue before deploy: all 5 listeners in this phase are `ShouldQueue` on the `database` queue connection (persistent, not sync). Check `jobs` and `failed_jobs` tables for pending payloads referencing the classes being deleted; drain or clear them before deploy so workers don't hit `ClassNotFoundException` on deserialize.
4. Delete the events + their listeners + `ScheduleAssessmentReminders` (only if step 2 clears it — if cron is NOT clear, STOP: do not delete `AssessmentDeadlineApproaching`/its listener/`EventServiceProvider` entry either; disable the cron entry first, redeploy, wait one tick, then proceed).
5. Remove corresponding `EventServiceProvider` registrations.
6. Run affected module test suites.

## Success Criteria

- [ ] Event classes + listeners deleted, `EventServiceProvider` entries removed
- [ ] `ScheduleAssessmentReminders` + `AssessmentDeadlineApproaching` deleted only if `routes/console.php` + prod crontab (checked by signature) + `schedule:list` all confirm clear
- [ ] `jobs`/`failed_jobs` drained of payloads referencing deleted listener classes before deploy
- [ ] No new test failures vs baseline

## Risk Assessment

Low-medium once corrected. [Red-team F9] Original crontab-check step pointed at a nonexistent `app/Console/Kernel.php` and grepped a class name that can't appear in a crontab — both false-negative traps that would have let a live cron job break silently (cron failures don't surface as HTTP 500s, so Phase 5's soak gate wouldn't catch them). [Red-team F11] Queue drain was previously only required in Phase 4; these 5 listeners are equally queued. Do not skip step 2 or step 3.

## Execution Evidence (2026-08-07)

- **Crontab check (step 2):** SSH'd prod (157.10.186.103), confirmed no `app/Console/Kernel.php` (Laravel 11, schedule lives in `routes/console.php`). `routes/console.php` `Schedule::` entries do NOT include `email:schedule-assessment-reminders` (the command's actual signature). Prod crontab only invokes `artisan schedule:run` (3x, one per site) — Laravel's own scheduler, already confirmed clear — plus 2 unrelated baota-panel hash-named jobs (verified by user: backup/SSL, not app-related). Supervisor conf.d has one queue-worker program (`queue:work --queue=default,emails,notifications,webhooks,reconciliation`), not a scheduler/cron entry. **Conclusion: `ScheduleAssessmentReminders` is not cron-invoked anywhere on prod.**
- **Queue drain (step 3):** local dev `jobs`/`failed_jobs` tables checked — 0 payloads referencing any of the 5 deleted listener classes. Prod queue drain is a deploy-time operational step (not blocking this PR); do it in the same deploy window as Phase 1's `optimize:clear`.
- All 5 events + listeners + `ScheduleAssessmentReminders` + the now-fully-dead `EventServiceProvider.php` (its whole `$listen` array was only these 5 mappings) deleted in one pass, including `AssessmentDeadlineApproaching` — crontab evidence above satisfies the phase's conditional gate.
- `bootstrap/providers.php`: removed `EventServiceProvider::class` registration + `use` import.
- Full `tests/Feature/Architecture` suite: identical 5 pre-existing failures with/without this change (git-stash compared) — zero regression.
- Shipped combined with Phase 1 in one PR per Validation Session 1.
