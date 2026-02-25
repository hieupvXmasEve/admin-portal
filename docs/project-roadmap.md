# Project Roadmap

Last updated: 2026-02-25  
Owner: Platform Team  
Status: Active planning baseline  
Horizon: next 2-3 quarters

## 1) Objectives

- Stabilize API security and auth consistency.
- Restore delivery confidence (CI + deployment path normalization).
- Reduce contract drift between backend routes and frontend integrations.
- Keep documentation synchronized with code changes.

## 2) Current Baseline

- Platform is operational with hybrid architecture (`app/Modules/*` + `app/Services/*`).
- Auth hardening landed for student/parent/lecturer actor middleware and protected refresh routes.
- Token TTL is aligned to 8 hours across current Identity login/refresh actions.
- Critical unresolved risks remain:
  - public `/api/system-config*`
  - finance API auth model drift
  - disabled CI workflows
  - deployment/script/security drift

## 3) Phase Plan

### Phase 0: Core Documentation Alignment

Status: Completed  
Completed date: 2026-02-25

Outcome:
- Core docs aligned to current code-verified state.
- Removed aspirational phrasing from quality/security posture.

### Phase 1: Deploy/Script Normalization

Status: Planned  
Target: 1-2 sprints

Scope:
- choose one canonical deploy entrypoint
- align script references to actual `docker/*` layout
- remove references to missing helper scripts

Exit criteria:
- no broken script references in normal dev/deploy paths
- deployment guide maps exactly to runnable script flow

### Phase 2: CI Reactivation

Status: Planned  
Target: after Phase 1

Scope:
- uncomment and modernize `.github/workflows/*`
- enforce minimum checks on PRs

Minimum gate target:
- lint
- type-check
- backend tests

Exit criteria:
- required checks run automatically and block on failure

### Phase 3: API Security Hardening

Status: Planned  
Target: overlaps with Phase 2 when feasible

Scope:
- secure or relocate public `system-config` routes
- resolve finance API auth drift (`web` + `auth` vs Sanctum actor model)
- document stable auth matrix by route group

Exit criteria:
- no high-risk public config mutation/read routes without explicit approval rationale
- finance API auth model decision implemented and documented

### Phase 4: Frontend Contract Stabilization

Status: Planned  
Target: ongoing

Scope:
- reduce literal URL pockets in frontend pages/composables
- standardize route helper usage and API wrapper usage in high-churn pages

Exit criteria:
- measurable reduction in literal path usage on critical flows
- route contract changes require single-source updates

### Phase 5: Architecture Consolidation

Status: Planned  
Target: ongoing

Scope:
- define explicit policy for `Modules` vs `Services`
- apply policy incrementally on touched hotspots

Exit criteria:
- reduced placement ambiguity for new domain logic
- documented ownership by domain/module

## 4) Dependencies

- Phase 1 before Phase 2
- Phase 2 before strict merge-gate enforcement policy
- Phase 3 and Phase 4 can overlap after baseline CI is active
- Documentation updates are required in every phase

## 5) Tracking Metrics

- CI status: active/inactive + pass rate
- Script drift count: missing scripts/path mismatches
- API risk count: unresolved high-risk auth exposures
- Frontend contract drift: literal path usage in high-risk areas
- Docs hygiene: stale claim/link count in core docs

## 6) Immediate Decisions

1. Canonical deploy script and environment contract.
2. Minimum required CI gates for merge.
3. Final auth model for finance API routes.
4. Ownership and timeline for public system-config hardening.

## Unresolved Questions

- Who is accountable for Phase 1 execution window and sign-off?
- Which API endpoints are external-facing contracts vs internal web support APIs?
- What is the acceptable grace period between code change and required doc update?
