# Phase 03 - Validation and Rollout

## Context Links

- `resources/js/pages/Admin/Reports/StudentDecisions/Index.vue`
- `resources/js/components/tables/ServerPaginatedDataTable.vue`
- `resources/js/composables/useServerTableQuery.ts`

## Overview

- Priority: P2
- Status: pending
- Description: validate correctness, typing, and build health; ship with low regression risk.

## Key Insights

- Refactor touches shared components; failures can spill into unrelated pages.
- Fastest confidence path is type-check + build + focused feature tests.

## Requirements

- Functional: confirm filtering/sorting/pagination works on StudentDecisions index.
- Non-functional: ensure TS compile passes and production build passes.

## Architecture

- Validation layers: static checks -> unit/feature tests -> manual browser sanity.

## Related Code Files

- Update as needed from earlier phases only; no new scope.

## Implementation Steps

1. Run frontend static checks: `npm run type-check` then `npm run build`.
2. Run backend tests for touched domain first: `php artisan test --filter=StudentDecision`.
3. Run broader test subset if available for Academic reports.
4. Manual sanity in browser: apply filters, sort columns, page navigation, per-page change, open create/edit dialog.
5. Capture regressions and patch in smallest possible change set.

## Todo List

- [ ] Typecheck passes.
- [ ] Build passes.
- [ ] StudentDecision tests pass.
- [ ] Manual page workflow validated.

## Success Criteria

- Commands green: `npm run type-check`, `npm run build`, `php artisan test --filter=StudentDecision`.
- StudentDecisions index behavior matches pre-refactor plus new server-sort capability.

## Risk Assessment

- Risk: hidden dependency on old `decisions.links` markup.
- Mitigation: keep pagination data contract unchanged and rely on `DataPagination` inputs.

## Security Considerations

- Re-verify no unvalidated query params are fed into sorting on backend.

## Next Steps

- Optional: migrate other report pages to the same shared blocks incrementally.
