---
title: "Modular Governance + Contract Stability Improvement"
description: "Phased hardening plan for module boundaries, frontend/backend contracts, CI drift, and route/middleware consistency."
status: pending
priority: P1
effort: 9w
branch: dev
tags: [planning, laravel, vue3, inertia, ci, governance]
created: 2026-02-25
---

# Improvement Plan

## Scope
Synthesize completed research/scout outputs into one delivery plan: modular monolith governance, contract stability, frontend/backend hotspot reduction, CI drift cleanup, and route/middleware consistency.

## Context Links
- [Track 1](/Users/hunt2412/hieupvdev/project/swinx/plans/reports/2026-02-25-track-1-laravel-monolith-modular-governance.md)
- [Track 2](/Users/hunt2412/hieupvdev/project/swinx/plans/reports/2026-02-25-track-2-vue3-inertia-ts-contract-stability-report.md)
- [Frontend Scout](/Users/hunt2412/hieupvdev/project/swinx/plans/reports/2026-02-25-frontend-architecture-scout.md)
- [Codebase Summary](/Users/hunt2412/hieupvdev/project/swinx/docs/codebase-summary.md)
- [Deployment Guide](/Users/hunt2412/hieupvdev/project/swinx/docs/deployment-guide.md)

## Phase Status / Progress
| Phase | Focus | Status | Progress | Link |
|---|---|---|---:|---|
| 01 | Modular governance + boundaries | pending | 0% | [phase-01](/Users/hunt2412/hieupvdev/project/swinx/plans/20260225-1525-modular-contract-ci-route-hardening/phase-01-modular-governance-and-boundary-rules.md) |
| 02 | Contract stability + frontend hotspots | pending | 0% | [phase-02](/Users/hunt2412/hieupvdev/project/swinx/plans/20260225-1525-modular-contract-ci-route-hardening/phase-02-contract-stability-and-frontend-hotspot-reduction.md) |
| 03 | CI drift remediation | pending | 0% | [phase-03](/Users/hunt2412/hieupvdev/project/swinx/plans/20260225-1525-modular-contract-ci-route-hardening/phase-03-ci-and-deploy-drift-remediation.md) |
| 04 | Route/middleware consistency | pending | 0% | [phase-04](/Users/hunt2412/hieupvdev/project/swinx/plans/20260225-1525-modular-contract-ci-route-hardening/phase-04-route-and-middleware-consistency-hardening.md) |

## Delivery Principles
- YAGNI: no microservice split, no large rewrite.
- KISS: incremental policy + checks at merge gate.
- DRY: single contract manifest per critical route/page.

## Milestones
- Week 2: boundary policy + PR checklist ratified.
- Week 4: route diff + type-check + Inertia contract tests in CI.
- Week 6: CI workflows active on `dev`; script path drift reduced.
- Week 9: route/middleware matrix stable; high-risk inconsistencies closed.

## Execution Order (must-follow)
1. Phase 01 policy + owner map + exception workflow.
2. Phase 02 contract schema + contract test scaffolding.
3. Phase 04 route/middleware matrix baseline.
4. Phase 03 enable blocking CI gates tied to artifacts from phases 02/04.

## Branch and Gate Strategy
- `dev`: advisory checks for 2 sprints (`route-diff`, `inertia-contract`, `vue-tsc`), then promote to required checks on `dev`.
- No required-check rollout to `main` in this plan scope.
- Workflow trigger branches and protected-branch checks must stay aligned to avoid zero coverage.

## Dependencies
- Architecture owner for module/shared admission decisions.
- DevOps owner for workflow re-enable and CI branch protection on `dev`.
- FE/BE leads for contract manifest ownership.

## Unresolved Questions
1. Canonical source of truth for contracts: `docs/contracts` manual vs generated from tests/route metadata?
2. CI latency target for protected branch checks (example <= 10m)?
3. Deprecation window for breaking route/prop contracts (30/60 days)?
4. Should admin/internal APIs migrate off `web` middleware coupling now or phase later?
