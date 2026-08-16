---
phase: 4
title: "Nesting, placement, and automated verification gates"
status: done
priority: P1
effort: "5h"
dependencies: [3]
---

# Phase 4: Nesting, placement, and automated verification gates

## Overview

Resolve the remaining depth and duplication defects, then replace the previous
plan's manual four-login verification with mechanical gates that run in CI. The
v1 gate was the plan's only safety net and was broken three ways; rebuilding it
properly is the bulk of this phase.

## Requirements

Functional:
- No item nests deeper than group > item > child.
- Canvas appears in exactly one location.
- The single-child `Queries` wrapper is gone.
- A checked-in test asserts the group tree, so the IA cannot silently drift.

Non-functional:
- Every gate is a command, not an eyeball check.
- Gates compare against the pinned `baseline_commit`, never `HEAD`.

## Architecture

`NavMenuItem.vue:110-140` recurses on `children`, so three levels render without
error — `Communications > Notification Management > Ops > Outbox` works today. It
is a usability defect, not a bug: it is the only 3-level path in the file, so it
breaks the pattern users learn everywhere else.

Canvas is currently split by a config-vs-operations distinction. That split does
not match ownership: **both entries already carry the same permission**,
`view_canvas_integration` (`menu-sidebar.ts:151` and `:765`); there is no
`canvas_courses` permission. Co-locating them under Course Delivery is correct
because `view_canvas_integration` is `ACA|DIR|SA` — neither role's daily home is
Administration.

**Menu permissions diverge from route-enforced permissions.** This is recorded,
not fixed here — fixing it is [#135](https://github.com/swinburne-edu/asia-admin-portal/issues/135):

| Menu entry | Menu gate | Route enforces |
|---|---|---|
| Staff Inbox | `review_form` | `can:view_queries` (`QueryController.php:31`) |
| Notification Ops | `view_notification_ops` | `view_any_notification` (`NotificationOpsController.php:40`) |
| Survey Results | `view_survey_results_aggregate` | `can:view_survey` (`Engagement/routes/web.php:78`) |

Do not claim these are coherent while promoting the items.

**Why the v1 parity gate failed, and what replaces it:**

| Defect | Fix |
|--------|-----|
| Baseline was `git show HEAD:`, which by Phase 4 is post-Phase-3 and blind to earlier drops | Compare against the pinned `baseline_commit` SHA |
| Sorted-*set* comparison is invariant under duplication, yet claimed to detect duplicates | Compare multisets (`sort`, not `sort -u`) plus an explicit `uniq -d` check |
| No command given, and `href` has two syntaxes — string literal (`'/tuition-plans'`) and call expression (`financeRoutes.collect.dngWebhookEvents()`) — so a naive literal regex silently drops the whole Finance group from both sides and passes vacuously | Extract the full `href:` right-hand side, verify the extracted count matches the `href:` occurrence count |

## Related Code Files

- Modify: `resources/js/constants/menu-sidebar.ts`
- Create: `tests/Feature/Navigation/SidebarMenuStructureTest.php`

## Implementation Steps

1. **Flatten `Communications > Notification Management > Ops`.** Promote
   `Outbox`, `Messages`, `Deliveries` to direct children of
   `Notification Management` and delete the `Ops` wrapper. All three keep
   `view_notification_ops`. Note in the commit that this permission is
   Super-Admin-only, so the change is visible to one role — the justification is
   depth consistency, not operator ergonomics.

2. **Flatten `Forms & Surveys > Queries`.** The wrapper holds one child
   (`Staff Inbox`). Promote it and delete the wrapper.

3. **Consolidate Canvas.** Move `Canvas Integrations` from
   `Administration > Integrations` into `Academic Operations > Course Delivery`,
   directly after `Canvas Courses`, retitled `Canvas Settings`.

4. **Write `SidebarMenuStructureTest`.** A checked-in Pest test reading
   `menu-sidebar.ts` and asserting the structural invariants, so they cannot
   regress silently:
   - href multiset matches a committed fixture list;
   - no duplicate href;
   - no duplicate sibling `title`, no duplicate group `label`;
   - no wrapper (`href: '#'` with `children`) carries `requiredPermissions`;
   - no nesting deeper than group > item > child;
   - every `requiredPermissions` string exists in `config/permission.php`.

   The last assertion would have caught the fabricated `canvas_courses`
   permission that justified a move in the first version of this plan.

5. **Run the parity gate** against `baseline_commit`, comparing multisets and
   checking for duplicates. Verify the extraction is not silently dropping
   call-expression hrefs by asserting the extracted line count equals the
   `href:` occurrence count on both sides.

6. **Per-persona spot check.** With the structural invariants now covered by
   test, the manual check narrows to a sanity pass: log in as Super Admin and as
   the persona most affected by the moves (HQ), on a named campus, after clearing
   the permission cache — `EloquentCampusPermissionReader.php:18,82` caches per
   user+campus for a day, so a stale cache will show a tree that was never
   rendered from the new file. Record which campus and which roles were checked;
   if a role from Phase 1's holder table is not provisioned in the environment,
   say so rather than claiming coverage.

## Success Criteria

- [ ] `href` multiset identical to `baseline_commit`; `uniq -d` empty; extracted
      count equals `href:` occurrence count on both sides
- [ ] No 3-level nesting path remains
- [ ] `Canvas Courses` and `Canvas Settings` are siblings; no Canvas entry under
      Administration
- [ ] `SidebarMenuStructureTest` exists, passes, and covers all six invariants
- [ ] Every menu `requiredPermissions` string resolves in `config/permission.php`
- [ ] Menu-vs-route permission divergences recorded in the plan (not silently
      fixed, not silently claimed coherent)
- [ ] Per-persona spot check done with campus and cache state recorded
- [ ] `./scripts/dev.sh npm run lint -- --max-warnings=0 resources/js/constants/menu-sidebar.ts` clean

## Risk Assessment

The structural test is what makes this restructure durable — without it the next
person to touch the file reintroduces the same defects and no gate objects. Write
it before running the manual check, so the manual check is a sanity pass rather
than the primary evidence.

Manual verification remains partly unachievable: three of the roles in the holder
table exist in no seeder (Phase 1, step 2). Whatever Phase 1 decided about that
determines how much of step 6 can actually be executed. Report the gap honestly
rather than marking the criterion met.

**Execution note (implementation session, 2026-08-16):** Step 6's live
per-persona spot check was not executed — this session had no browser access
with staff credentials for the target environment. Every mechanical invariant
(nesting depth, wrapper permissions, sibling/label uniqueness, href multiset vs
`baseline_commit`, permission-string resolvability against
`config/permission.php`) is covered by `SidebarMenuStructureTest` and passes.
The manual sanity pass — confirming the rendered tree matches for Super Admin
and HQ on a named campus with a cleared permission cache — is an accepted gap,
owned by whoever performs the pre-merge/pre-deploy check, not silently marked
done.
