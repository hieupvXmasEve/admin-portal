---
title: 'Refactor Student Decisions Show to Shared Server Table Contract'
description: 'Migrate linked-actions table on StudentDecisions Show page to shared query/pagination flow without breaking existing route/query behavior.'
status: pending
priority: P2
effort: 3h
branch: dev
tags: [inertia, vue3, laravel12, table, pagination, compatibility]
created: 2026-02-27
---

# Plan Overview

- Scope: `resources/js/pages/Admin/Reports/StudentDecisions/Show.vue` linked-actions table/filter/pagination only.
- Keep compatible behavior: same route, same query keys (`linked_per_page`, `linked_page`), same row actions, same date rendering.
- Reuse shared pieces where practical; avoid broad migration outside touched flow.

## File-Level Steps

1. Update `resources/js/composables/useServerTableQuery.ts`

    - Add optional config for custom query keys (ex: `pageKey`, `perPageKey`) while preserving current defaults (`page`, `per_page`).
    - Keep search debounce behavior unchanged; page/per-page/sort remain immediate.
    - Ensure reset-to-first-page still works with custom page key.

2. Update `resources/js/components/DataPagination.vue`

    - Remove implicit URL construction from `window.location` for page number buttons.
    - Emit page number directly (`page-change`) and keep `navigate` for prev/next link URLs.
    - Keep visual/UI behavior unchanged.

3. Update `resources/js/components/tables/ServerPaginatedDataTable.vue`

    - Add optional `pageParam` prop (default `page`) for URL parsing from pagination links.
    - Listen to direct page-number event from `DataPagination` and emit unified `page-change`.
    - Preserve existing API behavior for current consumers.

4. Refactor `resources/js/pages/Admin/Reports/StudentDecisions/Show.vue`

    - Replace local `router.get` + manual `<table>` + manual `<Link v-for="linkedActions.links">` glue with shared table wrapper.
    - Use `useServerTableQuery` with custom keys (`pageKey: 'linked_page'`, `perPageKey: 'linked_per_page'`) and base URL `studentRoutes.studentDecisionsShow(decision.id)`.
    - Keep existing columns/labels/actions and preserve `studentRoutes.studentStatusActionShow(row.id)`.
    - Keep current props contract (`linkedActions`, `filters.linked_per_page`) and preserve default per-page = 10.

5. Update `app/Modules/Academic/Http/Web/Admin/StudentDecisionController.php`

    - Keep validating `linked_per_page`.
    - Add compatibility read for page input so both `linked_page` (canonical current) and optional `page` fallback resolve correctly if shared components send either during transition.
    - Keep Inertia payload shape unchanged for `decision`, `linkedActions`, `filters.linked_per_page`.

6. Optional touch `app/Modules/Academic/Queries/GetStudentDecisionDetailQuery.php` (only if needed)
    - Keep paginator page name as `linked_page` to preserve URL compatibility.
    - No query semantics change.

## Validation Steps

- Type/build checks: run `npm run type-check` and `npm run build`.
- Manual URL compatibility checks on Show page:
    - `?linked_per_page=25` loads 25 rows per page.
    - Pagination changes `linked_page`, not a breaking new key.
    - Existing bookmarked URL with `linked_page` still works.
- Behavior regression checks:
    - Row "View Action" link still opens same detail route.
    - Decision header metadata and badges unchanged.
    - Browser back/forward keeps query state.

## Risks and Mitigation

- Risk: shared pagination emits `page` and breaks linked list page key.
    - Mitigation: explicit `pageParam` + custom key support + controller fallback mapping.
- Risk: broad changes impact other list pages.
    - Mitigation: keep defaults backward compatible; no required page edits outside target file.

## Unresolved Questions

- None.
