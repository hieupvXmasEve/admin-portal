# Research Report: Track 2 - Vue3 + Inertia + TypeScript Scaling and Laravel API Contract Stability

Research timestamp: 2026-02-25 (US)

## Scope
- Route/API contract clarity between Laravel backend and Vue3+Inertia frontend
- Testing strategy for mixed backend/frontend stack
- Migration safety for contract-preserving evolution

## Method
- Sources used: 5 (hard cap met)
- Source type: official docs only
- Query terms: Inertia protocol/typescript/testing, Laravel routing/migrations

## Key Findings

### 1) Contract Clarity: treat Inertia page payload as a versioned contract
- Inertia responses are protocol-structured (`component`, `props`, `url`, `version`, etc.); client behavior depends on this shape. Breaking prop keys/types is a contract break even if route URL is unchanged [1].
- Use shared TypeScript page-prop interfaces and enforce strict typing in frontend entry points to catch drift earlier [2].
- Practical rule: every page has one canonical `PageProps` type; server transformers/resources must map to it exactly.

### 2) Route Clarity: stabilize names first, URLs second
- Laravel route names are your stable integration surface for server-side generation and navigation refactors; enforce naming conventions + route grouping for domain ownership [4].
- Require CI check on `php artisan route:list --json` diff. Reject accidental route name/path/middleware changes unless tagged as intentional contract change.
- Keep API and Inertia-web routes separate by file/group. Prevent mixed concerns in same controllers.

### 3) Testing Strategy (mixed stack): contract-first pyramid
- Backend contract tests: use Laravel feature tests for route behavior + payload schema assertions.
- Inertia endpoint tests: assert component name + required props for each page-level endpoint; this directly validates server->Inertia contract [3].
- Frontend tests: add TS type-check (`vue-tsc --noEmit`) as contract gate; catches prop/type mismatch even before browser tests [2].
- Minimum viable pipeline:
  1. PHP unit/feature tests
  2. Inertia endpoint assertions
  3. `vue-tsc --noEmit`
  4. Small E2E smoke (critical flows only)

### 4) Migration Safety: expand -> migrate -> contract -> cleanup
- Use additive migrations first (new nullable columns/tables/indexes), dual-read/dual-write phase, then cutover, then cleanup. Avoid destructive schema changes in same release as consumer switch.
- Laravel migration docs emphasize reversible migrations and controlled schema evolution; use explicit `up/down`, keep rollback path real, and run migration status checks in CI/CD [5].
- For high-risk changes: add backfill command + idempotent batches; release feature flag for read-path switch.

## Recommended Operating Model (KISS/YAGNI/DRY)
1. Define one contract manifest per route/page:
- Route name
- Auth/middleware
- Response shape (`PageProps` or API DTO)
- Owner module

2. Enforce change policy:
- Non-breaking: add optional fields only
- Breaking: new contract version or new route; old kept for deprecation window

3. Enforce CI gates:
- Route diff gate (`route:list --json`)
- Inertia contract tests
- `vue-tsc --noEmit`
- Migration dry-run in ephemeral DB

## Migration-Safe Rollout Template
1. Release A: add schema + write path (old readers intact)
2. Backfill: async command/job, measurable progress
3. Release B: switch read path via feature flag
4. Release C: remove old columns/routes after deprecation window

## Concrete Next Steps for this repo
1. Add `contracts/` docs for top 10 critical routes/pages first.
2. Add base test helper for Inertia contract assertions (component + required props).
3. Add CI step for route diff and `vue-tsc --noEmit`.
4. Define migration RFC checklist for any schema touching page/API contracts.

## Sources
1. Inertia protocol docs: https://inertiajs.com/the-protocol
2. Inertia TypeScript docs: https://inertiajs.com/typescript
3. Inertia testing docs: https://inertiajs.com/testing
4. Laravel routing docs (v12): https://laravel.com/docs/12.x/routing
5. Laravel migrations docs (v12): https://laravel.com/docs/12.x/migrations

## Unresolved Questions
- Current repo contract source-of-truth: should it live in `docs/` only, or generated from tests/routes metadata?
- Deprecation window target: 1 release or time-based (ex: 30/60 days)?
- Which 3 user journeys are mandatory E2E smoke flows for first gate?
