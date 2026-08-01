---
phase: 4
title: "Phase 4: Portal Confirmation"
status: todo
priority: P2
effort: "3d"
dependencies: [3]
---

# Phase 4: Portal Confirmation

## Overview

Student confirms (or disputes) the interview minutes on the Student Portal, tied to an exact minutes version. Staff can confirm on behalf with a dedicated permission. 1-calendar-day deadline → `confirmation_overdue`.

<!-- red-team 2026-08-01: student.api.auth alone (no either/parent); hold-exempt route; single status owner via decision service -->
<!-- module-fix 2026-08-01: P3 was relocated to the Academic **Progression**
     sub-module and Services were converted to Actions. P4 follows the same
     convention: confirmation "decision service methods" are Progression
     Actions under `app/Modules/Academic/Progression/Actions/ScholarshipAdjustment/`;
     the model lives in `Academic/Progression/Models`. The STUDENT-PORTAL API,
     however, is its own global bounded surface (`app/Http/Controllers/Api/V1/Student/*`,
     requests in `app/Http/Requests/Api/V1/*`) — the student confirmation
     controller belongs THERE, not in the Academic module; it stays a thin
     transport that calls the Progression Actions. -->

## Requirements

- Functional:
  - Student sees minutes + submits: agree | disagree/request-correction, optional comment.
  - Confirmation stores confirmed `minutes_version`; minutes edit after confirmation invalidates it (P3 version++).
  - Staff confirm-on-behalf: `confirm_scholarship_adjustment_on_behalf` + mandatory contact-evidence note.
  - Deadline: `confirmation_requested_at + 1 calendar day` (Asia/Saigon) → overdue; Đào tạo proceeds only with approver + note (P3 gate).
- Non-functional:
  - **Auth: `student.api.auth` middleware ALONE — NOT the shared `either:parent.student.access,student.api.auth` group** (`routes/api/v1/student.php:35`). `EitherMiddleware` passes if EITHER passes and `ParentStudentAccess` accepts a guardian with `X-Student-ID`/`student_id` input (`ParentStudentAccess.php:36-57`) — a parent must NOT be able to bind the student's legally-relevant acknowledgement. Derive student strictly from `$request->user()`; reject any non-Student actor.
  - **Hold-exempt route:** `StudentApiAuthorization::holdBlocksRoute()` blocks every non-`profile` route for students with `financial`/`all` holds (`StudentApiAuthorization.php:100-114`) — exactly this fee-increase cohort. Add an explicit exemption for the confirmation/acknowledgement routes (route-name allow-list alongside `profile`), else students are locked out then counted "quá hạn không phản hồi".
  - Student sees ONLY own dossier (ownership from authenticated student id, never request input).

## Architecture

**Dossier columns (P4 migration):**

```
confirmation_status      string(30)  // pending|confirmed|disputed|declined|overdue
confirmation_requested_at datetime nullable
confirmed_minutes_version unsignedInteger nullable
student_comment          text nullable
confirmed_at             datetime nullable
confirmed_by_user_id     FK users nullable   // Student user or staff user
confirmed_on_behalf      boolean default false
on_behalf_note           text nullable
```

