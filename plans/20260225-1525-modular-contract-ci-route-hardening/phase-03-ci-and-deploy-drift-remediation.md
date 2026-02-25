# Phase 03 - CI Drift Remediation

## Context links
- [Track 1 CI baseline](/Users/hunt2412/hieupvdev/project/swinx/plans/reports/2026-02-25-track-1-laravel-monolith-modular-governance.md)
- [Codebase summary CI/deploy status](/Users/hunt2412/hieupvdev/project/swinx/docs/codebase-summary.md)

## Overview (date/priority/status)
- Date: 2026-02-25
- Priority: P1
- Status: pending (0%)

## Key Insights
- GitHub workflows exist but are currently commented out.
- Script paths drift from actual docker file locations (`docker/` vs repo root).

## Requirements
- Re-enable minimal required CI checks on protected branch.
- Remove compose-path drift and missing-script references.
- Exclude deploy validation checks from this phase scope.
- Exclude secret rotation/remediation from this phase scope (handled separately).

## Architecture
- CI minimum gates: build/test, static analysis, style, contract/type checks.
- This phase is CI-focused; no deploy gate or deploy dry-run checks.
- Required checks matrix:
  - `dev` warning mode: `route-diff`, `inertia-contract`, `vue-tsc`, `lint`, `tests`.
  - `dev` blocking mode after burn-in.

## Related code files
- [.github/workflows/deploy.yml](/Users/hunt2412/hieupvdev/project/swinx/.github/workflows/deploy.yml)
- [.github/workflows/lint.yml](/Users/hunt2412/hieupvdev/project/swinx/.github/workflows/lint.yml)
- [.github/workflows/tests.yml](/Users/hunt2412/hieupvdev/project/swinx/.github/workflows/tests.yml)
- [docker/docker-compose.production.yml](/Users/hunt2412/hieupvdev/project/swinx/docker/docker-compose.production.yml)
- [docker/docker-compose.dev.yml](/Users/hunt2412/hieupvdev/project/swinx/docker/docker-compose.dev.yml)

## Implementation Steps
1. Validate entry criteria before blocking gates:
   - Phase 02 contract schema exists for agreed top routes/pages.
   - Phase 04 route/middleware matrix baseline exists.
2. Normalize compose path references used by CI scripts to `docker/docker-compose.*.yml`.
3. Re-enable workflows with minimal gates and cache optimization.
4. Configure advisory checks on `dev`, then promote to required checks on `dev` after burn-in.
5. Remove deploy-check steps from CI workflows in this phase.

## Todo list
- [ ] Compose path drift removed from scripts.
- [ ] CI workflows uncommented and validated.
- [ ] Advisory checks active on `dev`.
- [ ] Required checks configured on `dev` after burn-in.
- [ ] Deploy-check steps removed from CI workflows.

## Success Criteria
- CI runs on every PR targeting `dev` and blocks red builds after burn-in.
- No script references missing root compose files.

## Risk Assessment
- Risk: CI re-enable increases lead time initially.
- Mitigation: stage gates, baseline debt, optimize runner cache.

## Security Considerations
- Secret rotation and credential cleanup are out of this phase scope and tracked separately.

## Next steps
- Publish CI runbook for `dev` branch gate operation.

## Unresolved questions
1. Are signed commits and merge queue required now or in a second phase?
