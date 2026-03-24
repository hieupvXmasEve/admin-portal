---
title: "Remove Voucher Import and Delete Feature Entry Points"
description: "Remove voucher import and voucher delete code paths from voucher list/detail and clean the dead backend entrypoints."
status: completed
priority: P1
effort: 2h
branch: dev
tags: [cleanup, vouchers, frontend, backend]
blockedBy: []
blocks: []
created: 2026-03-24
---

# Voucher Import/Delete Removal Plan

## Overview
Remove the voucher import feature and voucher delete feature from the voucher module where they surface today: the voucher index page and voucher detail page. Keep voucher create/edit/show/apply intact.

## Scope
In scope:
- Remove import button from voucher list page.
- Remove delete action from voucher detail page.
- Remove voucher import routes, controller actions, request, service, and page.
- Remove voucher delete route/controller/service entrypoints if no longer referenced.

Out of scope:
- Voucher create/edit/apply flow.
- Permission model redesign outside dead-code cleanup.
- Legacy data migration.

## Current-State Findings
- `resources/js/pages/Vouchers/Index.vue` exposes `Import Vouchers`.
- `resources/js/pages/Vouchers/Show.vue` exposes delete confirmation + delete submit logic.
- `routes/web/vouchers.php` still exposes import and destroy routes.
- `VoucherController` still owns import and destroy actions.
- `VoucherImportRequest`, `VoucherImportService`, and `resources/js/pages/Vouchers/Import.vue` are voucher-import-only and have no other callers.
- `VoucherService::deleteVoucher()` is only used by `VoucherController::destroy()`.

## Execution Steps
1. Remove list/detail UI entrypoints for import/delete.
2. Remove backend route/controller/service/request/page code that becomes unreachable.
3. Verify no remaining references to `vouchers.import` / `vouchers.destroy` and run focused frontend checks.

## Implementation Update
- Voucher index no longer exposes `Import Vouchers`.
- Voucher detail no longer exposes delete UI or delete submit logic.
- Voucher import routes, controller methods, request, service, and Inertia page were removed.
- Voucher destroy route/controller/service entrypoints were removed, and `delete_voucher` was dropped from permission config.
- Focused verification passed for voucher UI files and route-reference scans.

## Unresolved Questions
- None. Scope assumes delete/import should be removed from the voucher module entirely, not only hidden in UI.
