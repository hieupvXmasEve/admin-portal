# Project Roadmap

Last updated: 2026-03-26  
Owner: Platform Team  
Status: Active execution baseline  
Horizon: next 2-3 quarters

## 1) Objectives

- Stabilize API security and auth consistency.
- Restore delivery confidence (CI + deployment path normalization).
- Reduce contract drift between backend routes and frontend integrations.
- Keep documentation synchronized with code changes.

## 2) Current Baseline

- Platform is operational with hybrid architecture (`app/Modules/*` + `app/Services/*`).
- Frontend filter stack remains hybrid (`useInertiaFilters`, legacy `useFilters`/`useTableFilters`, and `useServerTableQuery`).
- Auth hardening landed for student/parent/lecturer actor middleware and protected refresh routes.
- Token TTL is aligned to 8 hours across current Identity login/refresh actions.
- Critical unresolved risks remain:
    - public `/api/system-config*`
    - finance API auth model drift
    - disabled CI workflows
    - deployment/script/security drift

Recent activity through 2026-03-05:

- repomix snapshot regenerated and core docs re-synced to current repository shape
- deployment drift now tracked with concrete root-vs-`docker/` script mismatch examples
- frontend form/filter guidance updated to mark exception paths explicitly
- Notification V2 Phase 1 foundation completed: domain event + outbox architecture, ops monitoring pages, retry capabilities
- `inertia-filter-table` skill documented as canonical pattern for server-filtered tables
- Production deployment guide created (`docs/deployment-guide.md`) with FrankenPHP, queue worker, scheduler setup
- System architecture updated with runtime scheduled commands and queue worker requirements
- Finance settlement v2 landed in runtime code: invoice-line settlement, settlement worklist, voucher/scholarship migration commands, dashboard/fee summary updates, and generate-charge logic aligned to zero-amount tuition + semester-aware EGC rules

## 3) Phase Plan

### Phase 0: Core Documentation Alignment

Status: Completed  
Completed date: 2026-02-25

Outcome:

- Core docs aligned to current code-verified state.
- Removed aspirational phrasing from quality/security posture.

### Phase 1: Deploy/Script Normalization

Status: In progress  
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

- define explicit policy for module-domain folders vs shared service layer
- apply policy incrementally on touched hotspots

Exit criteria:

- reduced placement ambiguity for new domain logic
- documented ownership by domain/module

### Phase 6: Finance Settlement Cutover

Status: In progress  
Target: active

Scope:

- remove runtime dependence on legacy `payment_allocations`
- standardize invoice-line settlement across fee summary, settlement worklist, generate charges, and dashboard
- backfill voucher/scholarship data into `invoice_discounts` + `discount_allocations`
- finish snapshot reconciliation for legacy invoices

Exit criteria:

- finance runtime reads no longer depend on `payment_allocations`
- discount truth is line-level via `discount_allocations`
- dashboard/worklist/invoice views agree on gross, discount, paid, outstanding
- zero-amount tuition terms no longer create invoices

## 4) Dependencies

- Phase 1 before Phase 2
- Phase 2 before strict merge-gate enforcement policy
- Phase 3 and Phase 4 can overlap after baseline CI is active
- Documentation updates are required in every phase
- Phase 6 depends on migration command rollout and snapshot reconciliation

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
5. Finance settlement cutover order for remaining legacy read models.

## Unresolved Questions

- Who is accountable for Phase 1 execution window and sign-off?
- Which API endpoints are external-facing contracts vs internal web support APIs?
- What is the acceptable grace period between code change and required doc update?
