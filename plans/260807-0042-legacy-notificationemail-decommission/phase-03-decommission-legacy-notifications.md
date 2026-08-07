---
phase: 3
title: "Decommission legacy notifications"
status: completed
priority: P1
effort: "1-2d"
dependencies: [1, 2]
---

# Phase 3: Decommission legacy notifications

## Overview

Delete the legacy `notifications` table's write path (`SendWelcomeStudents`), BOTH read paths (parent-portal AND student-portal unread count — see [Red-team F4]), and all supporting code. User explicitly chose delete-outright over porting to `NotificationMessage` — this is a **product/UX change** (parents and students lose unread count, new students stop getting welcome notifications, admins lose manual-notify), not pure dead-code removal. Requires written product sign-off before merge.

[Red-team F2] Removing `HasNotifications` is NOT a simple deletion: all three models (`User`, `Student`, `Lecture`) use it inside a PHP trait conflict-resolution block (`use HasNotifications, Notifiable { HasNotifications::notifications insteadof Notifiable; }`). Deleting only the trait import without the `insteadof` line is a PHP fatal compile error (app-wide outage). Deleting the whole block silently falls back to Laravel's `Notifiable::notifications()`, which queries the SAME `notifications` table against a DIFFERENT schema (uuid PK vs auto-increment, no `unread()` scope) — this fails silently until Phase 5 drops the table.

[Red-team F3] `SendManualNotificationAction` (admin manual-notify) is gated by a runtime config flag `notification.write_mode` (env `NOTIFICATION_V2_WRITE_MODE`, supports `off|dual|v2|v2_only`), but `NotificationController::send()` method-injects BOTH the legacy and v2 action classes — Laravel resolves all method parameters from the container before the `if` branch runs. Deleting the legacy class breaks the endpoint on EVERY call regardless of which mode prod is actually running, and the plan never verified what prod's flag is set to.

## Requirements

