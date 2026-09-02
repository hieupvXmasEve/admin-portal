---
title: "remove-batch-studio-reminders"
description: "Delete Batch Studio bulk debt reminders; due-calendar remains the only staff surface."
status: completed
priority: P2
effort: "2h"
tags: [finance, batch-studio, reminders]
created: 2026-09-02
blockedBy: []
blocks: []
---

# Remove Batch Studio bulk reminders

## Brainstorm contract

- **Outcome:** `/finance/batch-studio` no longer has "Nhắc nợ hàng loạt". Staff send due reminders only at `/finance/operations/due-calendar`.
- **Constraints:** Keep charges + DNG batch jobs. Keep shared send actions (`SendDueItemRemindersAction`, `SendDueItemParentRemindersAction`) and `ListDueItemsQuery`. Portal impact: none. User decision: delete SendToBatchBar remind button; old reminder routes 404 (no redirect).
- **Non-goals:** Do not change due-calendar send behavior. Do not port preview-token drift detection onto due-calendar. Do not remove `view_finance_operations_due_calendar`. Do not delete `useBatchStudio` / charge / DNG wizards.
- **Acceptance:**
  - GET/POST `finance.batch-studio.reminders*` and preview API are gone (404 / unnamed).
  - Hub has no reminder tile / `jobs.reminder`.
  - Lookup bars on Charges / Invoices / Payments have no "Nhắc nợ hàng loạt".
  - Unique reminder assembler, FormRequests, `Reminders.vue`, `BatchReminderCommitTest` deleted.
  - Due-calendar still sends student/parent reminders.
  - Charge generation and DNG batch still work.
  - docs-site finance-office pages updated in the same change as `Hub.vue` (freshness).

## Overview

Batch Studio reminders is a wizard wrapper around the same due-item send actions that Due Calendar already owns. Scout confirmed no unique send logic. Remove the wrapper and its entry points.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Delete reminder wizard, routes, unique PHP/Vue, hub tile | P1 |
| 2 | Remove lookup-bar remind button; leave DNG send | P1 |
| 3 | Update tests + staff guide; prove due-calendar and remaining batch jobs | P1 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Remove reminder surface](./phase-01-start.md) | Completed |
| 2 | [Tests and docs](./phase-02-tests-and-docs.md) | Completed |

## Success Criteria

- [x] `/finance/batch-studio/reminders` does not exist
- [x] Hub shows only charge generation and DNG
- [x] SendToBatchBar has no remind action
- [x] Due-calendar reminder send paths unchanged
- [x] Targeted PHP tests + file-scoped frontend lint pass
- [x] docs-site VI/EN/KO (and ZH if present) finance-office pages touched with Hub.vue

## Portal impact

none

<!-- slug: remove-batch-studio-reminders -->
