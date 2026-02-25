# Phase 02 - Contract Stability and Frontend Hotspot Reduction

## Context links
- [Track 2 contract stability report](/Users/hunt2412/hieupvdev/project/swinx/plans/reports/2026-02-25-track-2-vue3-inertia-ts-contract-stability-report.md)
- [Frontend architecture scout](/Users/hunt2412/hieupvdev/project/swinx/plans/reports/2026-02-25-frontend-architecture-scout.md)

## Overview (date/priority/status)
- Date: 2026-02-25
- Priority: P1
- Status: pending (0%)

## Key Insights
- Inertia `component + props` shape is a hard contract; prop drift breaks runtime.
- Route names are more stable than URLs and should be the default frontend integration key.
- Hotspots are concentrated in a few oversized pages/composables and duplicated filter/permission utilities.

## Requirements
- Define contract manifest for top 10 critical page/API routes.
- Add TS and Inertia contract checks as first-class merge gates.
- Reduce duplicate filter/permission/network abstractions with one canonical path each.
- Add migration-safety checklist for any contract-breaking change.

## Architecture
- Contract-first model: each route/page has owner, middleware, response shape, deprecation policy.
- Frontend contract types live close to page boundaries; shared models only for truly common DTOs.
- API client normalization via one wrapper (`useApiRequest`) and one response envelope policy.
- Prioritization rubric for “top 10”: traffic, incident history, business criticality, change frequency.

## Related code files
- [resources/js/app.ts](/Users/hunt2412/hieupvdev/project/swinx/resources/js/app.ts)
- [resources/js/ssr.ts](/Users/hunt2412/hieupvdev/project/swinx/resources/js/ssr.ts)
- [resources/js/types/models.ts](/Users/hunt2412/hieupvdev/project/swinx/resources/js/types/models.ts)
- [resources/js/composables/useFilters.ts](/Users/hunt2412/hieupvdev/project/swinx/resources/js/composables/useFilters.ts)
- [resources/js/composables/useInertiaFilters.ts](/Users/hunt2412/hieupvdev/project/swinx/resources/js/composables/useInertiaFilters.ts)
- [resources/js/composables/usePermission.ts](/Users/hunt2412/hieupvdev/project/swinx/resources/js/composables/usePermission.ts)
- [resources/js/composables/usePermissions.ts](/Users/hunt2412/hieupvdev/project/swinx/resources/js/composables/usePermissions.ts)
- [app/Http/Middleware/HandleInertiaRequests.php](/Users/hunt2412/hieupvdev/project/swinx/app/Http/Middleware/HandleInertiaRequests.php)

## Implementation Steps
1. Create `docs/contracts/` manifest for top 10 critical contracts using the prioritization rubric.
2. Define gate spec and owners:
   - `inertia-contract` (owner: BE lead)
   - `vue-tsc` (owner: FE lead)
   - `phpunit-contract` (owner: BE lead)
3. Add backend Inertia endpoint assertions: component name + required props.
4. Add `vue-tsc --noEmit` and contract checks as advisory (non-blocking) checks on `dev`; promote to required checks on `dev` after burn-in.
5. Choose canonical filter and permission composables; deprecate duplicates.
6. Split work into increments:
   - 02A: contract/gates
   - 02B: hotspot page refactors after 02A is stable.
7. Add migration-safety workflow: expand -> dual-read/write if needed -> cutover -> cleanup with rollback gate.

## Todo list
- [ ] Contract manifest template approved.
- [ ] Top 10 contracts documented.
- [ ] Contract tests scaffolded.
- [ ] Duplicate composable decision made.
- [ ] First hotspot page split merged.
- [ ] Owners/approvers recorded for each contract.
- [ ] Migration checklist template added to PR template.

## Success Criteria
- Contract changes are explicit, versioned, and reviewed.
- Type-check catches prop drift before runtime.
- Duplicated abstractions reduced to one canonical implementation each.

## Risk Assessment
- Risk: refactor churn across large pages.
- Mitigation: phase by domain and preserve behavior with contract tests first.

## Security Considerations
- Ensure all normalized API calls preserve CSRF/session behavior and auth headers.
- Validate that contract docs include auth/middleware expectations per route.

## Next steps
- Start with `student-applications`, `course-offerings`, and `Admin/Canvas` flows.
- Publish contract-change policy in PR checklist.

## Unresolved questions
1. Should contract source-of-truth be docs-only or generated from tests/route metadata?
2. Is eager page loading in `app.ts` intentional or ready for phased lazy loading?