- Functional: `SendWelcomeStudents` command, parent-portal AND student-portal unread-count reads, `app/Actions/Notification/*`, `HasNotifications` trait usage (including the `insteadof Notifiable` conflict-resolution clause), `NotificationBroadcast`, `Notification` model all removed.
- Functional: `app/Notifications/*` classes with `via() => ['database']` (writers to the `notifications` table via Laravel's native `Notifiable`) audited and removed/repointed.
- Non-functional: prod `NOTIFICATION_V2_WRITE_MODE` verified to be `v2` (or `v2_only`) BEFORE deleting `SendManualNotificationAction` — if it's `off`/`dual`, flip to `v2` and soak first.
- Non-functional: written product sign-off (covering ALL 3 losses: parent unread count, student unread count, welcome notifications — plus admin manual-notify if write_mode isn't already `v2`) attached to the PR description.
- Non-functional: FE (`resources/js` NotificationPopper, sidebar/menu constants, student-nuxt) confirmed to hit only the `NotificationMessage`/outbox API after this change, not the legacy endpoint.

## Related Code Files

- Delete: `app/**/Commands/SendWelcomeStudents.php` (confirm exact path)
- Modify: `app/**/Queries/GetParentContextQuery.php` — remove unread-count read at line ~28
- Modify: `app/Modules/StudentRegistry/Support/EloquentStudentPortalContextReader.php:56` — remove `$student->notifications()->unread()->count()` [Red-team F4: this second read path was missing from the original plan]
- Delete: `app/Actions/Notification/*` (`GetNotificationsAction`, `MarkAllNotificationsAsReadAction`, `SendManualNotificationAction`, etc.)
- Modify: `app/Http/Controllers/Web/Admin/NotificationController.php` — remove the `$legacyAction` parameter and the `write_mode === 'v2'` branch in the SAME commit as the action deletion [Red-team F3]
- Modify: `routes/web/notifications.php` — remove routes exposing deleted legacy actions
- Modify: `config/notification.php` — remove the unused `read_mode` key if confirmed dead (0 consumers per red-team grep)
- Modify: `app/Models/User.php`, `app/Models/Student.php`, `app/Models/Lecture.php` — remove `HasNotifications` trait usage AND the `insteadof Notifiable` clause; explicitly decide whether `Notifiable` itself stays [Red-team F2]
- Delete: `app/**/HasNotifications.php` trait, `NotificationBroadcast`, `Notification` model
- Audit: `app/Notifications/*` classes with `via() => ['database']` — these write to the `notifications` table through the native `Notifiable` path and are not caught by any `App\Models\Notification` grep
- Modify: `resources/js` NotificationPopper component, sidebar/menu-constant files referencing notification routes, and student-nuxt FE — remove/repoint dead endpoint calls [Red-team F10]

## Implementation Steps

1. SSH prod, verify `NOTIFICATION_V2_WRITE_MODE` (or `config('notification.write_mode')` via tinker). If not `v2`/`v2_only`, this phase's blast radius is larger (admin manual-notify would currently be routing through the code about to be deleted) — flip to `v2` and soak before continuing.
2. Get written product sign-off first (blocking): confirm parents AND students lose unread count, new students stop getting welcome notifications, and (if step 1 required a flag flip) admin manual-notify implications. Attach to PR description before merging.
3. `grep -rn "HasNotifications\|NotificationBroadcast\|Actions\\\\Notification" app routes resources` AND `grep -rn -- "->notifications()" app` (the second catches relation calls the first misses — this is how Finding 4's second read path was found) to enumerate every call site.
4. Delete `SendWelcomeStudents`, `app/Actions/Notification/*`; remove `$legacyAction` param + branch from `NotificationController::send()` in the same commit; remove exposed routes.
5. Remove the unread-count read in `GetParentContextQuery` AND `EloquentStudentPortalContextReader` (and any FE code rendering either, if the FE renders a count that no longer resolves).
6. Remove `HasNotifications` trait AND the `insteadof Notifiable` clause from User/Student/Lecture models (decide + document whether `Notifiable` stays); delete the trait file, `NotificationBroadcast`, `Notification` model; audit/delete `app/Notifications/*` classes using the `database` channel.
7. Verify FE: `resources/js` NotificationPopper, sidebar/menu constants, and student-nuxt hit only the `NotificationMessage` API — no dangling references to deleted classes/routes/Ziggy route names.
8. Run full test suite for Academic/Identity/StudentRegistry/parent-portal modules; fix any breakage from removed unread counts.

## Success Criteria

- [ ] Prod `write_mode` verified `v2`/`v2_only` before legacy action deletion
- [ ] Written product sign-off (covering all losses) attached to PR
- [ ] `grep -rn -- "->notifications()\|insteadof Notifiable\|DatabaseNotification" app` → 0 hits (replaces the narrower `App\Models\Notification\b`-only grep, which cannot see the `Notifiable` fallback)
- [ ] `SendWelcomeStudents`, `app/Actions/Notification/*`, `HasNotifications` trait + `insteadof` clause, `NotificationBroadcast`, `Notification` model all deleted
- [ ] FE confirmed clean of legacy notification endpoint calls (named files checked, not a vague "Check:")
- [ ] No new test failures vs baseline; parent AND student portals functional (minus the removed unread counts, which are accepted)

## Risk Assessment

Medium-high — this is a product change plus a subtle Laravel trait-resolution hazard, not pure dead-code deletion. [Red-team F2] Blocking risk: incorrectly removing `HasNotifications` either fatals the whole app at compile time or silently swaps to a schema-incompatible fallback that only breaks after Phase 5's table drop. [Red-team F3] Deleting `SendManualNotificationAction` without removing its still-injected controller parameter breaks the admin send endpoint unconditionally. [Red-team F4] The student-portal read path is as live as the parent-portal one and was originally missing from this phase entirely. Mitigate by treating all four sub-risks as hard gates, not simplifications of "delete the model."

## Execution Evidence (2026-08-07)

- **Gate 1 (prod write_mode):** SSH tinker confirmed `config('notification.write_mode')` = `v2` on prod. Admin manual-notify already routed through v2 before this change — no flag flip/soak needed.
- **Gate 2 (product sign-off):** user confirmed directly in-chat, accepting all 3 losses (parent unread count, student unread count, welcome notifications). Admin manual-notify unaffected (already v2).
- **F2 (trait removal):** removed `HasNotifications` + its `insteadof Notifiable` clause as one atomic block from `User`/`Student`/`Lecture` (not partial — avoids the PHP fatal). Kept `Notifiable` — still required for password-reset (`CanResetPassword::sendPasswordResetNotification`) and email-verification mail-channel notifications, both unaffected by the DB-relation removal. Verified 0 remaining `->notifications()`/`insteadof`/`DatabaseNotification` references repo-wide, so `Notifiable`'s native fallback relation is unreachable — no schema-mismatch risk.
- **F3 (controller DI):** `NotificationController::send()` no longer injects `SendManualNotificationAction` — always calls `SendManualNotificationV2Action` (matches prod's actual `v2` mode).
- **F4 (student-portal read):** removed alongside the parent-portal read in the same commit.
- **Audit finding beyond original scope:** all 4 `app/Notifications/*` classes using `via() => ['database']` had zero dispatch sites anywhere in the repo (confirmed by exact-class grep, not just filename match) — deleted as dead code.
- **FE:** no FE consumer found in this repo for either removed `unread_count` field; code-reviewer flagged a crash risk for an out-of-repo parent-portal FE destructuring the key unguarded — mitigated by keeping `notifications.unread_count` in both response shapes hardcoded to `0` instead of dropping the key outright.
- **Deferred to Phase 4/5 (code-reviewer LOW findings, not blocking):** `routes/channels.php` `notifications.{id}` broadcast channel and `FE/student-nuxt/app/composables/useRealtime.ts`'s `useRealtimeNotification` (0 consumers) were fed only by the now-deleted `NotificationBroadcast` — orphaned but harmless; clean up when touching email/broadcast surfaces in Phase 4. `NOTIFICATION_V2_READ_MODE` env var may still exist in prod `.env` — harmless now that the config key reading it is gone; strip during Phase 5.
- Tests: identical pre-existing failures before/after (git-stash compared) across Architecture, Identity, Registry, Notification, Lecturer suites — zero regression.
