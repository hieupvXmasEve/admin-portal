# Phased Migration Plan

Use phased rollout to avoid regressions in high-traffic index pages.

## Phase 0: Baseline

- Capture current behavior for top pages (search/sort/paginate).
- Snapshot current query param contracts.
- Define fallback/rollback path.

## Phase 1: Core Contract

- Finalize shared composable API.
- Finalize `DataTable` and `DataPagination` event contracts.
- Add adapter layer if old pages still emit legacy events.

## Phase 2: Low-Risk Pages

- Migrate pages with minimal custom logic.
- Verify URL parity and user-visible behavior.
- Fix issues before expanding rollout.

## Phase 3: Medium-Risk Pages

- Migrate pages with custom filter transforms.
- Keep backend key compatibility.
- Add targeted tests for transformed filters.

## Phase 4: High-Risk Pages

- Migrate pages with custom data flow (exports, bulk actions, tabs).
- Move one concern at a time (pagination first, then sort, then filters).
- Add explicit regression checklist per page.

## Phase 5: Cleanup

- Remove dead helpers and page-local duplicate glue.
- Remove temporary adapters after adoption window.
- Update docs and examples.

## Rollback Strategy

- Keep migration per-page or per-module PR.
- Avoid cross-module atomic changes.
- If behavior drifts in prod, revert one batch not whole migration.

## Validation Gate Per Phase

- Query contract parity check
- Feature tests green
- Manual smoke check (search -> sort -> paginate)
- No error spikes in logs
