# Project Roadmap

Last updated: 2026-02-23
Planning horizon: next 2-3 quarters

## 1) Roadmap Goals

- Stabilize engineering workflow and deployment reliability.
- Clarify and harden public/internal API contracts.
- Reduce regression risk in academic and finance domains.
- Consolidate documentation into a reliable source of truth.

## 2) Current Baseline Status

- Core platform exists and is feature-rich across Identity, Academic, and Finance.
- Documentation exists but is fragmented and partially stale.
- CI pipelines are present but disabled.
- Several scripts and Docker references are inconsistent.
- Test footprint is low for current codebase size.

## 3) Phases and Milestones

## Phase 0: Documentation Baseline (Completed)

Status: Completed (2026-02-23)

Deliverables:
- Updated `README.md`
- Created baseline docs:
  - `docs/project-overview-pdr.md`
  - `docs/codebase-summary.md`
  - `docs/code-standards.md`
  - `docs/system-architecture.md`
  - `docs/project-roadmap.md`
  - `docs/deployment-guide.md`
  - `docs/design-guidelines.md`

## Phase 1: Environment and Deployment Stabilization

Status: Planned
Target window: 1-2 sprints

Scope:
- Normalize Docker compose/script paths and env file conventions.
- Decide one canonical local-dev command set.
- Decide one canonical production deployment path.
- Re-enable CI workflows with minimum required checks.

Success criteria:
- Clean local setup path documented and reproducible.
- CI runs lint + type-check + tests on PRs.
- Deployment script references match actual repository paths.

## Phase 2: API Contract Hardening

Status: Planned
Target window: 1-2 sprints after Phase 1

Scope:
- Resolve TODO/commented endpoint decisions in student/lecturer API routes.
- Normalize rate-limit middleware usage by endpoint category.
- Document campus-context behavior clearly for admin/internal APIs.
- Publish a stable API index map in `docs/api/`.

Success criteria:
- No ambiguous TODO route blocks in critical API files.
- Auth and rate-limit behavior documented per route group.

## Phase 3: Testing and Quality Coverage

Status: Planned
Target window: 2-3 sprints

Scope:
- Add feature tests for critical auth + route access + high-risk endpoints.
- Add unit/integration tests for finance charge/invoice flows and academic progression logic.
- Add regression checks for critical middleware and permission paths.

Success criteria:
- Meaningful increase in backend test coverage for critical modules.
- CI fails reliably on regressions in protected areas.

## Phase 4: Architecture Consolidation

Status: Planned
Target window: ongoing

Scope:
- Define policy for future logic placement (`Modules` vs `Services`).
- Incrementally reduce service-layer hotspots when touching related code.
- Standardize action/query method conventions for new code.

Success criteria:
- Documented architecture decision record for layering.
- Reduced ambiguity in contribution patterns.

## Phase 5: Documentation Consolidation

Status: Planned
Target window: ongoing

Scope:
- Clean stale indexes and overlapping docs.
- Add role-based doc navigation (admin, student API, lecturer API, ops).
- Keep baseline docs in sync with code and release milestones.

Success criteria:
- `docs/README.md` accurately links active docs.
- Reduced duplicate/conflicting doc pages.

## 4) Dependencies and Sequencing

- Phase 1 should precede broad CI/deployment changes.
- Phase 2 should precede major external API consumer onboarding.
- Phase 3 should start with highest business-risk flows, not broad low-value coverage.
- Phase 5 is continuous and should be updated every phase.

## 5) Tracking Metrics

- Build reliability: CI pass rate and runtime stability.
- API stability: number of unresolved TODO/undocumented behaviors.
- Test health: count of critical-path tests and failure detection effectiveness.
- Docs health: stale-link count and update latency after code changes.

## Unresolved Questions

- Which team owns Phase 1 script/deployment normalization?
- What is the required minimum CI gate for merge (lint/type-check/test subsets)?
- Which API surfaces are considered externally committed contracts vs internal-only?
