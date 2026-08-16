---
phase: 6
title: "Phase 6: Close Finance domain debt"
status: in-progress
priority: P0
effort: XL
dependencies: [3, 5]
---

# Phase 6: Close Finance domain debt

<!-- Rescoped 2026-08-16 from measured inventory; supersedes the 2026-07-26 draft. -->

## Progress

- 2026-08-16: `DeferCase`/`DeferCaseItem` moved `app/Models` → `app/Modules/Finance/Models`
  (only Finance consumed them; zero cross-module callers). Deleted two dead relations
  found along the way (`DeferCase::uploadRecord()`, `DeferCaseItem::courseRegistration()`)
  that would otherwise have tripped the zero-tolerance `cross_context_concrete_imports`
  rule and ADR-0026's Academic/Finance boundary test. Added a forward-only backfill
  migration for `finance_charges.source_type` (raw FQCN polymorphic column, no morphMap
  entry) covering any historical row still carrying the pre-move class string.
  `shared_model_imports` 113→104 for this phase; `tests/Feature/Finance` unaffected
  (only gained passes). Remaining `App\Models\{Student,Semester,StudentActionLog,User}`
  reads inside the moved models are still open — Step 1's classification, not yet
  routed through owner contracts.

## Overview

Make Finance the sole owner of obligations, settlement, collection, DNG,
billing, scholarships, vouchers, wallets, and Finance administration, without
changing money.

The 2026-07-26 draft sized this phase at 93 shared-model imports. The measured
count is now 113, and the module's internal shape changed underneath the draft:
the obligation-v2 work introduced billing accounts, a credit-application ledger,
and a materialized charge read model, all governed by ADR-0027 through ADR-0030.
This phase must land inside those decisions rather than beside them.

## Measured scope (2026-08-16)

133 findings: 102 `shared_model_imports`, 15 `inline_request_validation`,
5 `frozen_controllers`, 4 `frozen_routes`, 4 `frozen_services`,
3 `direct_json_responses`.

Re-measured after the `DeferCase`/`DeferCaseItem` move in `c78b66f6e`, which
closed 11 `shared_model_imports` findings in this phase's territory.

120 of those sit under `app/Modules/Finance`, broken down as:

| Cluster | Findings |
|---|---:|
| `Finance/Models` | 36 |
| `Finance/Http` | 26 |
| `Finance/Queries` | 25 |
| `Finance/Support` | 15 |
| `Finance/Actions` | 9 |
| `Finance/Dng` | 6 |
| `Finance/Policies` | 3 |

The concentration in `Models` and `Queries` rather than `Http` says the debt is
mostly read-side coupling to Academic and Registry models, not controller-layer
debt. Plan the slices around readers, not around endpoints.

The remaining 13 are frozen shells outside the module that carry live money
workflows: `app/Http/Controllers/{Scholarship,StudentScholarship,StudentFinancialImport,TuitionPlan,Voucher}Controller.php`,
their `routes/web/{scholarships,student-scholarships,tuition-plans,vouchers}.php`
split files, and the matching
`app/Services/{Scholarship,TuitionPlan,Voucher,StudentFinancialImport}Service.php`.
The scanner tagged these to phase 9 until `frozenShellPhase()` was corrected on
2026-08-16; they now tag here, where the cutover actually happens.

## Requirements

- [ ] Replace external Student and Academic model access with owner contracts and events.
- [ ] Prove canonical gross, discount, cash, credit, collectible, and allocation totals.
- [ ] Keep every change inside ADR-0027 through ADR-0030; a slice that contradicts one
  of them needs a new ADR first, not an exception.
- [ ] Preserve DNG provider acknowledgment, webhook durability, reconciliation, and idempotency.
- [ ] Preserve the payment-allocation rule that a payment applies only to its own fee and
  surplus stays unapplied; do not reintroduce waterfall spill while refactoring.
- [ ] Retire the thirteen scholarship, tuition-plan, voucher, and financial-import shells
  now tagged here, since they are live workflows rather than phase-9 residue.
- [ ] Do not run pointer-retirement migrations until phases 10 and 11 pass.
- [ ] Portal impact: student; update the Finance API contract and student portal in the
  same package.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Models` | Break read-side coupling to Academic and Registry models |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Queries` | Replace cross-context model reads with owner contracts |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Http` | Move validation into FormRequests; canonical envelopes |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Support` | Resolve settlement and allocation support coupling |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Dng` | Keep the provider boundary explicit and named |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/ScholarshipController.php` | Retire into Finance |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/StudentScholarshipController.php` | Retire into Finance |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/StudentFinancialImportController.php` | Retire into Finance |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/TuitionPlanController.php` | Retire into Finance |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/VoucherController.php` | Retire into Finance |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Services` | Retire the matching legacy billing, scholarship, voucher, and wallet services |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Finance` | Canonical filters, routes, and operations UI |
| `/Users/hunt2412/hieupvdev/project/swinx/FE/student-nuxt` | Update Finance composables, types, and pages |
| `/Users/hunt2412/hieupvdev/project/swinx/docs/api` | Update the student Finance contract |
| `/Users/hunt2412/hieupvdev/project/swinx/database/migrations` | Preserve guarded forward-only migrations for phase 11 |
| `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Finance` | Extend invariant and provider coverage |

## Interface Checklist

- Academic and Registry supply stable identifiers and events, never Finance-owned decisions.
- Finance queries accept typed criteria or arrays rather than `Request`.
- All money writes are transactional, idempotent, auditable, and use canonical precision.
- Provider response is an explicit external contract, not `ApiResponse::compatible()` concealment.
- Billing account remains the payer key for new aggregates, per ADR-0029.
- Credit reduction flows through the credit-application ledger, per ADR-0030; no new
  negative charges.

## Dependency Map

`Registry/Progression facts → Finance obligations → settlement/DNG → reports/portal → reconciliation`

## Implementation Steps

1. Classify all 113 imports by obligation or settlement workflow, separating read-side
   coupling from write-side coupling; the read side is the bulk and can move first.
2. Replace the Student observer with a post-commit Registry event or owner command.
3. Migrate tuition, scholarship, voucher, wallet, billing, EGC, and DNG vertical slices.
4. Move request validation into FormRequests and query criteria into typed DTOs or arrays.
5. Convert admin and API responses while preserving the DNG provider boundary.
6. Migrate Finance pages to canonical filters and routes; delete proven dead pages.
7. Retire shells and lower ratchets only after owner tests and total reconciliation pass.
8. Run student portal lint, typecheck, build, and contract tests for every API package.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Student lifecycle event replay | One canonical obligation effect |
| Discount, cash, and credit allocation | Totals reconcile exactly |
| Payment exceeding its own fee | Surplus stays unapplied; no spill to other fees |
| DNG duplicate or out-of-order webhook | Idempotent durable acknowledgment |
| Cancellation, refund, reversal | Ledger and provider state reconcile |
| Admin filter validation | FormRequest error envelope and safe query |
| Shared import scan | Finance count reaches zero |

## Success Criteria

- [ ] Finance has zero non-owner shared-model imports and zero frozen shell debt.
- [ ] Finance invariant suites and production-style reconciliation report zero violations.
- [ ] No slice contradicts ADR-0027 through ADR-0030 without a superseding ADR.
- [ ] Forward-only cleanup remains unapplied and has explicit recovery evidence.

## Risks and Security

- Never recompute or discard verified money or provider evidence. Protect webhook
  signatures, campus scoping, replay defense, precision, audit trails, and transactional
  boundaries.
- The Finance test suite has a known red baseline; measure against that baseline rather
  than assuming a green tree, and do not absorb its failures into this phase's evidence.
