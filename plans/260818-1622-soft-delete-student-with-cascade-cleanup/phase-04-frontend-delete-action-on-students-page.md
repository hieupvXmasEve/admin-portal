---
phase: 4
title: "Frontend delete action on students page"
status: pending
priority: P1
effort: "3h"
dependencies: [3]
---

# Phase 4: Frontend delete action on students page

## Overview

Add a permission-gated Delete row action to `resources/js/pages/Students/Index.vue`, reusing the exact patterns already in this codebase (`usePermission().can()`, `useGlobalConfirmDialog`, `router.delete`) — confirmed via `resources/js/pages/RoomBookings/Index.vue`.

<!-- Updated: Red Team Session 1 - Finding 14 -->
Response contract with the backend is now pinned (Phase 3): `destroy()` returns `back()` with flash, not a redirect to the deleted student's own page. `router.delete` against a `back()` response naturally reloads the current index URL (filters/pagination preserved) — no separate contradiction to resolve here, just confirm this assumption holds once Phase 3 lands.

## Requirements

- Functional: Delete action visible only when `can('delete_student')` is true.
- Functional: clicking Delete opens a confirm dialog (destructive/irreversible-looking copy, even though it's a soft-delete) before calling `router.delete`.
- Non-functional: on success, table refreshes / row disappears (Inertia partial reload of the current index URL, filters and pagination preserved — per Phase 3's `back()` contract); on guard-block error, show the server's specific flash error message (now one of: missing billing account, unverifiable finance position, non-zero balance), not a generic failure.

## Related Code Files

- Modify: `resources/js/pages/Students/Index.vue`
  - `usePermission` already imported (`:19`, `const { can } = usePermission()` at `:91`) — reuse.
  - Add `useGlobalConfirmDialog` import (pattern from `resources/js/pages/RoomBookings/Index.vue:9,47`).
  - Add a `deleteStudent(student: Student)` handler near `editStudent`/`goToStudentActions` (`:394-408`), calling `router.delete(studentRoutes.destroy(student.id), { onSuccess/onError })` after `confirmDialog.confirm(...)`.
  - Add the row action button (Tooltip-wrapped, matching the `change_student_status`/`view_student_action` pattern at `:659`, `:667`) gated by `v-if="can('delete_student')"`.
- Modify: student routes helper (wherever `studentRoutes.edit`/`studentRoutes.studentAcademicSummary` are defined — likely a generated Ziggy/route helper or `resources/js/routes/students.ts`) — add `destroy`.

## Implementation Steps

1. Locate the route helper backing `studentRoutes.*` (grep `studentRoutes\.` usage + its import) and add `destroy(id)`.
2. Add `deleteStudent` handler using `useGlobalConfirmDialog` + `router.delete`, mirroring `RoomBookings/Index.vue:130`.
3. Add the row action UI (icon button + Tooltip, `can('delete_student')` gated) in the actions column render function near the existing action buttons.
4. Manual browser check: as a user without `delete_student`, confirm no Delete button renders; as a user with it, confirm dialog → delete → row disappears (or list reloads without it) → flash success toast.
5. Manual check for each guard-block case (missing billing account, invalid finance position, non-zero balance — see Phase 3): confirm the dialog's error path surfaces the server's specific message.

## Success Criteria

- [ ] Delete action only rendered for `can('delete_student')` users
- [ ] Confirm dialog required before delete fires
- [ ] Successful delete updates the table without full page reload
- [ ] Guard-block error surfaces the server's message, not a silent no-op

## Risk Assessment

Low — additive UI following established conventions. Main risk is skipping the confirm-dialog step and making a destructive-looking action a single click, inconsistent with every other delete flow in this app.
