# brainstorm: evaluate data table composable

## Goal

Evaluate `openspec/changes/use-data-table-composable` before implementation, focusing on fit with current Swinx frontend patterns, implementation risk, MVP scope, and recommended next steps.

## What I Already Know

* OpenSpec change is syntactically valid via `openspec validate use-data-table-composable --strict`.
* Current frontend has legacy `useInertiaFilters` and newer `useServerTableQuery`.
* `useServerTableQuery` is a thin explicit-control wrapper over `useInertiaFilters` with `autoSync: false`.
* Current docs already say new list pages should use `useServerTableQuery`.
* Actual counts from `resources/js`: `useInertiaFilters` appears in 29 files, `useServerTableQuery` in 10 files, `ServerPaginatedDataTable` in 9 files.
* Proposal says 84 affected files and includes plugin architecture, validation, dependencies, performance, realtime, migration scripts, analytics, virtual scrolling.

## Assumptions

* User wants feature evaluation, not implementation yet.
* Primary concern is whether the OpenSpec change should proceed as written.
* Repo should prefer YAGNI/KISS and evolve existing frontend standards instead of introducing large unused abstractions.

## Requirements

* Assess whether the proposed `useDataTable` is valuable.
* Identify scope and architecture risks.
* Recommend a smaller MVP if current scope is too large.
* Preserve compatibility with existing Laravel/Inertia contracts and current UI components.

## Acceptance Criteria

* [x] README and project rules inspected.
* [x] OpenSpec proposal/design/tasks/specs inspected.
* [x] Current composables and reference list-page implementation inspected.
* [x] Validation result captured.
* [x] Recommendation produced with risks and next steps.

## Definition of Done

* Evaluation summarizes changed files inspected and validation result.
* Unresolved questions listed at end.
* No implementation started.

## Out of Scope

* Implementing `useDataTable`.
* Migrating existing pages.
* Updating OpenSpec files in this pass.
* Running frontend typecheck/tests, because this is review only.

## Technical Notes

* Relevant OpenSpec files:
  * `openspec/changes/use-data-table-composable/proposal.md`
  * `openspec/changes/use-data-table-composable/design.md`
  * `openspec/changes/use-data-table-composable/tasks.md`
  * `openspec/changes/use-data-table-composable/specs/*/spec.md`
* Relevant current files:
  * `resources/js/composables/useInertiaFilters.ts`
  * `resources/js/composables/useServerTableQuery.ts`
  * `resources/js/components/tables/ServerPaginatedDataTable.vue`
  * `resources/js/pages/Admin/Notifications/Ops/Messages.vue`
  * `docs/rules/frontend.md`
  * `docs/rules/filtering.md`
  * `docs/rules/reference-implementations.md`

## Evaluation Summary

The feature direction is valid: table/filter state should be unified behind one explicit-control API. However, the current OpenSpec is too broad for an MVP. It mixes a useful composable cleanup with speculative plugin architecture, realtime updates, analytics, virtual scrolling, migration automation, performance monitoring, and async validation. That violates the repo's KISS/YAGNI preference and increases migration risk.

Recommended direction: revise to an incremental `useServerTableQuery` v2 or `useDataTable` core-only change that extracts the shared navigation/filter state from `useInertiaFilters`, keeps the current manual-control API, and migrates only 1-2 reference pages first.

## Recommended MVP

* Core explicit filter state and Inertia navigation.
* Clean URL builder with defaults/empty values.
* Typed helpers: `setFilter`, `apply`, `applySearch`, `clearFilters`, sort/page/page-size handlers.
* Basic loading/error state from Inertia callbacks.
* Compatibility adapter or unchanged facade for `useServerTableQuery`.
* Tests around URL generation, defaults, page reset, debounce, and route partial reload options.

## Defer

* Plugin ecosystem.
* WebSocket/realtime table updates.
* Virtual scrolling.
* Analytics plugin.
* Automated migration scripts.
* Async validation and server-side filter-option loading.

## Open Questions

* Should the new public API be named `useDataTable` or should `useServerTableQuery` be evolved in place?
* Which 1-2 pages should be first migration references?
* Should legacy `useInertiaFilters` remain only for auto-sync pages, or be fully deprecated later?
