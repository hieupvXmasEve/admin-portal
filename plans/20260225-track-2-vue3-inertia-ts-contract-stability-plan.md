# Plan: Track 2 - Vue3 + Inertia + TypeScript Scaling + Contract Stability

## Goal
Scale frontend safely while keeping Laravel route/API/Inertia contracts stable during iterative delivery.

## Inputs
- Research report: [`2026-02-25-track-2-vue3-inertia-ts-contract-stability-report.md`](/Users/hunt2412/hieupvdev/project/swinx/plans/reports/2026-02-25-track-2-vue3-inertia-ts-contract-stability-report.md)

## Phase 1: Contract Inventory (1-2 days)
1. Inventory critical routes (web Inertia + API) and assign owner module.
2. Define canonical contract sheet per route:
- route name/path/middleware
- response type (`PageProps` or API DTO)
- compatibility rule (optional-only additions)
3. Store in `docs/` as source of truth.

Exit criteria:
- Top 10 critical routes documented and reviewed.

## Phase 2: Test Harness (1-2 days)
1. Add backend contract tests for top routes.
2. Add Inertia assertions for component + required props.
3. Add frontend type gate (`vue-tsc --noEmit`).

Exit criteria:
- CI fails on contract drift for covered routes.

## Phase 3: CI Contract Gates (0.5-1 day)
1. Add route diff gate using `php artisan route:list --json` artifact.
2. Add required checks: PHP tests, Inertia tests, TS type-check.
3. Publish failure guide for devs.

Exit criteria:
- PR cannot merge with unapproved route/contract drift.

## Phase 4: Migration Safety Standard (1 day)
1. Add migration RFC checklist: expand -> backfill -> switch -> cleanup.
2. Enforce rollback notes in migration PRs.
3. Add ephemeral DB migration validation in CI.

Exit criteria:
- Schema changes touching contracts follow same safe template.

## Phase 5: Progressive Coverage (ongoing)
1. Expand contract tests route-by-route.
2. Add small E2E smoke for 3 critical journeys.
3. Track contract incidents and MTTR.

Exit criteria:
- 80%+ critical route coverage; no silent contract breaks in production.

## Risks
- Hidden implicit contracts in legacy responses.
- Route naming inconsistencies across modules.
- Time cost of retrofitting tests on unstable pages.

## Mitigations
- Prioritize only critical flows first (YAGNI).
- Freeze naming for new work; migrate old names incrementally.
- Require contract owner per module.

## Success Metrics
- Contract regression count per release = 0 (target).
- Mean detection time < PR cycle (not post-release).
- Migration rollback success validated in non-prod.

## Open Questions
1. Contract doc format: markdown tables vs JSON schema files?
2. Who approves breaking contract requests?
3. Which CI stage owns route diff baseline updates?
