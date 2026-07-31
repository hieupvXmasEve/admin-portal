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

**Single-owner rule:** `confirmation_status` and dossier `status` are both mutated ONLY through `ScholarshipAdjustmentDecisionService` methods (`requestConfirmation`, `recordStudentResponse`, `recordOnBehalfConfirmation`, `markOverdue`). The overdue command and controllers call these methods. `markOverdue` REJECTS when `confirmed_at IS NOT NULL` (race: student confirms seconds before the nightly run) — guards the confirmed+overdue inconsistency.

**Flow:**

- P3 `awaiting_student_confirmation` → sets `confirmation_requested_at`, notification → portal pending item.
- Student POST confirm: validate `minutes_version === dossier.minutes_version` (stale ⇒ 409, client refetches); → `ready_for_decision` or `student_disputed`.
- Minutes edited later → decision service resets confirmation to pending, clears confirmed fields, re-notifies.
- Overdue: daily command `academic:mark-scholarship-confirmations-overdue` → pending + requested_at older than 1 calendar day (Asia/Saigon) → overdue (via decision service). Proceeding from overdue = P3 approver gate.
- Staff on-behalf: staff web endpoint, permission + mandatory note; `confirmed_on_behalf = true`.

**Endpoints:**

- Student API — NEW route group `student.api.auth` only (no `either`): `GET .../scholarship-adjustments/{id}/minutes`, `POST .../scholarship-adjustments/{id}/confirmation`. Route names added to the hold-exemption allow-list.
- Staff web: `POST /scholarship-adjustments/{id}/confirm-on-behalf`.

**Frontend (FE/student-nuxt):** notification deep-link → minutes view + confirm/dispute form; follow existing page/composable conventions.

**Notifications:** `NotificationMessage` + outbox: on request, on invalidation, on overdue.

## Related Code Files

- Create: migration adding confirmation columns
- Create: student API controller + FormRequest + dedicated route group (`student.api.auth` alone)
- Modify: `app/Http/Middleware/StudentApiAuthorization.php` — confirmation route-name exemption in `holdBlocksRoute()`
- Create: `app/Console/Commands/MarkScholarshipConfirmationsOverdue.php` + scheduler registration
- Modify: `ScholarshipAdjustmentDecisionService` — the four confirmation methods incl. overdue race guard
- Create: student-nuxt page + composable
- Modify: staff dossier detail UI — confirmation panel + on-behalf action

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
- [ ] `confirmation_status`/dossier status mutated only via decision service.

## Risk Assessment

- Timezone off-by-one → app-timezone computation + boundary unit tests.
- Student without portal account → on-behalf fallback; overdue path caps waiting.
- Hold-exemption too broad → allow-list exact route names only, test that other routes remain hold-blocked.
