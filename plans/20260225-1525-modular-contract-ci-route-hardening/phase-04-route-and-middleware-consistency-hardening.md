# Phase 04 - Route and Middleware Consistency Hardening

## Context links
- [Track 2 route contract recommendations](/Users/hunt2412/hieupvdev/project/swinx/plans/reports/2026-02-25-track-2-vue3-inertia-ts-contract-stability-report.md)
- [Frontend scout route/middleware touchpoints](/Users/hunt2412/hieupvdev/project/swinx/plans/reports/2026-02-25-frontend-architecture-scout.md)
- [Project overview unresolved middleware question](/Users/hunt2412/hieupvdev/project/swinx/docs/project-overview-pdr.md)

## Overview (date/priority/status)
- Date: 2026-02-25
- Priority: P2
- Status: pending (0%)

## Key Insights
- Route usage is fragmented between named routes and hardcoded literals.
- Middleware composition differs across admin/internal APIs; some groups use `web` coupling.
- Route changes lack explicit diff governance at PR boundary.

## Requirements
- Introduce route/middleware contract matrix for critical surfaces.
- Enforce route list JSON diff on PRs.
- Standardize middleware policies for web, API, and broadcast auth boundaries.
- Add intentional-break workflow for approved contract changes.

## Architecture
- Stable contract key: route name + middleware chain + response contract.
- Separate concerns: Inertia web routes vs API routes vs admin/internal API groups.
- Policy: breaking change requires version bump/new route + deprecation window.
- Migration-safety rule: additive-first changes, compatibility window, rollback criteria.

## Related code files
- [routes/web.php](/Users/hunt2412/hieupvdev/project/swinx/routes/web.php)
- [routes/api.php](/Users/hunt2412/hieupvdev/project/swinx/routes/api.php)
- [routes/api/admin.php](/Users/hunt2412/hieupvdev/project/swinx/routes/api/admin.php)
- [routes/api/v1/student.php](/Users/hunt2412/hieupvdev/project/swinx/routes/api/v1/student.php)
- [routes/api/v1/lecturer.php](/Users/hunt2412/hieupvdev/project/swinx/routes/api/v1/lecturer.php)
- [routes/channels.php](/Users/hunt2412/hieupvdev/project/swinx/routes/channels.php)
- [resources/js/utils/routes.ts](/Users/hunt2412/hieupvdev/project/swinx/resources/js/utils/routes.ts)
- [app/Http/Middleware/HandleInertiaRequests.php](/Users/hunt2412/hieupvdev/project/swinx/app/Http/Middleware/HandleInertiaRequests.php)

## Implementation Steps
1. Generate current route/middleware matrix from `route:list --json` and curate top critical routes.
2. Add CI diff gate for route method/name/path/domain/middleware deltas (`route-diff`, owner: BE lead).
3. Replace high-risk hardcoded frontend paths with named-route helpers.
4. Resolve admin/internal API middleware policy (`web` coupling vs strict token path).
5. Add intentional-break workflow:
   - `contract-break-approved` label,
   - required approvers (FE lead + BE lead + architecture owner),
   - changelog + deprecation note required.
6. Add deprecation policy and compatibility window to contract docs.

## Todo list
- [ ] Route/middleware matrix created.
- [ ] CI route-diff gate enabled.
- [ ] Top hardcoded route literals replaced.
- [ ] Middleware policy decision approved.
- [ ] Deprecation policy documented.
- [ ] Intentional-break override workflow documented.

## Success Criteria
- Route and middleware changes are intentional and reviewable.
- Critical frontend flows avoid hardcoded API/admin path literals.
- Middleware strategy is consistent across API surfaces.

## Risk Assessment
- Risk: route normalization introduces regressions in legacy flows.
- Mitigation: contract tests + smoke E2E for top 3 journeys.

## Security Considerations
- Verify auth middleware is explicit per route group; avoid accidental public exposure.
- Confirm broadcasting auth route keeps intended Sanctum/actor protections.

## Next steps
- Prioritize student + lecturer API route groups first.
- Track consistency metrics (hardcoded literal count, route diff exceptions).

## Unresolved questions
1. Should admin/internal APIs keep `web` middleware dependencies or move to token-only model?
2. What deprecation window is acceptable for route/middleware contract breaks?
