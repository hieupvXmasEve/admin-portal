# Phase 01 - Modular Governance and Boundary Rules

## Context links
- [Track 1 governance research](/Users/hunt2412/hieupvdev/project/swinx/plans/reports/2026-02-25-track-1-laravel-monolith-modular-governance.md)
- [Codebase summary hotspot signal](/Users/hunt2412/hieupvdev/project/swinx/docs/codebase-summary.md)

## Overview (date/priority/status)
- Date: 2026-02-25
- Priority: P1
- Status: pending (0%)

## Key Insights
- Monolith-first is correct now; service split is premature.
- Shared layer is oversized (`app/Services`), so admission rules must be explicit.
- Boundary drift starts with cross-module internal calls, not with route files.

## Requirements
- Define module-vs-shared admission checklist.
- Define allowed layer direction and PR review checks.
- Add minimal architecture decision record for exceptions.
- Define enforcement operations before any blocking check (owner map, SLA, suppression format).

## Architecture
- Keep deploy unit single Laravel app.
- Module owns routes/controllers/policies/migrations/tests/internal services.
- Shared services only for proven cross-cutting utilities (>=3 modules, stable API).

## Related code files
- [app/Modules](/Users/hunt2412/hieupvdev/project/swinx/app/Modules)
- [app/Services](/Users/hunt2412/hieupvdev/project/swinx/app/Services)
- [app/Http/Controllers/Web](/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/Web)
- [docs/system-architecture.md](/Users/hunt2412/hieupvdev/project/swinx/docs/system-architecture.md)

## Implementation Steps
1. Publish one-page boundary policy in `docs/` with strict admission checklist.
2. Add PR template checks: cross-module imports, shared-service justification, owner sign-off.
3. Add static boundary scan with explicit allowlist file and rule IDs as non-blocking check first.
4. Assign enforcement RACI: Owner (architecture lead), Approver (domain lead), Consulted (security/platform).
5. Define approval SLA and exception expiry (example 14 days).
6. Promote to blocking after two stable sprints and <5% false-positive rate.

## Todo list
- [ ] Finalize module/shared admission checklist.
- [ ] Map core modules and owners.
- [ ] Add PR checklist questions.
- [ ] Add non-blocking boundary scan job.
- [ ] Add exception log format and SLA rules.

## Success Criteria
- New domain logic lands in module by default.
- Shared-service additions include checklist evidence.
- Cross-module internal dependency violations trend down sprint-over-sprint.

## Risk Assessment
- Risk: policy too strict blocks delivery.
- Mitigation: start non-blocking, ratchet by violation trend.

## Security Considerations
- Restrict direct infra calls from controller layer for sensitive workflows.
- Ensure architecture exceptions are auditable (decision log + approver).

## Next steps
- Start with top 2 volatile domains (`students`, `Admin`) to prove policy.
- Feed boundary violations into weekly architecture review.
- Publish suppression review cadence (weekly) to prevent permanent waivers.

## Unresolved questions
1. Which modules are core system-of-record vs supporting workflows?
2. Who has final approval authority for shared-layer exceptions?