**Single-owner rule:** `confirmation_status` and dossier `status` are both mutated ONLY through the confirmation **Progression Actions** (mirrors P3's split of the former decision service into Actions): `RequestConfirmationAction`, `RecordStudentResponseAction`, `RecordOnBehalfConfirmationAction`, `MarkOverdueAction` — all under `app/Modules/Academic/Progression/Actions/ScholarshipAdjustment/`. The overdue command, the student API controller, and the staff web controller call these Actions; none writes the columns directly. `MarkOverdueAction` REJECTS when `confirmed_at IS NOT NULL` (race: student confirms seconds before the nightly run) — guards the confirmed+overdue inconsistency. Any status-write guard rules (e.g. terminal-status) belong in these Actions, matching `DecideAdjustmentAction`/`ApproveAdjustmentAction`.

**Confirmation gate (SETTLED 2026-08-01):** `confirmation_status` is a SEPARATE axis from the P3 main `status` (matches the separate-column schema). The P3 main flow is UNCHANGED (`interviewed → ready_for_decision → approved`). The gate is enforced INSIDE `DecideAdjustmentAction`: the maker decision is BLOCKED unless `confirmation_status === confirmed`, OR (`confirmation_status === overdue` AND a `confirmation_override_reason` is supplied by a holder of `approve_scholarship_adjustment` at the dossier campus — reuse the existing exception-path pattern already in `DecideAdjustmentAction`). `disputed`/`pending`/`declined` → decide refused. This keeps a single clean gate (no P3 state-machine rework) while making the student acknowledgement a hard precondition for a fee-increasing decision. The P3 status token `awaiting_student_confirmation` = "interview complete, `confirmation_status = pending`".

**Flow:**

- Interview completed → `RequestConfirmationAction`: sets `confirmation_status = pending` + `confirmation_requested_at`, notification → portal pending item. (Main `status` stays `interviewed`.)
- Student POST confirm/dispute (`RecordStudentResponseAction`): validate `minutes_version === dossier.minutes_version` (stale ⇒ 409, client refetches); agree → `confirmation_status = confirmed` + `confirmed_at`/`confirmed_minutes_version`; dispute → `confirmation_status = disputed` + comment. Main `status` untouched here — the gate lives in `DecideAdjustmentAction`.
- Minutes edited later → `EditMinutesAction` (P3) resets `confirmation_status` to pending, clears confirmed fields, re-notifies (extend the existing Action, not a new writer).
- Overdue: daily command `academic:mark-scholarship-confirmations-overdue` → `confirmation_status = pending` + requested_at older than 1 calendar day (Asia/Saigon) → `overdue` (via `MarkOverdueAction`). Proceeding from overdue = the approver-gated decide branch above.
- Staff on-behalf (`RecordOnBehalfConfirmationAction`): staff web endpoint, permission + mandatory note; `confirmed_on_behalf = true`, `confirmation_status = confirmed`.

**Endpoints:**

- Student API — NEW route group `student.api.auth` only (no `either`): `GET .../scholarship-adjustments/{id}/minutes`, `POST .../scholarship-adjustments/{id}/confirmation`. Route names added to the hold-exemption allow-list.
- Staff web: `POST /scholarship-adjustments/{id}/confirm-on-behalf`.

**Frontend (FE/student-nuxt):** notification deep-link → minutes view + confirm/dispute form; follow existing page/composable conventions.

**Notifications:** `NotificationMessage` + outbox: on request, on invalidation, on overdue.

## Related Code Files

Paths pinned per `.claude/rules/development-rules.md` → "File Placement & Module Ownership". Two owners here: the **student-portal API** (global bounded surface) and the **Academic Progression** domain.

Domain layer (Academic Progression — owns the dossier + confirmation rules):
- Create: `app/Modules/Academic/Progression/Actions/ScholarshipAdjustment/RequestConfirmationAction.php`
- Create: `.../Actions/ScholarshipAdjustment/RecordStudentResponseAction.php` (version-match confirm/dispute; stale ⇒ 409)
- Create: `.../Actions/ScholarshipAdjustment/RecordOnBehalfConfirmationAction.php`
- Create: `.../Actions/ScholarshipAdjustment/MarkOverdueAction.php` (overdue race guard: no-op when `confirmed_at` set)
- Modify: `.../Actions/ScholarshipAdjustment/EditMinutesAction.php` — reset confirmation to pending on minutes edit
- Migration adding the confirmation columns (global `database/migrations/`)

Student-portal API (global surface — matches `Api/V1/Student/DashboardController` convention; NOT the Academic module):
- Create: `app/Http/Controllers/Api/V1/Student/ScholarshipAdjustmentConfirmationController.php` (thin transport → Progression Actions)
- Create: FormRequest under `app/Http/Requests/Api/V1/` (student-portal request convention)
- Register routes in `routes/api/v1/student.php` — a NEW sub-group under `student.api.auth` ALONE (no `either`, no `parent.student.access`); route names added to the hold-exemption allow-list
- Modify: `app/Http/Middleware/StudentApiAuthorization.php` — confirmation route-name exemption in `holdBlocksRoute()` (allow-list beside `profile`)

Staff web (Academic Progression — the existing dossier controller):
- Modify: `app/Modules/Academic/Progression/Http/Web/ScholarshipAdjustmentDossierController.php` — add `confirmOnBehalf` action; route in `app/Modules/Academic/routes/web.php` (module route file); FormRequest under `app/Modules/Academic/Progression/Http/Requests/ScholarshipAdjustment/`

Cross-cutting / infra (global — Academic module has no Console dir, consistent with `IdentifyScholarshipAdjustmentCandidates`):
- Create: `app/Console/Commands/MarkScholarshipConfirmationsOverdue.php` + scheduler registration (calls `MarkOverdueAction`)
- Placement arch test: extend `tests/Feature/Architecture/ScholarshipAdjustmentModulePlacementArchTest.php` — confirmation Actions live in Progression; staff on-behalf stays on the Progression Web controller. (The student-portal controller is intentionally global — assert it is NOT forced into the module.)

Frontend (FE/student-nuxt — separate surface):
- Create: student-nuxt page + composable (notification deep-link → minutes view + confirm/dispute)
- Modify: staff dossier detail UI (`resources/js/pages/ScholarshipAdjustments/Show.vue`) — confirmation panel + on-behalf action

## Implementation Steps

1. Migration + allow-list values.
2. Student route group + controller; tests: ownership (A cannot read B), **guardian token + `X-Student-ID` → 403**, **student with financial hold CAN confirm** (exemption test), non-Student actor rejected.
3. Version-match confirm/dispute + invalidation-on-edit test.
4. Overdue command + scheduler; tests: Asia/Saigon calendar-day boundary, race guard (confirmed_at set → markOverdue no-op).
5. Staff on-behalf endpoint + permission test.
6. Portal UI + notification deep-link.

## Todo

- [ ] Migration
- [ ] Student endpoints (student.api.auth only) + ownership/parent/hold tests
- [ ] Version confirm/invalidate
- [ ] Overdue command + race guard
- [ ] On-behalf flow
- [ ] Portal UI

## Success Criteria

- [ ] Confirmation references exact minutes version; stale rejected 409.
- [ ] Minutes edit voids confirmation and re-requests.
- [ ] Overdue at +1 calendar day; confirmed-then-overdue race impossible (guard test).
- [ ] Parent/guardian token cannot confirm (negative test); held student can confirm (exemption test).
- [ ] On-behalf flagged with note + permission; student only sees own dossier.
- [ ] `confirmation_status`/dossier status mutated only via the confirmation Progression Actions (single-writer) — asserted by the placement arch test.

## Risk Assessment

- Timezone off-by-one → app-timezone computation + boundary unit tests.
- Student without portal account → on-behalf fallback; overdue path caps waiting.
- Hold-exemption too broad → allow-list exact route names only, test that other routes remain hold-blocked.
