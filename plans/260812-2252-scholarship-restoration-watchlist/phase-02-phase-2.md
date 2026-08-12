---
phase: 2
title: "Restoration HTTP layer (Finance)"
status: done
priority: P1
effort: "0.5-1d"
dependencies: [1]
---

# Phase 2: Restoration HTTP layer (Finance)

## Overview

Expose the three existing restoration Actions (Create/Approve/Reject) via
staff web endpoints in the **Finance module** (owner of the models/actions).
Clears the "E2E deferred" debt from plan 260731 phase 5.

## Requirements

- Functional: POST propose (with optional `restored_amount`), POST approve,
  POST reject; all campus-scoped; permission `approve_scholarship_adjustment`
  (user decision 2026-08-12 — reuse, no new permission).
- Non-functional: no Finance→Academic import (`ScholarshipDossierCloser`
  contract already handles dossier close on approve).

## Architecture

Thin controller → existing Actions. FormRequests own validation (Vietnamese
messages per repo `lang/vn` convention, "remaining value" semantics copied
from `DecideScholarshipAdjustmentRequest` style with dynamic
`max`/`min` from the adjustment row). NO maker≠checker (product decision
2026-08-02 — proposer may equal approver; do not reinstate).

## Related Code Files

- Create: `app/Modules/Finance/Http/Web/Admin/ScholarshipRestorationController.php`
  (methods: `propose`, `approve`, `reject`)
- Create: `app/Modules/Finance/Http/Requests/ScholarshipRestoration/ProposeScholarshipRestorationRequest.php`
  (`reason` required; `restored_amount` nullable numeric, `gt:{floor}`, `lt:original_amount`
  — floor = latest approved proposal's `restored_amount` ?? `adjusted_amount`,
  resolved from route-bound adjustment; dynamic-bound style per `DecideScholarshipAdjustmentRequest::adjustedAmountCeiling()`)
- Create: `app/Modules/Finance/Http/Requests/ScholarshipRestoration/ApproveScholarshipRestorationRequest.php`
- Create: `app/Modules/Finance/Http/Requests/ScholarshipRestoration/RejectScholarshipRestorationRequest.php` (`reason` required)
- Modify: `app/Modules/Finance/routes/web.php` — group `scholarship-restorations`:
  `POST adjustments/{adjustment}/scholarship-restorations` (propose),
  `POST scholarship-restorations/{proposal}/approve`,
  `POST scholarship-restorations/{proposal}/reject`,
  middleware `can:approve_scholarship_adjustment` + existing campus-scope middleware (copy sibling Finance route group)
- Modify: policy — locate existing policy used by restoration actions; if none,
  gate purely via permission middleware + campus check in controller (match
  sibling Finance staff controllers, e.g. `FinanceChargeController`)
- Create: placement arch test
  `tests/Feature/Architecture/ScholarshipRestorationModulePlacementArchTest.php`
  (HTTP + routes live in Finance module; no stray copy under global `app/Http`)
- Tests: `tests/Feature/Finance/ScholarshipRestoration/` HTTP tests

## Implementation Steps

1. Read sibling Finance staff controller + route group for exact middleware
   stack; copy convention.
2. FormRequests with bound-model bounds + `lang/vn` messages.
3. Controller wiring Actions; propose passes `restoredAmount`; approve/reject
   pass approver user id; redirect back with toast flash (repo convention).
4. Routes + permission middleware.
5. Arch test + HTTP feature tests: 403 without permission, 403 cross-campus,
   422 bounds, happy paths (propose partial, propose full, approve flips +
   closes dossier via contract, reject), guard behavior per Phase 1 narrowing:
   pending blocks, approved-full blocks, approved-partial allows repeat with
   raised floor.
6. Feature tests need `_token` (CSRF active in tests — repo gotcha).

## Todo

- [x] Controller + 3 FormRequests
- [x] Routes in Finance module `routes/web.php`
- [x] Arch placement test
- [x] HTTP feature tests green

## Success Criteria

- [x] All 3 endpoints functional, permission + campus gated
- [x] Partial amount validated against the adjustment's own bounds
- [x] Approve closes Academic dossier via `ScholarshipDossierCloser` (existing behavior, asserted)

## Risk Assessment

- Permission reuse means Academic approvers can also decide restorations —
  accepted by user; note in PRD (Phase 5).
- Duplicate proposals: rely on existing action-level lock
  (`CreateRestorationProposalAction` lockForUpdate); test double-submit.
