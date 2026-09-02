---
title: "Phase 1: Remove reminder surface"
status: todo
---

# Phase 1: Remove reminder surface

## Overview

Delete the Batch Studio reminder wizard and every staff entry that pointed at it. Leave charges, DNG, shared reminder actions, and due-calendar.

## Requirements

- [x] No GET/POST `/finance/batch-studio/reminders` and no preview API
- [x] Hub tiles: charge_generation + dng_push only
- [x] SendToBatchBar: DNG only
- [x] No leftover `BatchJobType::Reminder` or `jobs.reminder`

## Related Code Files

- Delete: `resources/js/pages/Finance/BatchStudio/Reminders.vue`
- Delete: `app/Modules/Finance/Http/Requests/Batch/CommitBatchRemindersRequest.php`
- Delete: `app/Modules/Finance/Http/Requests/Batch/PreviewBatchRemindersRequest.php`
- Delete: `app/Modules/Finance/Queries/Batch/AssembleBatchReminderPreviewQuery.php`
- Modify: `app/Modules/Finance/routes/web.php` — drop reminders GET/POST
- Modify: `app/Modules/Finance/routes/api.php` — drop reminders.preview
- Modify: `app/Modules/Finance/Http/Web/Admin/BatchStudioController.php` — drop `reminders()`, `commitReminders()`, hub `jobs.reminder`, unused imports
- Modify: `app/Modules/Finance/Http/Api/Admin/BatchStudioPreviewController.php` — drop `previewReminders()`
- Modify: `app/Modules/Finance/Support/Batch/BatchJobType.php` — drop `Reminder`
- Modify: `resources/js/pages/Finance/BatchStudio/Hub.vue` — drop reminder tile; jobs prop without `reminder`
- Modify: `resources/js/components/finance/lookup/SendToBatchBar.vue` — drop remind button/prop/emit
- Modify: `resources/js/composables/useLookupSelection.ts` — `sendToBatch` DNG-only
- Modify: `resources/js/pages/Finance/Charges/Index.vue`
- Modify: `resources/js/pages/Finance/Invoices/Index.vue`
- Modify: `resources/js/pages/Finance/Payments/Index.vue`
- Modify: `resources/js/constants/finance-routes.ts`
- Modify: `resources/js/utils/routes.ts`
- Modify: `resources/js/types/finance.ts` — `BatchJobKind` without `'reminder'`
- Do not edit generated `resources/js/ziggy.js`
- Keep: `SendDueItemRemindersAction`, `SendDueItemParentRemindersAction`, `ListDueItemsQuery`, `DueCalendar.vue`, operations send-reminder APIs, `useBatchStudio`, charge/DNG wizards, `BatchPreviewTokenService`

## Implementation Steps

1. Strip reminder routes from Finance web + API route files.
2. Remove controller methods and hub `reminder` job flag.
3. Delete unique request/query/page files.
4. Remove `BatchJobType::Reminder`.
5. Hub + route helpers + `BatchJobKind`.
6. SendToBatchBar + lookup composable + three index pages: drop `canRemind` / `@reminders`.

## Todo

- [x] Backend routes and controller
- [x] Unique PHP files deleted
- [x] Hub and route helpers
- [x] Lookup bar entry points

## Success Criteria

- [x] `route('finance.batch-studio.reminders')` does not exist
- [x] Hub Inertia props have no `jobs.reminder`
- [x] No Vue import of `Reminders.vue`
- [x] Charge/DNG batch routes still named as today

## Risk Assessment

- Missed callsite of `financeRoutes.batchStudio.reminders()` → compile/Ziggy runtime error. Mitigate with grep after edits.
- Accidental deletion of shared send actions → due-calendar breaks. Do not touch Operations actions/queries.
